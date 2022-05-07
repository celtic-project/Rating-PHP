<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\Content;

/**
 * This page displays a list of items for a resource link.  Students are able to rate
 * each item; staff may add, edit, re-order and delete items.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

// Initialise session and database
$db = null;
$ok = init($db, true);
// Initialise parameters
$id = 0;
$userList = false;

if ($ok) {
    $action = '';
// Check for item id and action parameters
    if (isset($_REQUEST['id'])) {
        $id = intval($_REQUEST['id']);
    }
    if (isset($_REQUEST['do'])) {
        $action = $_REQUEST['do'];
    }

// Process add/update item action
    if ($action == 'add') {
        $updateItem = getItem($db, $_SESSION['resource_pk'], $id);
        $updateItem->item_title = $_POST['title'];
        $updateItem->item_text = $_POST['text'];
        $updateItem->item_url = $_POST['url'];
        $updateItem->max_rating = intval($_POST['max_rating']);
        $updateItem->step = intval($_POST['step']);
        $wasVisible = $updateItem->visible;
        $updateItem->visible = isset($_POST['visible']);
// Ensure all required fields have been provided
        if (isset($_POST['id']) && isset($_POST['title']) && !empty($_POST['title'])) {
            $ok = true;
            $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
            $platform = LTI\Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
            if (is_null($_SESSION['resource_pk'])) {
                $resourceLink = LTI\ResourceLink::fromPlatform($platform, $_SESSION['resource_id']);
                $ok = $resourceLink->save();
            } else {
                $resourceLink = LTI\ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
            }
            if ($ok) {
                $_SESSION['resource_pk'] = $resourceLink->getRecordId();
                $ok = saveItem($db, $_SESSION['resource_pk'], $updateItem);
                saveLineItem($resourceLink, $updateItem, empty($id));
                if (!empty($id) && $updateItem->visible && !$wasVisible) {
                    updateLineItemOutcomes($resourceLink, $updateItem);
                }
            }
            if ($ok) {
                $_SESSION['message'] = 'The item has been saved.';
                if (!$_SESSION['isContentItem'] && ($updateItem->visible != $wasVisible)) {
                    updateGradebook($db);
                }
            } else {
                $_SESSION['error_message'] = 'Unable to save the item; please check the data and try again.';
            }
            header('Location: ./');
            exit;
        }

// Process delete item action
    } else if ($action == 'delete') {
        $updateItem = getItem($db, $_SESSION['resource_pk'], $id);
        $wasVisible = $updateItem->visible;
        if (deleteItem($db, $_SESSION['resource_pk'], $id)) {
            $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
            $resourceLink = LTI\ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
            if ($resourceLink->hasLineItemService()) {
                $lineItems = $resourceLink->getLineItems(strval($id));
                if (!empty($lineItems)) {
                    $lineItems[0]->delete();
                }
            }
            $_SESSION['message'] = 'The item has been deleted.';
            if (!$_SESSION['isContentItem'] && $wasVisible) {
                updateGradebook($db);
            }
        } else {
            $_SESSION['error_message'] = 'Unable to delete the item; please try again.';
        }
        header('Location: ./');
        exit;

// Process content-item save action
    } else if ($action == 'saveci') {
        $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
        $platform = LTI\Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
// Pass on preference for overlay, popup, iframe, frame options in that order if any of these is offered
        $placement = null;
        $documentTarget = '';
        if (in_array('overlay', $_SESSION['document_targets'])) {
            $documentTarget = 'overlay';
        } else if (in_array('popup', $_SESSION['document_targets'])) {
            $documentTarget = 'popup';
        } else if (in_array('iframe', $_SESSION['document_targets'])) {
            $documentTarget = 'iframe';
        } else if (in_array('frame', $_SESSION['document_targets'])) {
            $documentTarget = 'frame';
        }
        if (!empty($documentTarget)) {
            $placement = new Content\Placement($documentTarget);
        }
        $item = new Content\LtiLinkItem($placement);
        $item->setMediaType(Content\Item::LTI_LINK_MEDIA_TYPE);
        $item->setTitle($_SESSION['title']);
        $item->setText($_SESSION['text']);
        $item->setIcon(new Content\Image(getAppUrl() . 'images/icon50.png', 50, 50));
        $item->addCustom('content_item_id', $_SESSION['resource_id']);
        if (strpos($platform->consumerVersion, 'canvas') === 0) {
            $item->setUrl(getAppUrl() . 'connect.php');
        }
        $formParams['content_items'] = Content\Item::toJson($item, $_SESSION['lti_version']);
        if (!is_null($_SESSION['data'])) {
            $formParams['data'] = $_SESSION['data'];
        }
        LTI\Tool::$defaultTool->platform = $platform;
        $formParams = LTI\Tool::$defaultTool->signParameters($_SESSION['return_url'], 'ContentItemSelection',
            $_SESSION['lti_version'], $formParams);
        $page = LTI\Util::sendForm($_SESSION['return_url'], $formParams);
        echo $page;
        exit;

// Process content-item cancel action
    } else if ($action == 'cancelci') {

        deleteAllItems($db, $_SESSION['resource_pk']);

        $formParams = array();
        if (!is_null($_SESSION['data'])) {
            $formParams['data'] = $_SESSION['data'];
        }
        $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
        LTI\Tool::$defaultTool->platform = LTI\Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
        $formParams = LTI\Tool::$defaultTool->signParameters($_SESSION['return_url'], 'ContentItemSelection',
            $_SESSION['lti_version'], $formParams);
        $page = LTI\Util::sendForm($_SESSION['return_url'], $formParams);
        echo $page;
        exit;

// Process reorder item action
    } else if (($action == 'reorder') && (isset($_GET['seq']))) {
        if (reorderItem($db, $_SESSION['resource_pk'], intval($_GET['id']), intval($_GET['seq']))) {
            $_SESSION['message'] = 'The item has been moved.';
        } else {
            $_SESSION['error_message'] = 'Unable to move the item; please try again.';
        }
        header('Location: ./');
        exit;
    } else if (isset($_POST['userlist'])) {
        $userList = true;
    }

// Initialise an empty item instance
    $updateItem = new Item();

// Fetch a list of existing items for the resource link
    if (isset($_SESSION['resource_pk'])) {
        $items = getItems($db, $_SESSION['resource_pk']);
    } else {
        $items = array();
    }

    if ($_SESSION['isStudent']) {
// Fetch a list of ratings for items for the resource link for the student
        $userRated = getUserRated($db, $_SESSION['resource_pk'], $_SESSION['user_pk']);
    }
}

// Page header
$title = APP_NAME;
$page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-language" content="EN" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>{$title}</title>
  <link href="css/rateit.css" media="screen" rel="stylesheet" type="text/css" />
  <script src="js/jquery-3.3.1.min.js" type="text/javascript"></script>
  <script src="js/jquery.rateit.min.js" type="text/javascript"></script>
  <script src="js/rating.js" type="text/javascript"></script>
  <link href="css/rating.css" media="screen" rel="stylesheet" type="text/css" />
  <script type="text/javascript">
//<![CDATA[
function doContentItem(todo) {
  var el = document.getElementById('id_do');
  el.value = todo;
  return true;
}

function doOnLoad() {
  $('.rateit').bind('over', function (event, value) {
    $(this).attr('title', value);
  });

  $('.rateit').bind('rated reset', function (event) {
    var ri = $(this);

    var value = ri.rateit('value');
    var id = ri.data('id');

    $.ajax({
      url: 'rating.php',
      data: { id: id, value: value },
      dataType: 'json',
      type: 'POST',
      success: function (data) {
        if (data.response == 'Success') {
          ri.rateit('readonly', true);
          alert('Your rating has been saved.');
        } else {
          ri.rateit('value', 0);
          alert('Unable to save your rating; please try again.');
        }
      },
      error: function (jxhr, msg, err) {
        ri.rateit('value', 0);
        alert('Unable to save your rating; please try again.');
      }
    });
  });
}

window.onload=doOnLoad;
//]]>
  </script>
</head>

<body>

EOD;

// Check for any messages to be displayed
if (isset($_SESSION['error_message'])) {
    $page .= <<< EOD
<p style="font-weight: bold; color: #f00;">ERROR: {$_SESSION['error_message']}</p>

EOD;
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['message'])) {
    $page .= <<< EOD
<p style="font-weight: bold; color: #00f;">{$_SESSION['message']}</p>

EOD;
    unset($_SESSION['message']);
}

// Display table of existing platform records
if ($ok) {

    if (count($items) <= 0) {
        $page .= <<< EOD
<p>No items have been added yet.</p>

EOD;
    } else {
        $page .= <<< EOD
  <table class="items" border="0" cellpadding="3">
  <tbody>

EOD;
        $row = 0;
        foreach ($items as $item) {
            if (!$_SESSION['isStudent'] || $item->visible) {
                $row++;
                if (!empty($id) && ($id == $item->item_pk)) {
                    $updateItem = $item;
                }
                if (!$item->visible) {
                    $trClass = 'notvisible';
                    $row--;
                } else if (($row % 2) == 1) {
                    $trClass = 'oddrow';
                } else {
                    $trClass = 'evenrow';
                }
                if (isset($item->item_url)) {
                    $title = '<a href="' . $item->item_url . '" target="_blank">' . $item->item_title . '</a>';
                } else {
                    $title = $item->item_title;
                }
                if (!$item->visible) {
                    $title .= ' [hidden]';
                }
                if (!empty($item->item_text)) {
                    $text = "<br />\n{$item->item_text}";
                } else {
                    $text = '';
                }
                $step = 1.0 / $item->step;
                $value = '0';
                $readonly = 'true';
                if ($_SESSION['isStudent'] && !array_key_exists(strval($item->item_pk), $userRated)) {
                    $readonly = 'false';
                } else if ($item->num_ratings > 0) {
                    $value = floatToStr($item->tot_ratings / $item->num_ratings);
                }
                $page .= <<< EOD
    <tr class="{$trClass}">
      <td><span class="title">{$title}</span>{$text}</td>
      <td><div data-id="{$item->item_pk}" title="{$value}" class="rateit" data-rateit-min="0" data-rateit-max="{$item->max_rating}" data-rateit-step="{$step}" data-rateit-value="{$value}" data-rateit-readonly="{$readonly}" data-rateit-mode="font"></div></td>

EOD;
                if (!$_SESSION['isStudent']) {
                    $page .= <<< EOD
      <td class="aligncentre">
        <select name="seq{$item->item_pk}" onchange="location.href='./?do=reorder&amp;id={$item->item_pk}&amp;seq='+this.value;" class="alignright">

EOD;
                    for ($i = 1; $i <= count($items); $i++) {
                        if ($i == $item->sequence) {
                            $sel = ' selected="selected"';
                        } else {
                            $sel = '';
                        }
                        $page .= <<< EOD
          <option value="{$i}"{$sel}>{$i}</option>

EOD;
                    }
                    $page .= <<< EOD
        </select>
      </td>
      <td class="iconcolumn aligncentre">
        <a href="./?id={$item->item_pk}"><img src="images/edit.png" title="Edit item" alt="Edit item" /></a>&nbsp;<a href="./?do=delete&amp;id={$item->item_pk}" onclick="return confirm('Delete item; are you sure?');"><img src="images/delete.png" title="Delete item" alt="Delete item" /></a>
      </td>

EOD;
                }
                $page .= <<< EOD
    </tr>

EOD;
            }
        }
        $page .= <<< EOD
  </tbody>
  </table>

EOD;
    }
}

// Display form for adding/editing an item
if ($ok && !$_SESSION['isStudent'] && ($_SESSION['isContentItem'] || ($_SESSION['resource_pk'] === $_SESSION['user_resource_pk']))) {
    if (isset($updateItem->item_pk)) {
        $mode = 'Update';
    } else {
        $mode = 'Add new';
    }
    $title = htmlentities($updateItem->item_title);
    $url = htmlentities($updateItem->item_url);
    $text = htmlentities($updateItem->item_text);
    if ($updateItem->visible) {
        $checked = ' checked="checked"';
    } else {
        $checked = '';
    }
    $page .= <<< EOD

  <h2>{$mode} item</h2>

  <form action="./" method="get">
    <div class="sharebox">
      <strong>New share key</strong><br /><br />
      Life:&nbsp;<select id="life">
        <option value="1">1 hour</option>
        <option value="2">2 hours</option>
        <option value="12">12 hours</option>
        <option value="24">1 day</option>
        <option value="48">2 days</option>
        <option value="72" selected="selected">3 days</option>
        <option value="96">4 days</option>
        <option value="120">5 days</option>
        <option value="168">1 week</option>
      </select><br />
      Auto approve?&nbsp;<input type="checkbox" id="auto_approve" value="yes" /><br /><br />
      <input type="button" value="Generate" onclick="return doGenerateKey();" />
    </div>
  </form>

  <form action="./" method="post">
    <div class="box">
      <span class="label">Title:<span class="required" title="required">*</span></span>&nbsp;<input name="title" type="text" size="50" maxlength="200" value="{$title}" /><br />
      <span class="label">URL:</span>&nbsp;<input name="url" type="text" size="75" maxlength="200" value="{$url}" /><br />
      <span class="label">Description:</span>&nbsp;<textarea name="text" rows="3" cols="60">{$text}</textarea><br />
      <span class="label">Visible?</span>&nbsp;<input name="visible" type="checkbox" value="1"{$checked} /><br />
      <span class="label">Maximum rating:<span class="required" title="required">*</span></span>&nbsp;<select name="max_rating">

EOD;
    for ($i = 3; $i <= 10; $i++) {
        if ($i == $updateItem->max_rating) {
            $sel = ' selected="selected"';
        } else {
            $sel = '';
        }
        $page .= <<< EOD
        <option value="{$i}"{$sel}>{$i}</option>

EOD;
    }
    $sel1 = '';
    $sel2 = '';
    $sel4 = '';
    if ($updateItem->step == 1) {
        $sel1 = ' selected="selected"';
    }
    if ($updateItem->step == 2) {
        $sel2 = ' selected="selected"';
    }
    if ($updateItem->step == 4) {
        $sel4 = ' selected="selected"';
    }
    $page .= <<< EOD
      </select><br />
      <span class="label">Rating step:<span class="required" title="required">*</span></span>&nbsp;<select name="step">
        <option value="4"{$sel4}>0.25</option>
        <option value="2"{$sel2}>0.5</option>
        <option value="1"{$sel1}>1</option>
      </select><br />
      <br />
      <input type="hidden" name="do" id="id_do" value="add" />
      <input type="hidden" name="id" value="{$id}" />
      <span class="label"><span class="required" title="required">*</span>&nbsp;=&nbsp;required field</span>&nbsp;<input type="submit" value="{$mode} item" />

EOD;

    if (isset($updateItem->item_pk)) {
        $page .= <<< EOD
      &nbsp;<input type="reset" value="Cancel" onclick="location.href='./';" />

EOD;
    }
    $page .= <<< EOD
    </div>

EOD;
    if ($_SESSION['isContentItem'] && !isset($updateItem->item_pk)) {
        $disabled = '';
        if (count($items) <= 0) {
            $disabled = ' disabled="disabled"';
        }
        $page .= <<< EOD
      <p class="clear">
        <br />
        <input type="submit" value="Cancel content" onclick="return doContentItem('cancelci');" />
        <input type="submit" value="Create content item" onclick="return doContentItem('saveci');"{$disabled} />
      </p>

EOD;
    }
    $page .= <<< EOD
  </form>

EOD;

    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
    $shares = $resourceLink->getShares();
    if (count($shares) > 0) {
        $page .= <<< EOD

  <h2 class="clear">Shares</h2>

  <table class="shares" border="0" cellpadding="3">
  <thead>
    <tr>
      <th>Source</th>
      <th>Title</th>
      <th>Approved</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>

EOD;
        $i = 0;
        foreach ($shares as $share) {
            $i++;
            if ($share->approved) {
                $shareApproved = 'tick';
                $shareApproved_alt = 'Approved';
                $action = 'Suspend';
            } else {
                $shareApproved = 'cross';
                $shareApproved_alt = 'Not approved';
                $action = 'Approve';
            }
            $page .= <<< EOD
  <tr>
    <td>{$share->consumerName}</td>
    <td>{$share->title}</td>
    <td class="aligncentre"><img id="img{$i}" src="images/{$shareApproved}.gif" alt="{$shareApproved_alt}" title="{$shareApproved_alt}" /></td>
    <td class="aligncentre">
      <input type="button" id="btn{$i}" value="{$action}" onclick="return doAction({$i}, '{$action}', {$share->resourceLinkId});" />
      <a href="share.php?do=cancel&rlid={$share->resourceLinkId}"><input type="button" value="Cancel" onclick="return confirm('Cancel share; are you sure?');" /></a>
    </td>
  </tr>

EOD;
        }
        $page .= <<< EOD
  </tbody>
  </table>

EOD;
    }

    $page .= <<< EOD
  <div class="clear" style="margin-left: 10px;">

EOD;
    if ($resourceLink->hasMembershipsService()) {
        if ($userList) {
            $members = $resourceLink->getMemberships(true);
            $page .= <<< EOD
    <form action="./" method="post">
      <input type="submit" name="userlist" value="Refresh user list" />
    </form>

    <h2>Users</h2>

    <table class="users" border="0" cellpadding="3">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Learner?</th>
        <th>Ratings</th>
      </tr>
    </thead>
    <tbody>

EOD;
            $users = array();
            if (!empty($members)) {
                foreach ($members as $member) {
                    $users["{$member->lastname}, {$member->firstname}"] = $member;
                }
                ksort($users);
                foreach ($users as $name => $user) {
                    if ($user->isLearner()) {
                        $img = 'tick.gif';
                        $ratings = count(getUserRated($db, $_SESSION['resource_pk'], $user->getRecordId()));
                    } else {
                        $img = 'cross.gif';
                        $ratings = 'NA';
                    }
                    $page .= <<< EOD
       <tr>
         <td>{$user->ltiUserId}</td>
         <td>{$name}</td>
         <td class="aligncentre"><img src="images/{$img}" /></td>
         <td class="aligncentre">{$ratings}</td>
       </tr>

EOD;
                }
            }
            $page .= <<< EOD
    </tbody>
    </table>

EOD;
            if (!empty($resourceLink->groupSets)) {
                $page .= <<< EOD
    <h2>Group sets</h2>

    <table class="users" border="0" cellpadding="3">
    <thead>
      <tr>
        <th>Name</th>
        <th>Groups</th>
      </tr>
    </thead>
    <tbody>

EOD;
                $groupSets = array();
                foreach ($resourceLink->groupSets as $groupSetId => $groupSet) {
                    $groupSets[$groupSet['title']] = $groupSet;
                }
                ksort($groupSets);
                foreach ($groupSets as $title => $groupSet) {
                    $page .= <<< EOD
      <tr>
        <td>{$title}</td>
        <td>

EOD;
                    foreach ($groupSet['groups'] as $groupId) {
                        $page .= <<< EOD
           {$resourceLink->groups[$groupId]['title']}<br />

EOD;
                    }
                    $page .= <<< EOD
        </td>
      </tr>

EOD;
                }
                $page .= <<< EOD
    </tbody>
    </table>

EOD;
            } else {
                $page .= <<< EOD
    <p>
      Your course does not appear to offer the ability to access a list of groups, or there is none to be accessed.
    </p>

EOD;
            }
        } else {
            $page .= <<< EOD
    <form action="./" method="post">
      Your course appears to offer the ability to access a list of users. <input type="submit" id="id_members" name="userlist" value="Show user list" />
    </form>

EOD;
        }
    } else if (!$_SESSION['isContentItem']) {
        $page .= <<< EOD
    Your course does not appear to offer the ability to access a list of users.

EOD;
    }
    $page .= <<< EOD
  </div>

EOD;
}

// Page footer
$page .= <<< EOD
</body>
</html>

EOD;

// Display page
echo $page;
?>
