<?php

use ceLTIc\LTI\DataConnector;

/**
 * This page processes a registration request.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('rating_tp.php');

// Initialise session and database
$db = null;
if (init($db)) {
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $tool = new RatingTool($dataConnector);
    $tool->doRegistration();
    $ok = $tool->ok;
    $message = $tool->reason;
} else {
    $ok = false;
    $message = 'Unable to connect to database.';
}

$response = array();
$response['ok'] = $ok;
$response['message'] = $message;

header('Content-type: application/json');
echo json_encode($response);
?>
