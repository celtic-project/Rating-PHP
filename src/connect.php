<?php

use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;

/**
 * This page processes a launch request from an LTI platform.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('MyTool.php');

// Cancel any existing session
session_name(SESSION_NAME);
session_start();
$_SESSION = array();
session_destroy();

// Initialise database
$db = null;
if (init($db)) {
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
	// If the consumer (platform) was auto-registered, there may not be a deployment_id. This will add it
	//  if the platform exists, is enabled, and is not protected.
	if (isset($_POST['iss']) && isset($_POST['client_id']) && isset($_POST['deployment_id'])) {
		$platformCheck = new Platform($dataConnector);
		$platformCheck->platformId = $_POST['iss'];
		$platformCheck->clientId = $_POST['client_id'];
		$platformCheck->deploymentId = $_POST['deployment_id'];
		if (!$dataConnector->loadPlatform($platformCheck)) {
			$platformCheck->deploymentId = null;
			if ($dataConnector->loadPlatform($platformCheck)) {
				$platformCheck = Platform::fromPlatformId($_POST['iss'], $_POST['client_id'], null, $dataConnector);
				if ($platformCheck->enabled && !$platformCheck->protected) {
					$platformCopy = new Platform($dataConnector);
					$platformCopy->ltiVersion = $platformCheck->ltiVersion;
					$platformCopy->name = $platformCheck->name;
					$platformCopy->secret = $platformCheck->secret;
					$platformCopy->platformId = $platformCheck->platformId;
					$platformCopy->clientId = $platformCheck->clientId;
					$platformCopy->deploymentId = $_POST['deployment_id'];
					$platformCopy->authorizationServerId = $platformCheck->authorizationServerId;
					$platformCopy->authenticationUrl = $platformCheck->authenticationUrl;
					$platformCopy->accessTokenUrl = $platformCheck->accessTokenUrl;
					$platformCopy->rsaKey = $platformCheck->rsaKey;
					$platformCopy->jku = $platformCheck->jku;
					$platformCopy->enabled = $platformCheck->enabled;
					$platformCopy->enableFrom = $platformCheck->enableFrom;
					$platformCopy->enableUntil = $platformCheck->enableUntil;
					$platformCopy->protected = $platformCheck->protected;
					// other stuff to copy
					$platformSettings = $platformCheck->getSettings();
					foreach ($platformSettings as $prop => $value) {
						if (strpos($prop, 'custom_') !== 0) $platformCopy->setSetting($prop, $value);
					}					
					$platformCopy->save();
				}
			}
		}
	}
	$tool = new MyTool($dataConnector);
	$tool->setParameterConstraint('resource_link_id', true, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('user_id', true, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('roles', true, null, array('basic-lti-launch-request'));
} else {
	$tool = new MyTool(null);
	$tool->reason = $_SESSION['error_message'];
}
$tool->handleRequest();
?>