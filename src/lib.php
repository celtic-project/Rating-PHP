<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\OAuth;
use ceLTIc\LTI\Enum\ServiceAction;

/**
 * This page provides general functions to support the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('db.php');
require_once('rating_tp.php');

###  Application settings
define('APP_NAME', 'Rating');
define('APP_VERSION', '5.0.0');
define('SESSION_NAME', 'php-rating');

LTI\ResourceLink::registerApiHook(LTI\ResourceLink::$MEMBERSHIPS_SERVICE_HOOK, 'moodle',
    'ceLTIc\LTI\ApiHook\moodle\MoodleApiResourceLink');
LTI\Tool::registerApiHook(LTI\Tool::$USER_ID_HOOK, 'canvas', 'ceLTIc\LTI\ApiHook\canvas\CanvasApiTool');
LTI\ResourceLink::registerApiHook(LTI\ResourceLink::$MEMBERSHIPS_SERVICE_HOOK, 'canvas',
    'ceLTIc\LTI\ApiHook\canvas\CanvasApiResourceLink');

###
###  Initialise application session and database connection
###

function init(&$db, $checkSession = null, $currentLevel = 0)
{
    $ok = true;

// Check if path value passed by web server needs amending
    if (defined('REQUEST_URI_PREFIX') && !empty(REQUEST_URI_PREFIX)) {
        $_SERVER['REQUEST_URI'] = REQUEST_URI_PREFIX . $_SERVER['REQUEST_URI'];
    }

// Set timezone
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('UTC');
    }

// Set session cookie path
    ini_set('session.cookie_path', getAppPath($currentLevel));

// Set samesite value for cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_samesite', 'none');
        ini_set('session.cookie_secure', true);
    }

// Open session
    session_name(SESSION_NAME);
    session_start();

// Set the default tool
    LTI\Tool::$defaultTool = new RatingTool(null);

    if (!is_null($checkSession) && $checkSession) {
        $ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
            isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
    }

    if (!$ok) {
        $_SESSION['error_message'] = 'Unable to open session.';
    } else {
// Open database connection
        $db = open_db(!$checkSession);
        $ok = $db !== false;
        if (!$ok) {
            if (!is_null($checkSession) && $checkSession) {
// Display a more user-friendly error message to LTI users
                $_SESSION['error_message'] = 'Unable to open database.';
            }
        } else if (!is_null($checkSession) && !$checkSession) {
// Create database tables (if needed)
            $ok = init_db($db);  // assumes a MySQL/SQLite database is being used
            if (!$ok) {
                $_SESSION['error_message'] = 'Unable to initialise database: \'' . $db->errorInfo()[2] . '\'';
            }
        }
    }

    return $ok;
}

###
###  Return the number of items to be rated for a specified resource link
###

function getNumItems($db, $resourcePk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT COUNT(i.item_pk)
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
    $query->execute();

    $row = $query->fetch(PDO::FETCH_NUM);
    if ($row === false) {
        $num = 0;
    } else {
        $num = intval($row[0]);
    }

    return $num;
}

###
###  Return an array containing the items for a specified resource link
###

function getItems($db, $resourcePk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating mr, i.step st, i.visible vis, i.sequence seq,
   i.created cr, i.updated upd, COUNT(r.user_pk) num, SUM(r.rating) total
FROM {$prefix}item i LEFT OUTER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE (i.resource_link_pk = :resource_pk)
GROUP BY i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating, i.step, i.visible, i.sequence, i.created, i.updated
ORDER BY i.sequence
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_CLASS, 'Item');
    if ($rows === false) {
        $rows = array();
    }

    return $rows;
}

###
###  Return an array of ratings made for items for a specified resource link by a specified user
###

function getUserRated($db, $resourcePk, $userPk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT r.item_pk, r.rating
FROM {$prefix}item i INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE (i.resource_link_pk = :resource_pk) AND (r.user_pk = :user_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $userPk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    $rated = array();
    if ($rows !== false) {
        foreach ($rows as $row) {
            $rated[$row->item_pk] = $row->rating;
        }
    }

    return $rated;
}

###
###  Return details for a specific item for a specified resource link
###

function getItem($db, $resourcePk, $itemPk)
{
    $item = new Item();

    if (!empty($itemPk)) {
        $prefix = DB_TABLENAME_PREFIX;
        $sql = <<< EOD
SELECT i.item_pk, i.item_title, i.item_text, i.item_url, i.max_rating mr, i.step st, i.visible vis, i.sequence seq, i.created cr, i.updated upd
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk) AND (i.item_pk = :item_pk)
EOD;

        $query = $db->prepare($sql);
        $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
        $query->bindValue('item_pk', $itemPk, PDO::PARAM_INT);
        $query->setFetchMode(PDO::FETCH_CLASS, 'Item');
        $query->execute();

        $row = $query->fetch();
        if ($row !== false) {
            $item = $row;
        }
    }

    return $item;
}

###
###  Save the details for an item for a specified resource link
###

function saveItem($db, $resourcePk, $item)
{
    $prefix = DB_TABLENAME_PREFIX;
    if (!isset($item->item_pk)) {
        $sql = <<< EOD
INSERT INTO {$prefix}item (resource_link_pk, item_title, item_text, item_url, max_rating, step, visible, sequence, created, updated)
VALUES (:resource_pk, :item_title, :item_text, :item_url, :max_rating, :step, :visible, :sequence, :created, :updated)
EOD;
    } else {
        $sql = <<< EOD
UPDATE {$prefix}item
SET item_title = :item_title, item_text = :item_text, item_url = :item_url, max_rating = :max_rating, step = :step, visible = :visible,
    sequence = :sequence, updated = :updated
WHERE (item_pk = :item_pk) AND (resource_link_pk = :resource_pk)
EOD;
    }
    $query = $db->prepare($sql);
    $item->updated = new DateTime();
    if (!isset($item->item_pk)) {
        $item->created = $item->updated;
        $item->sequence = getNumItems($db, $resourcePk) + 1;
        $query->bindValue('created', $item->created->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    } else {
        $query->bindValue('item_pk', $item->item_pk, PDO::PARAM_INT);
    }
    $query->bindValue('item_title', $item->item_title, PDO::PARAM_STR);
    $query->bindValue('item_text', $item->item_text, PDO::PARAM_STR);
    $query->bindValue('item_url', $item->item_url, PDO::PARAM_STR);
    $query->bindValue('max_rating', $item->max_rating, PDO::PARAM_INT);
    $query->bindValue('step', $item->step, PDO::PARAM_INT);
    $query->bindValue('visible', $item->visible, PDO::PARAM_INT);
    $query->bindValue('sequence', $item->sequence, PDO::PARAM_INT);
    $query->bindValue('updated', $item->updated->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);

    $ok = $query->execute();

    if ($ok && !isset($item->item_pk)) {
        $item->item_pk = intval($db->lastInsertId());
    }

    return $ok;
}

###
###  Delete the ratings for an item
###

function deleteRatings($db, $itemPk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}rating
WHERE item_pk = :item_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $itemPk, PDO::PARAM_INT);
    $query->execute();
}

###
###  Delete a specific item for a specified resource link including any related ratings
###

function deleteItem($db, $resourcePk, $itemPk)
{
// Update order for other items for the same resource link
    reorderItem($db, $resourcePk, $itemPk, 0);

// Delete any ratings
    deleteRatings($db, $itemPk);

// Delete the item
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}item
WHERE (item_pk = :item_pk) AND (resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $itemPk, PDO::PARAM_INT);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_STR);
    $ok = $query->execute();

    return $ok;
}

###
###  Change the position of an item in the list displayed for the resource link
###

function reorderItem($db, $resourcePk, $itemPk, $new_pos)
{
    $item = getItem($db, $resourcePk, $itemPk);

    $ok = !empty($item->item_pk);
    if ($ok) {
        $old_pos = $item->sequence;
        $ok = ($old_pos != $new_pos);
    }
    if ($ok) {
        $prefix = DB_TABLENAME_PREFIX;
        if ($new_pos <= 0) {
            $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence - 1
WHERE (resource_link_pk = :resource_pk) AND (sequence > :old_pos)
EOD;
        } else if ($old_pos < $new_pos) {
            $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence - 1
WHERE (resource_link_pk = :resource_pk) AND (sequence > :old_pos) AND (sequence <= :new_pos)
EOD;
        } else {
            $sql = <<< EOD
UPDATE {$prefix}item
SET sequence = sequence + 1
WHERE (resource_link_pk = :resource_pk) AND (sequence < :old_pos) AND (sequence >= :new_pos)
EOD;
        }

        $query = $db->prepare($sql);
        $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
        $query->bindValue('old_pos', $old_pos, PDO::PARAM_INT);
        if ($new_pos > 0) {
            $query->bindValue('new_pos', $new_pos, PDO::PARAM_INT);
        }

        $ok = $query->execute();

        if ($ok && ($new_pos > 0)) {
            $item->sequence = $new_pos;
            $ok = saveItem($db, $resourcePk, $item);
        }
    }

    return $ok;
}

###
###  Delete all the ratings for an resource link
###

function deleteAllRatings($db, $resourcePk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}rating
WHERE resource_link_pk = :resource_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_INT);
    $query->execute();
}

###
###  Delete all items for a specified resource link including any related ratings
###

function deleteAllItems($db, $resourcePk)
{
// Delete any ratings
    deleteAllRatings($db, $resourcePk);

// Delete the items
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
DELETE FROM {$prefix}item
WHERE (resource_link_pk = :resource_pk)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_STR);
    $ok = $query->execute();

    return $ok;
}

###
###  Save the rating for an item for a specified user
###

function saveRating($db, $userPk, $itemPk, $rating)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
INSERT INTO {$prefix}rating (item_pk, user_pk, rating)
VALUES (:item_pk, :user_pk, :rating)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('item_pk', $itemPk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $userPk, PDO::PARAM_INT);
    $query->bindValue('rating', $rating);

    $ok = $query->execute();

    return $ok;
}

###
###  Create/update the line item for this item
###

function saveLineItem($resourceLink, $item, $isNew)
{
    if ($resourceLink->hasLineItemService()) {
        $resourceId = strval($item->item_pk);
        $label = "Rating: {$item->item_title}";
        $tag = 'Rating';
        $pointsPossible = $item->max_rating;
        $visible = $item->visible;
        if (!$isNew) {
            $lineItems = $resourceLink->getLineItems($resourceId);
            $isNew = empty($lineItems);
        }
        if (!$isNew) {
            $lineItem = reset($lineItems);
            if (!$visible) {
                $lineItem->delete();
            } else if (($lineItem->label !== $label) || ($lineItem->tag !== $tag) || ($lineItem->pointsPossible !== $pointsPossible)) {
                $lineItem->label = $label;
                $lineItem->tag = $tag;
                $lineItem->pointsPossible = $pointsPossible;
                $lineItem->save();
            }
        } else if ($visible) {
            $lineItem = new LTI\LineItem($resourceLink->getPlatform(), $label, $pointsPossible);
            $lineItem->resourceId = $resourceId;
            $lineItem->tag = $tag;
            $resourceLink->createLineItem($lineItem);
        }
    }
}

###
###  Create/update the outcomes in the line item associated with this item
###

function updateLineItemOutcomes($resourceLink, $item)
{
    global $db;

    if ($resourceLink->hasLineItemService()) {
        $id = strval($item->item_pk);
        $lineItems = $resourceLink->getLineItems($id);
        if (!empty($lineItems)) {
            $lineItem = $lineItems[0];
            $users = $resourceLink->getUserResultSourcedIDs();
            foreach ($users as $user) {
                $userRated = getUserRated($db, $resourceLink->getRecordId(), $user->getRecordId());
                if (array_key_exists($id, $userRated)) {
                    $outcome = new LTI\Outcome($userRated[$id], $item->max_rating);
                    $lineItem->submitOutcome($outcome, $user);
                }
            }
        }
    }
}

###
###  Update the gradebook with proportion of visible items which have been rated by each user
###

function updateGradebook($db, $userResourcePk = null, $userUserPk = null, $item = null, $rating = null)
{
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $resourceLink = LTI\ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);

    $num = getVisibleItemsCount($db, $_SESSION['resource_pk']);
    $ratings = getVisibleRatingsCounts($db, $_SESSION['resource_pk']);
    $users = $resourceLink->getUserResultSourcedIDs();
    foreach ($users as $user) {
        $resourcePk = $user->getResourceLink()->getRecordId();
        $userPk = $user->getRecordId();
        $update = is_null($userResourcePk) || is_null($userUserPk) || (($userResourcePk === $resourcePk) && ($userUserPk === $userPk));
        if ($update) {
            if ($num > 0) {
                $count = 0;
                if (isset($ratings[$resourcePk]) && isset($ratings[$resourcePk][$userPk])) {
                    $count = $ratings[$resourcePk][$userPk];
                }
                $ltiOutcome = new LTI\Outcome($count, $num);
                if ($count === 1) {
                    $itemDesc = 'item';
                } else {
                    $itemDesc = 'items';
                }
                $ltiOutcome->comment = "{$count} {$itemDesc} rated out of {$num}.";
                $resourceLink->doOutcomesService(ServiceAction::Write, $ltiOutcome, $user);
            } else {
                $ltiOutcome = new LTI\Outcome();
                $resourceLink->doOutcomesService(ServiceAction::Delete, $ltiOutcome, $user);
            }
        }
    }
    if (!empty($userResourcePk) && !empty($userUserPk) && !empty($item) && !empty($rating)) {
        if ($userResourcePk !== $_SESSION['resource_pk']) {
            $resourceLink = LTI\ResourceLink::fromRecordId($userResourcePk, $dataConnector);
        }
        if ($resourceLink->hasLineItemService()) {
            $lineItems = $resourceLink->getLineItems(strval($item->item_pk));
            if (!empty($lineItems)) {
                $user = LTI\UserResult::fromRecordId($userUserPk, $dataConnector);
                $outcome = new LTI\Outcome($rating, $item->max_rating);
                $lineItems[0]->submitOutcome($outcome, $user);
            }
        }
    }
}

###
###  Return a count of visible items for a specified resource link
###

function getVisibleItemsCount($db, $resourcePk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT COUNT(i.item_pk) count
FROM {$prefix}item i
WHERE (i.resource_link_pk = :resource_pk) AND (i.visible = 1)
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_STR);
    $query->execute();

    $row = $query->fetch(PDO::FETCH_NUM);
    if ($row === false) {
        $num = 0;
    } else {
        $num = intval($row[0]);
    }

    return $num;
}

###
###  Return a count of visible ratings made for items for a specified resource link by each user
###

function getVisibleRatingsCounts($db, $resourcePk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $userTableName = DataConnector\DataConnector::USER_RESULT_TABLE_NAME;
    $sql = <<< EOD
SELECT u.resource_link_pk, r.user_pk, COUNT(r.item_pk) count
FROM {$prefix}item i INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
  INNER JOIN {$prefix}{$userTableName} u ON r.user_pk = u.user_result_pk
WHERE (i.resource_link_pk = :resource_pk) AND (i.visible = 1)
GROUP BY u.resource_link_pk, r.user_pk
EOD;
    $query = $db->prepare($sql);
    $query->bindValue('resource_pk', $resourcePk, PDO::PARAM_STR);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    $ratings = array();
    if ($rows !== false) {
        foreach ($rows as $row) {
            $ratings[$row->resource_link_pk][$row->user_pk] = intval($row->count);
        }
    }

    return $ratings;
}

###
###  Return an array containing all the ratings for a specific user
###

function getUserSummary($db, $userConsumerPk, $userPk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $sql = <<< EOD
SELECT c.context_pk, i.resource_link_pk, i.max_rating, r.rating
FROM {$prefix}lti_context c INNER JOIN  {$prefix}item i ON c.consumer_key = i.consumer_key AND c.context_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE r.consumer_pk = :consumer_pk AND r.user_pk = :user_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('consumer_pk', $userConsumerPk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $userPk, PDO::PARAM_STR);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === false) {
        $rows = array();
    }

    return $rows;
}

###
###  Return an array containing all of a user's ratings for a specific context
###

function getUserRatings($db, $contextPk, $userPk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $resourceLinkTableName = DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME;
    $sql = <<< EOD
SELECT rl.resource_link_pk, i.max_rating, r.rating
FROM {$prefix}{$resourceLinkTableName} rl INNER JOIN  {$prefix}item i ON rl.resource_link_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE rl.context_pk = :context_pk AND r.user_pk = :user_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('context_pk', $contextPk, PDO::PARAM_INT);
    $query->bindValue('user_pk', $userPk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === false) {
        $rows = array();
    }

    return $rows;
}

###
###  Return an array containing all ratings for a specific context
###

function getContextRatings($db, $contextPk)
{
    $prefix = DB_TABLENAME_PREFIX;
    $resourceLinkTableName = DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME;
    $sql = <<< EOD
SELECT rl.resource_link_pk title, i.max_rating, r.rating
FROM {$prefix}{$resourceLinkTableName} rl INNER JOIN {$prefix}item i ON rl.resource_link_pk = i.resource_link_pk
  INNER JOIN {$prefix}rating r ON i.item_pk = r.item_pk
WHERE rl.context_pk = :context_pk
EOD;

    $query = $db->prepare($sql);
    $query->bindValue('context_pk', $contextPk, PDO::PARAM_INT);
    $query->execute();

    $rows = $query->fetchAll(PDO::FETCH_OBJ);
    if ($rows === false) {
        $rows = array();
    }

    return $rows;
}

###
###  Get the web path to the application
###

function getAppPath($currentLevel = 0)
{
    $path = getAppUrl($currentLevel);
    $pos = strpos($path, '/', 8);
    if ($pos !== false) {
        $path = substr($path, $pos);
    }

    return $path;
}

###
###  Get the URL to the application
###

function getAppUrl($currentLevel = 0)
{
    $request = OAuth\OAuthRequest::from_request();
    $url = $request->get_normalized_http_url();
    for ($i = 1; $i <= $currentLevel; $i++) {
        $pos = strrpos($url, '/');
        if ($pos === false) {
            break;
        } else {
            $url = substr($url, 0, $pos);
        }
    }
    $pos = strrpos($url, '/');
    if ($pos !== false) {
        $url = substr($url, 0, $pos + 1);
    }

    return $url;
}

###
###  Return a string representation of a float value
###

function floatToStr($num)
{
    $str = sprintf('%f', $num);
    $str = preg_replace('/0*$/', '', $str);
    if (substr($str, -1) == '.') {
        $str = substr($str, 0, -1);
    }

    return $str;
}

###
###  Return the value of a POST parameter
###

function postValue($name, $defaultValue = null)
{
    $value = $defaultValue;
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    }

    return $value;
}

function pageFooter()
{
    $here = function($val) {
        return $val;
    };

    return <<< EOD
    <footer>
      <div>{$here(APP_NAME)} version {$here(APP_VERSION)} &copy; {$here(date('Y'))} <a href="//celtic-project.org/" target="_blank">ceLTIc Project</a> (powered by its open source <a href="https://github.com/celtic-project/LTI-PHP" target="_blank">LTI-PHP library</a>)</div>
    </footer>

EOD;
}

function ratingHtmlEntities($string, $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encoding = null, $double_encode = true)
{
    if (is_int($string) || is_float($string)) {
        return strval($string);
    } else if (!is_string($string)) {
        return '';
    } else {
        return htmlentities($string, $flags, $encoding, $double_encode);
    }
}

/**
 * Returns a string representation of a version 4 GUID, which uses random
 * numbers.There are 6 reserved bits, and the GUIDs have this format:
 *     xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
 * where 'x' is a hexadecimal digit, 0-9a-f.
 *
 * See http://tools.ietf.org/html/rfc4122 for more information.
 *
 * Note: This function is available on all platforms, while the
 * com_create_guid() is only available for Windows.
 *
 * Source: https://github.com/Azure/azure-sdk-for-php/issues/591
 *
 * @return string A new GUID.
 */
function getGuid()
{
    return sprintf('%04x%04x-%04x-%04x-%02x%02x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
        mt_rand(0, 65535), // 16 bits for "time_mid"
        mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with
// the most significant 4 bits being 0100
// to indicate randomly generated version
        mt_rand(0, 64) + 128, // 8 bits  for "clock_seq_hi", with
// the most significant 2 bits being 10,
// required by version 4 GUIDs.
        mt_rand(0, 256), // 8 bits  for "clock_seq_low"
        mt_rand(0, 65535), // 16 bits for "node 0" and "node 1"
        mt_rand(0, 65535), // 16 bits for "node 2" and "node 3"
        mt_rand(0, 65535)         // 16 bits for "node 4" and "node 5"
    );
}

###
###  Class representing an item
###

class Item
{

    public $item_pk = null;
    public $item_title = '';
    public $item_text = '';
    public $item_url = '';
    public $max_rating = 3;
    public $step = 1;
    public $visible = false;
    public $sequence = 0;
    public $created = null;
    public $updated = null;
    public $num_ratings = 0;
    public $tot_ratings = 0;

// ensure non-string properties have the appropriate data type
    function __set($name, $value)
    {
        if ($name == 'mr') {
            $this->max_rating = intval($value);
        } else if ($name == 'st') {
            $this->step = intval($value);
        } else if ($name == 'vis') {
            $this->visible = $value == '1';
        } else if ($name == 'seq') {
            $this->sequence = intval($value);
        } else if ($name == 'cr') {
            $this->created = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        } else if ($name == 'upd') {
            $this->updated = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        } else if ($name == 'num') {
            $this->num_ratings = intval($value);
        } else if ($name == 'total') {
            $this->tot_ratings = floatval($value);
        }
    }

}

?>