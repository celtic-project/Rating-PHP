<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\ResourceLinkShareKey;

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

$response = '';
if ($ok) {
// Initialise parameters
    $action = '';
// Check for item id and action parameters
    $action = '';
    if (isset($_REQUEST['do'])) {
        $action = strtolower($_REQUEST['do']);
    }
    $life = 0;
    if (isset($_REQUEST['life'])) {
        $life = intval($_REQUEST['life']);
    }
    $rlid = 0;
    if (isset($_REQUEST['rlid'])) {
        $rlid = intval($_REQUEST['rlid']);
    }
// Process share action
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    if (($action == 'generate') && !empty($life)) {
        $resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
        $autoApprove = false;
        if (isset($_REQUEST['auto_approve'])) {
            $autoApprove = $_REQUEST['auto_approve'] === 'yes';
        }
        $length = intval($resourceLink->getSetting('custom_share_key_length'));
        if (empty($length)) {
            $length = 6;
        }
        $shareKey = new ResourceLinkShareKey($resourceLink);
        $shareKey->length = $length;
        $shareKey->life = $life;
        $shareKey->autoApprove = $autoApprove;
        $ok = $shareKey->save();
        $response = $shareKey->getId();
    } else if ((($action == 'approve') || ($action == 'suspend')) && !empty($rlid)) {
        $resourceLink = ResourceLink::fromRecordId($rlid, $dataConnector);
        $resourceLink->shareApproved = ($action === 'approve');
        $ok = $resourceLink->save();
    } else if (($action == 'cancel') && !empty($rlid)) {
        $resourceLink = ResourceLink::fromRecordId($rlid, $dataConnector);
        $resourceLink->primaryResourceLinkId = null;
        $resourceLink->shareApproved = null;
        $resourceLink->save();
        header('Location: index.php');
        exit;
    }
}

if ($ok) {
    echo $response;
} else {
    header("Status: 404 Not Found", true, 404);
}
?>
