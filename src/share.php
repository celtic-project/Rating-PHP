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
 * @version   4.0.0
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
    $data_connector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    if (($action == 'generate') && !empty($life)) {
        $resource_link = ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);
        $auto_approve = false;
        if (isset($_REQUEST['auto_approve'])) {
            $auto_approve = $_REQUEST['auto_approve'] === 'yes';
        }
        $length = intval($resource_link->getSetting('custom_share_key_length'));
        if (empty($length)) {
            $length = 6;
        }
        $share_key = new ResourceLinkShareKey($resource_link);
        $share_key->length = $length;
        $share_key->life = $life;
        $share_key->autoApprove = $auto_approve;
        $ok = $share_key->save();
        $response = $share_key->getId();
    } else if ((($action == 'approve') || ($action == 'suspend')) && !empty($rlid)) {
        $resource_link = ResourceLink::fromRecordId($rlid, $data_connector);
        $resource_link->shareApproved = ($action === 'approve');
        $ok = $resource_link->save();
    } else if (($action == 'cancel') && !empty($rlid)) {
        $resource_link = ResourceLink::fromRecordId($rlid, $data_connector);
        $resource_link->primaryResourceLinkId = null;
        $resource_link->shareApproved = null;
        $resource_link->save();
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
