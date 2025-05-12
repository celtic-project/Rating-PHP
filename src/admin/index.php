<?php

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\DataConnector\DataConnector;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LtiVersion;

/**
 * This page manages the definition of LTI platform records.  A platform record is required to
 * enable each VLE to securely connect to this application.
 *
 * *** IMPORTANT ***
 * Access to this page should be restricted to prevent unauthorised access to the configuration of
 * platforms (for example, using an entry in an Apache .htaccess file); access to all other pages is
 * authorised by LTI.
 * ***           ***
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('../lib.php');

// Initialise session and database
$db = null;
$ok = init($db, false, 1);
// Initialise parameters
$id = null;
if ($ok) {
// Create LTI Tool instance
    $dataConnector = DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
    $tool = new Tool($dataConnector);
// Check for platform id and action parameters
    $action = '';
    if (isset($_REQUEST['id'])) {
        $id = intval($_REQUEST['id']);
    }
    if (isset($_REQUEST['do'])) {
        $action = $_REQUEST['do'];
    }

// Process add platform action
    if ($action == 'add') {
        if (!empty($id) || !empty($_POST['name'])) {
            if (empty($id)) {
                $updatePlatform = new Platform($dataConnector);
                $updatePlatform->ltiVersion = LtiVersion::V1;
            } else {
                $updatePlatform = Platform::fromRecordId($id, $dataConnector);
            }
            if (!empty($_POST['key'])) {
                $updatePlatform->setKey($_POST['key']);
            }
            if (!empty($_POST['name'])) {
                $updatePlatform->name = $_POST['name'];
            } else {
                $updatePlatform->name = null;
            }
            if (!empty($_POST['secret'])) {
                $updatePlatform->secret = $_POST['secret'];
            } else {
                $updatePlatform->secret = null;
            }
            if (!empty($_POST['platformid'])) {
                $updatePlatform->platformId = $_POST['platformid'];
            } else {
                $updatePlatform->platformId = null;
            }
            if (!empty($_POST['clientid'])) {
                $updatePlatform->clientId = $_POST['clientid'];
            } else {
                $updatePlatform->clientId = null;
            }
            if (!empty($_POST['deploymentid'])) {
                $updatePlatform->deploymentId = $_POST['deploymentid'];
            } else {
                $updatePlatform->deploymentId = null;
            }
            if (!empty($_POST['authorizationserverid'])) {
                $updatePlatform->authorizationServerId = $_POST['authorizationserverid'];
            } else {
                $updatePlatform->authorizationServerId = null;
            }
            if (!empty($_POST['authenticationurl'])) {
                $updatePlatform->authenticationUrl = $_POST['authenticationurl'];
            } else {
                $updatePlatform->authenticationUrl = null;
            }
            if (!empty($_POST['accesstokenurl'])) {
                $updatePlatform->accessTokenUrl = $_POST['accesstokenurl'];
            } else {
                $updatePlatform->accessTokenUrl = null;
            }
            if (!empty($_POST['publickey'])) {
                $updatePlatform->rsaKey = $_POST['publickey'];
            } else {
                $updatePlatform->rsaKey = null;
            }
            if (!empty($_POST['jku'])) {
                $updatePlatform->jku = $_POST['jku'];
            } else {
                $updatePlatform->jku = null;
            }
            if (!empty($_POST['ltiversion'])) {
                $updatePlatform->ltiVersion = LtiVersion::tryFrom($_POST['ltiversion']);
                if ($updatePlatform->ltiVersion === LtiVersion::V1P3) {
                    $updatePlatform->signatureMethod = 'RS256';
                }
            }
            $updatePlatform->enabled = isset($_POST['enabled']);
            $date = $_POST['enable_from'];
            if (empty($date)) {
                $updatePlatform->enableFrom = null;
            } else {
                $updatePlatform->enableFrom = strtotime($date);
            }
            $date = $_POST['enable_until'];
            if (empty($date)) {
                $updatePlatform->enableUntil = null;
            } else {
                $updatePlatform->enableUntil = strtotime($date);
            }
            $updatePlatform->protected = isset($_POST['protected']);
            $settings = $updatePlatform->getSettings();
            foreach ($settings as $prop => $value) {
                if (strpos($prop, 'custom_') !== 0) {
                    $updatePlatform->setSetting($prop);
                }
            }
            $properties = $_POST['properties'];
            $properties = str_replace("\r\n", "\n", $properties);
            $properties = explode("\n", $properties);
            foreach ($properties as $property) {
                if (strpos($property, '=') !== false) {
                    list($name, $value) = explode('=', $property, 2);
                    if ($name) {
                        $updatePlatform->setSetting($name, $value);
                    }
                }
            }
            $updatePlatform->debugMode = isset($_POST['debug']);
// Ensure all required fields have been provided
            if ($updatePlatform->save()) {
                $_SESSION['message'] = 'The platform has been saved.';
            } else {
                $_SESSION['error_message'] = 'Unable to save the platform; please check the data and try again.';
            }
        } else {
            $_SESSION['error_message'] = 'Please enter a name.';
        }
        header('Location: ./');
        exit;
// Process delete platform action
    } else if ($action == 'delete') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ok = true;
            foreach ($_POST['ids'] as $id) {
                $platform = Platform::fromRecordId($id, $dataConnector);
                $ok = $ok && $platform->delete();
            }
            if ($ok) {
                $_SESSION['message'] = 'The selected platforms have been deleted.';
            } else {
                $_SESSION['error_message'] = 'Unable to delete at least one of the selected platforms; please try again.';
            }
        } else {
            $platform = Platform::fromRecordId($id, $dataConnector);
            if ($platform->delete()) {
                $_SESSION['message'] = 'The platform has been deleted.';
            } else {
                $_SESSION['error_message'] = 'Unable to delete the platform; please try again.';
            }
        }
        header('Location: ./');
        exit;
    } else {
// Initialise an empty tool platform instance
        $updatePlatform = new Platform($dataConnector);
        $updatePlatform->secret = Util::getRandomString(32);
    }

// Fetch a list of existing tool platform records
    $platforms = $tool->getPlatforms();

// Set launch URL for information
    $launchUrl = getAppUrl(1) . 'connect.php';

// Set launch URL for information
    $jwksUrl = getAppUrl(1) . 'jwks.php';

// Set Canvas configure URL for information
    $configureUrl = getAppUrl(1) . 'configure.php';
}

$here = function($val) {
    return $val;
};

// Page header
$page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$here(APP_NAME)}: Manage platforms</title>
<link href="../css/rating.css?v={$here(APP_VERSION)}" media="screen" rel="stylesheet" type="text/css" />
<script type="text/javascript">
//<![CDATA[
var numSelected = 0;
function toggleSelect(el) {
  if (el.checked) {
    numSelected++;
  } else {
    numSelected--;
  }
  document.getElementById('delsel').disabled = (numSelected <= 0);
}

function onVersionChange(el) {
  if (el.selectedIndex <= 0) {
    displayv1 = 'block';
    displayv1p3 = 'none';
  } else {
    displayv1 = 'none';
    displayv1p3 = 'block';
  }
  document.getElementById('id_key').style.display = displayv1;
  document.getElementById('id_secret').style.display = displayv1;
  document.getElementById('id_platformid').style.display = displayv1p3;
  document.getElementById('id_clientid').style.display = displayv1p3;
  document.getElementById('id_deploymentid').style.display = displayv1p3;
  document.getElementById('id_authorizationserverid').style.display = displayv1p3;
  document.getElementById('id_authenticationurl').style.display = displayv1p3;
  document.getElementById('id_accesstokenurl').style.display = displayv1p3;
  document.getElementById('id_publickey').style.display = displayv1p3;
  document.getElementById('id_jku').style.display = displayv1p3;
}

function doOnLoad() {
  onVersionChange(document.getElementById('id_ltiversion'));
}

window.onload=doOnLoad;
//]]>
</script>
</head>

<body>
<h1>{$here(APP_NAME)}: Manage platforms</h1>

EOD;

// Display warning message if access does not appear to have been restricted
if (!(isset($_SERVER['AUTH_TYPE']) && isset($_SERVER['REMOTE_USER']) && isset($_SERVER['PHP_AUTH_PW']))) {
    $page .= <<< EOD
<p><strong>*** WARNING *** Access to this page should be restricted to application administrators only.</strong></p>

EOD;
}

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

    if (count($platforms) <= 0) {
        $page .= <<< EOD
<p>No platforms have been added yet.</p>

EOD;
    } else {
        $page .= <<< EOD
<form action="./?do=delete" method="post" onsubmit="return confirm('Delete selected platforms; are you sure?');">
<table class="items" border="1" cellpadding="3">
<thead>
  <tr>
    <th>&nbsp;</th>
    <th>Name</th>
    <th>Key</th>
    <th>Platform ID</th>
    <th>Client ID</th>
    <th>Deployment ID</th>
    <th>Version</th>
    <th>Available?</th>
    <th>Protected?</th>
    <th>Debug?</th>
    <th>Last access</th>
    <th>Options</th>
  </tr>
</thead>
<tbody>

EOD;
        foreach ($platforms as $platform) {
            $trid = urlencode($platform->getRecordId());
            if ($platform->getRecordId() === $id) {
                $updatePlatform = $platform;
            }
            if (!$platform->getIsAvailable()) {
                $available = 'cross';
                $availableAlt = 'Not available';
                $trclass = 'notvisible';
            } else {
                $available = 'tick';
                $availableAlt = 'Available';
                $trclass = '';
            }
            if ($platform->protected) {
                $protected = 'tick';
                $protectedAlt = 'Protected';
            } else {
                $protected = 'cross';
                $protectedAlt = 'Not protected';
            }
            if ($platform->debugMode) {
                $debug = 'tick';
                $debugAlt = 'Enabled';
            } else {
                $debug = 'cross';
                $debugAlt = 'Disabled';
            }
            if (is_null($platform->lastAccess)) {
                $last = 'None';
            } else {
                $last = date('j-M-Y', $platform->lastAccess);
            }
            $page .= <<< EOD
  <tr class="{$trclass}">
    <td><input type="checkbox" name="ids[]" value="{$trid}" onclick="toggleSelect(this);" /></td>
    <td>{$platform->name}</td>
    <td>{$platform->getKey()}</td>
    <td>{$platform->platformId}</td>
    <td>{$platform->clientId}</td>
    <td>{$platform->deploymentId}</td>
    <td><span title="{$platform->consumerGuid}">{$platform->consumerVersion}</span></td>
    <td class="aligncentre"><img src="../images/{$available}.gif" alt="{$availableAlt}" title="{$availableAlt}" /></td>
    <td class="aligncentre"><img src="../images/{$protected}.gif" alt="{$protectedAlt}" title="{$protectedAlt}" /></td>
    <td class="aligncentre"><img src="../images/{$debug}.gif" alt="{$debugAlt}" title="{$debugAlt}" /></td>
    <td>{$last}</td>
    <td class="iconcolumn aligncentre">
      <a href="./?id={$trid}#edit"><img src="../images/edit.png" title="Edit platform" alt="Edit platform" /></a>&nbsp;<a href="./?do=delete&amp;id={$trid}" onclick="return confirm('Delete platform; are you sure?');"><img src="../images/delete.png" title="Delete platform" alt="Delete platform" /></a>
    </td>
  </tr>

EOD;
        }
        $page .= <<< EOD
</tbody>
</table>
<p>
<input type="submit" value="Delete selected tool platforms" id="delsel" disabled="disabled" />
</p>
</form>

EOD;
    }

// Display form for adding/editing a platform
    $update = '';
    $lti2 = '';
    if (!isset($updatePlatform->created)) {
        $mode = 'Add new';
    } else {
        $mode = 'Update';
        $update = ' disabled="disabled"';
        if ($updatePlatform->ltiVersion === LtiVersion::V2) {
            $lti2 = ' disabled="disabled"';
        }
    }
    $name = ratingHtmlEntities($updatePlatform->name);
    $key = ratingHtmlEntities($updatePlatform->getKey());
    $platformId = ratingHtmlEntities($updatePlatform->platformId);
    $clientId = ratingHtmlEntities($updatePlatform->clientId);
    $deploymentId = ratingHtmlEntities($updatePlatform->deploymentId);
    $authorizationServerId = ratingHtmlEntities($updatePlatform->authorizationServerId);
    $authenticationUrl = ratingHtmlEntities($updatePlatform->authenticationUrl);
    $accessTokenUrl = ratingHtmlEntities($updatePlatform->accessTokenUrl);
    $publicKey = ratingHtmlEntities($updatePlatform->rsaKey);
    $jku = ratingHtmlEntities($updatePlatform->jku);
    $secret = ratingHtmlEntities($updatePlatform->secret);
    if ($updatePlatform->enabled) {
        $enabled = ' checked="checked"';
    } else {
        $enabled = '';
    }
    $enableFrom = '';
    if (!is_null($updatePlatform->enableFrom)) {
        $enableFrom = date('j-M-Y H:i', $updatePlatform->enableFrom);
    }
    $enableUntil = '';
    if (!is_null($updatePlatform->enableUntil)) {
        $enableUntil = date('j-M-Y H:i', $updatePlatform->enableUntil);
    }
    if ($updatePlatform->protected) {
        $protected = ' checked="checked"';
    } else {
        $protected = '';
    }
    $properties = '';
    $settings = $updatePlatform->getSettings();
    foreach ($settings as $prop => $value) {
        if (strpos($prop, 'custom_') !== 0) {
            $properties .= "{$prop}={$value}\n";
        }
    }
    if ($updatePlatform->debugMode) {
        $debug = ' checked="checked"';
    } else {
        $debug = '';
    }
    $v1 = LtiVersion::V1->value;
    $v1p3 = LtiVersion::V1P3->value;
    $v1Selected = ' selected';
    $v1p3Selected = '';
    if ($updatePlatform->ltiVersion === LtiVersion::V1P3) {
        $v1Selected = '';
        $v1p3Selected = ' selected';
    }

    $page .= <<< EOD
<h2><a name="edit">{$mode} platform</a></h2>

<form action="./" method="post">
<div class="box">
  <span class="label">LTI version:</span>&nbsp;<select name="ltiversion" id="id_ltiversion" onchange="onVersionChange(this);">
    <option value="{$v1}"{$v1Selected}>1.0/1.1/1.2/2.0</option>
    <option value="{$v1p3}"{$v1p3Selected}>1.3</option>
  </select><br />
  <br />
  <span class="label">Name:<span class="required" title="required">*</span></span>&nbsp;<input name="name" type="text" size="50" maxlength="50" value="{$name}" /><br /><br />
  <span id="id_key"><span class="label">Key:</span>&nbsp;<input name="key" type="text" size="75" maxlength="50" value="{$key}"{$update} /><br /></span>
  <span id="id_secret"><span class="label">Secret:</span>&nbsp;<input name="secret" type="text" size="75" maxlength="200" value="{$secret}"{$lti2} /><br /></span>
  <span id="id_platformid"><span class="label">Platform ID:</span>&nbsp;<input name="platformid" type="text" size="75" maxlength="255" value="{$platformId}" /><br /></span>
  <span id="id_clientid"><span class="label">Client ID:</span>&nbsp;<input name="clientid" type="text" size="75" maxlength="255" value="{$clientId}" /><br /></span>
  <span id="id_deploymentid"><span class="label">Deployment ID:</span>&nbsp;<input name="deploymentid" type="text" size="75" maxlength="255" value="{$deploymentId}" /><br /></span>
  <span id="id_authorizationserverid"><span class="label">Authorization server ID:</span>&nbsp;<input name="authorizationserverid" type="text" size="75" maxlength="255" value="{$authorizationServerId}" /><br /></span>
  <span id="id_authenticationurl"><span class="label">Authentication request URL:</span>&nbsp;<input name="authenticationurl" type="text" size="75" maxlength="255" value="{$authenticationUrl}" /><br /></span>
  <span id="id_accesstokenurl"><span class="label">Access token URL:</span>&nbsp;<input name="accesstokenurl" type="text" size="75" maxlength="255" value="{$accessTokenUrl}" /><br /></span>
  <span id="id_publickey"><span class="label">Public key:</span>&nbsp;<textarea name="publickey" rows="9" cols="65" placeholder="-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----">{$publicKey}</textarea><br /></span>
  <span id="id_jku"><span class="label">JSON webkey URL (jku):</span>&nbsp;<input name="jku" type="text" size="75" maxlength="255" value="{$jku}" /><br /></span>
  <br />
  <span class="label">Enabled?</span>&nbsp;<input name="enabled" type="checkbox" value="1"{$enabled} /><br />
  <span class="label">Enable from:</span>&nbsp;<input name="enable_from" type="text" size="50" maxlength="200" value="{$enableFrom}" /><br />
  <span class="label">Enable until:</span>&nbsp;<input name="enable_until" type="text" size="50" maxlength="200" value="{$enableUntil}" /><br />
  <span class="label">Protected?</span>&nbsp;<input name="protected" type="checkbox" value="1"{$protected} /><br />
  <span id="id_properties"><span class="label">Properties:</span>&nbsp;<textarea name="properties" rows="3" cols="65">{$properties}</textarea><br /></span>
  <span class="label">Debug mode?</span>&nbsp;<input name="debug" type="checkbox" value="1"{$debug} /><br />
  <br />
  <input type="hidden" name="do" value="add" />
  <input type="hidden" name="id" value="{$id}" />
  <span class="label"><span class="required" title="required">*</span>&nbsp;=&nbsp;required field</span>&nbsp;<input type="submit" value="{$mode} platform" />

EOD;

    if (isset($updatePlatform->created)) {
        $page .= <<< EOD
  &nbsp;<input type="reset" value="Cancel" onclick="location.href='./';" />

EOD;
    }
    $page .= <<< EOD
</div>
<ul class="clear">
  <li><em>Launch URL, initiate login URL, redirection URI, registration URL:</em> {$launchUrl}</li>
  <li><em>Public keyset URL:</em> {$jwksUrl}</li>
  <li><em>Canvas configuration URLs:</em> {$configureUrl} (XML) and {$configureUrl}?json (JSON)</li>
</ul>
</form>

EOD;
}

// Page footer
$page .= pageFooter();
$page .= <<< EOD
</body>
</html>

EOD;

// Display page
echo $page;
?>
