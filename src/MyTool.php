<?php

use ceLTIc\LTI;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Profile;
use ceLTIc\LTI\Enum\LtiVersion;

/**
 * This page processes a launch request from an LTI platform.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

class MyTool extends LTI\Tool
{
	// ceLTIc Message class does not include/filters out some message fields (such as placements, roles, icon_uri, custom_parameters)
	// and Canvas-specific fiels (https://canvas.instructure.com/lti/course_navigation/default_enabled, https://canvas.instructure.com/lti/visibility)
	private $extraMessages = array();
	
	// The tool's default privacy level, (determines the PII fields the tool is sent.) defaults to "anonymous"
	private $privacyLevel = "public"; // Canvas: "public" | "name_only" | "email_only" | "anonymous"
	
    function __construct($dataConnector)
    {
        parent::__construct($dataConnector);

        $this->allowSharing = true;

        $this->baseUrl = getAppUrl();
        $this->vendor = new Profile\Item(VENDOR_CODE, VENDOR_NAME, VENDOR_DESCRIPTION, VENDOR_URL);
        $this->product = new Profile\Item(TOOL_UUID, APP_NAME, APP_DESCRIPTION, APP_URL, APP_VERSION);

        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role'), CUSTOM_FIELDS));
		$optionalMessages = array();
		
		$extraMessage = array(
			'type'=>'LtiResourceLinkRequest',
			'label'=>APP_NAME,
			'placements'=>['course_navigation'],
			'icon_uri'=>TOOL_BASE_URL.'images/icon50.png',
			'https://canvas.instructure.com/lti/course_navigation/default_enabled'=>!DEFAULT_DISABLED
		);
		if (INSTRUCTOR_ONLY) {
			$extraMessage['roles'] = ['http://purl.imsglobal.org/vocab/lis/v2/institution/person#Instructor'];
			$extraMessage['https://canvas.instructure.com/lti/visibility'] = "admins";
		}
		$this->extraMessages[] = $extraMessage;

        $this->resourceHandlers[] = new Profile\ResourceHandler(
            new Profile\Item(TOOL_ID, APP_NAME, APP_DESCRIPTION),
            'images/icon50.png', $requiredMessages, $optionalMessages);

        $this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));

        if (isset($_SESSION['lti_version']) && ($_SESSION['lti_version'] === LtiVersion::V1P3)) {
            $this->signatureMethod = SIGNATURE_METHOD;
        }
        $this->jku = getAppUrl() . 'jwks.php';
        $this->kid = KID;
        $this->rsaKey = PRIVATE_KEY;
        $this->requiredScopes = array(
            LTI\Service\Membership::$SCOPE,
            LTI\Service\Groups::$SCOPE
        );
    }

    protected function onLaunch(): void
    {
// Check the user has an appropriate role
        if ($this->userResult->isLearner() || $this->userResult->isStaff()) {
			$_SESSION['userResult'] = json_encode($this->userResult);

// Initialise the user session
            $_SESSION['lti_version'] = $this->platform->ltiVersion;
            $_SESSION['resource_pk'] = $this->resourceLink->getRecordId();
            $_SESSION['consumer_pk'] = $this->platform->getRecordId();
            $_SESSION['user_consumer_pk'] = $this->userResult->getResourceLink()->getPlatform()->getRecordId();
            $_SESSION['user_resource_pk'] = $this->userResult->getResourceLink()->getRecordId();
            $_SESSION['user_pk'] = $this->userResult->getRecordId();
			$_SESSION['ltiUserId'] = $this->userResult->ltiUserId;
            $_SESSION['isStudent'] = $this->userResult->isLearner();
// The default index.php file will try to get the user information from the memberships based on $_SESSION['ltiUserId'].
// Alternatively, you can set session values here.
/* 			$_SESSION['user']['fullname'] = $this->userResult->fullname . " (here)";
			$_SESSION['user']['firstname'] = $this->userResult->firstname;
			$_SESSION['user']['middlename'] = $this->userResult->middlename;
			$_SESSION['user']['lastname'] = $this->userResult->lastname;
			$_SESSION['user']['sourcedId'] = $this->userResult->sourcedId;
			$_SESSION['user']['username'] = $this->userResult->username;
			$_SESSION['user']['email'] = $this->userResult->email;
			$_SESSION['user']['image'] = $this->userResult->image;
			$_SESSION['user']['roles'] = $this->userResult->roles;
			$_SESSION['user']['ltiUserId'] = $this->userResult->ltiUserId; */

// Redirect the user to display the list of items for the resource link
            $this->redirectUrl = getAppUrl();
        } else {
            $this->reason = 'Invalid role.';
            $this->ok = false;
        }
    }

    protected function onDashboard(): void
    {
        global $db;

        $title = APP_NAME;
        $appUrl = TOOL_BASE_URL;
        $iconUrl = getAppUrl() . 'images/icon50.png';
        if (empty($this->context)) {
            $html = <<< EOD
        <p>
          This is onDashboard with empty context.
        </p>
EOD;
            $this->output = $html;
        } else {
            $rss = <<< EOD
<rss xmlns:a10="http://www.w3.org/2005/Atom" version="2.0">
  <channel>
    <title>Dashboard</title>
    <link>{$appUrl}</link>
    <description />
    <image>
      <url>{$iconUrl}</url>
      <title>Dashboard</title>
      <link>{$appUrl}</link>
      <description>{$title} Dashboard</description>
    </image>
  </channel>
</rss>
EOD;
            header('Content-type: text/xml');
            $this->output = $rss;
        }
    }

    protected function onRegister(): void
    {
// Initialise the user session
        $_SESSION['consumer_pk'] = $this->platform->getRecordId();
        $_SESSION['tc_profile_url'] = $_POST['tc_profile_url'];
        $_SESSION['tc_profile'] = $this->platform->profile;
        $_SESSION['return_url'] = $_POST['launch_presentation_return_url'];

// Redirect the user to process the registration
        $this->redirectUrl = getAppUrl() . 'register.php';
    }

    protected function onRegistration(): void
    {
        if (!defined('AUTO_ENABLE') || !AUTO_ENABLE) {
            $successMessage = 'Note that the tool must be enabled by the tool provider before it can be used.';
        } else if (!defined('ENABLE_FOR_DAYS') || (ENABLE_FOR_DAYS <= 0)) {
            $successMessage = 'The tool has been automatically enabled by the tool provider for immediate use.';
        } else {
            $successMessage = 'The tool has been enabled for you to use for the next ' . ENABLE_FOR_DAYS . ' day';
            if (ENABLE_FOR_DAYS > 1) {
                $successMessage .= 's';
            }
        }
        $appName = APP_NAME;
		$disabledMsg = DEFAULT_DISABLED?"<p>The navigation item is disabled by default. Once you have registered the app, you will need to install it, and instructors will need to add it to their course navigation.</p>":"";
        $html = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-language" content="EN" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>LTI Tool registration</title>
  <script src="js/jquery-3.7.0.min.js" type="text/javascript"></script>
  <link href="css/basic-lti.css" media="screen" rel="stylesheet" type="text/css" />
  <script type="text/javascript">
    //<![CDATA[
    function doRegister() {
      $('#id_continue').addClass('hide');
      $('#id_loading').removeClass('hide');
      $.ajax({
        url: 'registration.php',
        dataType: 'json',
        data: {
          'openid_configuration': '{$_REQUEST['openid_configuration']}',
          'registration_token': '{$_REQUEST['registration_token']}'
        },
        type: 'POST',
        success: function (response) {
          $('#id_loading').addClass('hide');
          if (response.ok) {
            $('#id_registered').removeClass('hide');
            $('#id_close').removeClass('hide');
          } else {
            $('#id_notregistered').removeClass('hide');
            if (response.message) {
                $('#id_reason').text(': ' + response.message);
            }
          }
        },
        error: function (jxhr, msg, err) {
          $('#id_loading').addClass('hide');
          $('#id_reason').text(': Sorry an error occurred; please try again later.');
        }
      });
    }

    function doClose(el) {
      (window.opener || window.parent).postMessage({subject:'org.imsglobal.lti.close'}, '*');
      return true;
    }
    //]]>
  </script>
</head>
<body>
  <h1>{$appName} Tool Registration</h1>

  <p>
    This page allows you to perform a dynamic registration with an LTI 1.3 platform.
  </p>
{$disabledMsg}

  <p id="id_continue" class="aligncentre">
    <button type="button" onclick="return doRegister();">Continue</button>
  </p>
  <p id="id_loading" class="aligncentre hide">
    <img src="images/loading.gif">
  </p>

  <p id="id_registered" class="success hide">
    The tool registration was successful.  {$successMessage}
  </p>
  <p id="id_notregistered" class="error hide">
    The tool registration failed<span id="id_reason"></span>
  </p>

  <p id="id_close" class="aligncentre hide">
    <button type="button" onclick="return doClose(this);">Close</button>
  </p>

  </body>
</html>
EOD;
        $this->output = $html;
    }

    protected function onError(): void
    {
        $msg = $this->message;
        if ($this->debugMode && !empty($this->reason)) {
            $msg = $this->reason;
        }
        $title = APP_NAME;
        $this->errorOutput = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$title}</title>
</head>
<body>
<h1>Error</h1>
<p style="font-weight: bold; color: #f00;">{$msg}</p>
</body>
</html>
EOD;
    }

    public function doRegistration(): void
    {
        $platformConfig = $this->getPlatformConfiguration();
        if ($this->ok) {
            $toolConfig = $this->getConfiguration($platformConfig);
			$toolConfig['https://purl.imsglobal.org/spec/lti-tool-configuration']['messages'] = array_merge($toolConfig['https://purl.imsglobal.org/spec/lti-tool-configuration']['messages'], $this->extraMessages);
			$toolConfig['https://purl.imsglobal.org/spec/lti-tool-configuration']['https://canvas.instructure.com/lti/privacy_level'] = $this->privacyLevel;
            $registrationConfig = $this->sendRegistration($platformConfig, $toolConfig);
            if ($this->ok) {
                $this->getPlatformToRegister($platformConfig, $registrationConfig, false);
                if (defined('AUTO_ENABLE') && AUTO_ENABLE) {
                    $this->platform->enabled = true;
                }
                if (defined('ENABLE_FOR_DAYS') && (ENABLE_FOR_DAYS > 0)) {
                    $now = time();
                    $this->platform->enableFrom = $now;
                    $this->platform->enableUntil = $now + (ENABLE_FOR_DAYS * 24 * 60 * 60);
                }
                $this->ok = $this->platform->save();
                if (!$this->ok) {
                    $checkPlatform = Platform::fromPlatformId($this->platform->platformId, $this->platform->clientId,
                            $this->platform->deploymentId, $this->dataConnector);
                    if (!empty($checkPlatform->created)) {
                        $this->reason = 'The platform is already registered.';
                    } else {
                        $this->reason = 'Sorry, an error occurred when saving the platform details.';
                    }
                }
            }
        }
    }
}

?>
