<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\UserResult;

/**
 * This page displays information passed to the LTI tool from the platform.
 *
 * @author  Kyle Tuck <kylejtuck@gmail.com>
 * @copyright  Kyle Tuck
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

// Initialise session and database
$db = null;
$ok = init($db, true);
// Initialise parameters
$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
$resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
$userResult = UserResult::fromResourceLink($resourceLink, $_SESSION['ltiUserId']);

$showVal = function($val) {
    return $val;
};

$page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-language" content="EN" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>{$showVal(APP_NAME)}</title>
</head>
<body>
EOD;


if ($ok) {
	$membership = "<h2>Memberships Details for Course</h2>\n";
	if ($resourceLink->hasMembershipsService()) {
 		$membership .= "<p>Resource Link has <strong>memberships</strong> service.</p>\n";
		$members = $resourceLink->getMemberships(true);
		$membership .= "<h3>Members</h3>\n";
		$membership .= "<pre>";
		foreach ($members as $member) {
			if ($member->ltiUserId == $_SESSION['ltiUserId']) $userResult = $member;
//			$membership .= json_encode($member, JSON_PRETTY_PRINT);
			$membership .= $member->lastname . ", " . $member->firstname . ": ";
			$membership .= $member->isLearner()?"Student\n":"Instructor\n";
		}
		$membership .= "</pre>\n";
	}
	$page .= "<h2>User Details</h2>\n";
 	$page .= "<pre>\n" . json_encode($userResult, JSON_PRETTY_PRINT) . "</pre>\n";

	$otherDetails = "<h2>Other Details</h2>\n";
	$rlSettings = $resourceLink->getSettings();
	foreach ($rlSettings as $setting => $val) {
		$otherDetails .= "<p>" . $setting . ": " . $val . "</p>\n";
	}
	$page .= $otherDetails;
	$page .= $membership;
} else {
	$page .= <<< EOD
	<p style="font-weight: bold; color: #f00;">There was an error initializing the LTI application.</p>
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
}
$page .= <<< EOD
</body>
</html>
EOD;

// Display page
echo $page;
?>
