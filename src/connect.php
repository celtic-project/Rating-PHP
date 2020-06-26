<?php

use ceLTIc\LTI\DataConnector;

/**
 * This page processes a launch request from an LTI platform.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @version   4.0.0
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('rating_tp.php');

// Cancel any existing session
session_name(SESSION_NAME);
session_start();
$_SESSION = array();
session_destroy();

// Initialise database
$db = null;
if (init($db)) {
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $tool = new RatingTool($dataConnector);
    $tool->setParameterConstraint('resource_link_id', true, 50, array('basic-lti-launch-request'));
    $tool->setParameterConstraint('user_id', true, 50, array('basic-lti-launch-request'));
    $tool->setParameterConstraint('roles', true, null, array('basic-lti-launch-request'));
} else {
    $tool = new RatingTool(null);
    $tool->reason = $_SESSION['error_message'];
}
$tool->handleRequest();
?>
