<?php

use ceLTIc\LTI\DataConnector;

/**
 * This page processes a launch request from an LTI tool consumer.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @version   3.2.0
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('rating_tp.php');

// Cancel any existing session
session_name(SESSION_NAME);
session_start();
$_SESSION = array();
session_destroy();

// Initialise database
$db = NULL;
if (init($db)) {
    $data_connector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $tool = new RatingToolProvider($data_connector);
    $tool->setParameterConstraint('resource_link_id', TRUE, 50, array('basic-lti-launch-request'));
    $tool->setParameterConstraint('user_id', TRUE, 50, array('basic-lti-launch-request'));
    $tool->setParameterConstraint('roles', TRUE, NULL, array('basic-lti-launch-request'));
} else {
    $tool = new RatingToolProvider(NULL);
    $tool->reason = $_SESSION['error_message'];
}
$tool->handleRequest();
?>
