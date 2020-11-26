<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;

/**
 * This page displays a UI for registering the tool with an LTI platformr.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('rating_tp.php');

// Initialise session and database
$page = '';
$db = null;
if (init($db)) {

// Register
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $errorMsg = '';
        $url = $_SESSION['return_url'];
        if (strpos($url, '?') === false) {
            $sep = '?';
        } else {
            $sep = '&';
        }
        $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
        $tool = new RatingTool($dataConnector);
        $tool->platform = LTI\Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
        $do = $_POST['do'];
        if ($do == 'Register') {
            $ok = $tool->doToolProxyService($_SESSION['tc_profile_url']);
            if ($ok) {
                $guid = $tool->platform->getKey();
                header("Location: {$url}{$sep}lti_msg=The%20tool%20has%20been%20registered&status=success&tool_proxy_guid={$guid}");
                exit;
            } else {
                $errorMsg = 'Error setting tool proxy';
            }
        } else if ($do == 'Cancel') {
            $tool->platform->delete();
            header("Location: {$url}{$sep}lti_msg=The%20tool%20registration%20has%20been%20cancelled&status=failure");
            exit;
        }
    }

    $page .= <<< EOD
<form action="register.php" method="post">

EOD;
    if (!empty($errorMsg)) {
        $page .= <<< EOD
<p style="color: #f00; font-weight: bold;">
  {$errorMsg}
</p>
EOD;
    }
    $page .= <<< EOD
<div class="box">
  <p>
    Your system meets the minimum requirements for this tool.  Click the button below to complete the registration process.
  </p>
  <p>
    <input type="submit" name="do" value="Cancel" />
    &nbsp;&nbsp;&nbsp;<input type="submit" name="do" value="Register" />
  </p>
</div>
</form>

EOD;
}

$title = APP_NAME;
$page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$title}</title>
<link href="css/rating.css" media="screen" rel="stylesheet" type="text/css" />
</head>

<body>
<h1>Rating Application Registration</h1>
{$page}
</body>
</html>
EOD;

// Display page
echo $page;
?>
