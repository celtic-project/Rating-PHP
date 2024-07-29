<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\ApiHook\ApiHook;
use ceLTIc\LTI\OAuth;
use ceLTIc\LTI\Enum\ServiceAction;

/**
 * This page provides general functions to support the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('db.php');
require_once('MyTool.php');

LTI\ResourceLink::registerApiHook(ApiHook::$MEMBERSHIPS_SERVICE_HOOK, 'moodle', 'ceLTIc\LTI\ApiHook\moodle\MoodleApiResourceLink');
LTI\Tool::registerApiHook(ApiHook::$USER_ID_HOOK, 'canvas', 'ceLTIc\LTI\ApiHook\canvas\CanvasApiTool');
LTI\ResourceLink::registerApiHook(ApiHook::$MEMBERSHIPS_SERVICE_HOOK, 'canvas', 'ceLTIc\LTI\ApiHook\canvas\CanvasApiResourceLink');

###
###  Initialise application session and database connection
###

function init(&$db, $checkSession = null, $currentLevel = 0)
{
    $ok = true;

// Check if path value passed by web server needs amending
    if (defined('REQUEST_URI_PREFIX') && !empty(REQUEST_URI_PREFIX)) {
        $_SERVER['REQUEST_URI'] = REQUEST_URI_PREFIX . $_SERVER['REQUEST_URI'];
    }

// Set timezone
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('UTC');
    }

// Set session cookie path
    ini_set('session.cookie_path', getAppPath($currentLevel));

// Set samesite value for cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_samesite', 'none');
        ini_set('session.cookie_secure', true);
    }

// Open session
    session_name(SESSION_NAME);
    session_start();

// Set the default tool
    LTI\Tool::$defaultTool = new MyTool(null);

    if (!is_null($checkSession) && $checkSession) {
        $ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
            isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
    }

    if (!$ok) {
        $_SESSION['error_message'] = 'Unable to open session.';
    } else {
// Open database connection
        $db = open_db(!$checkSession);
        $ok = $db !== false;
        if (!$ok) {
            if (!is_null($checkSession) && $checkSession) {
// Display a more user-friendly error message to LTI users
                $_SESSION['error_message'] = 'Unable to open database.';
            }
        } else if (!is_null($checkSession) && !$checkSession) {
// Create database tables (if needed)
            $ok = init_db($db);  // assumes a MySQL/SQLite database is being used
            if (!$ok) {
                $_SESSION['error_message'] = 'Unable to initialise database: \'' . $db->errorInfo()[2] . '\'';
            }
        }
    }

    return $ok;
}

###
###  Get the web path to the application
###

function getAppPath($currentLevel = 0)
{
    $path = getAppUrl($currentLevel);
    $pos = strpos($path, '/', 8);
    if ($pos !== false) {
        $path = substr($path, $pos);
    }

    return $path;
}

###
###  Get the URL to the application
###

function getAppUrl($currentLevel = 0)
{
    $request = OAuth\OAuthRequest::from_request();
    $url = $request->get_normalized_http_url();
    for ($i = 1; $i <= $currentLevel; $i++) {
        $pos = strrpos($url, '/');
        if ($pos === false) {
            break;
        } else {
            $url = substr($url, 0, $pos);
        }
    }
    $pos = strrpos($url, '/');
    if ($pos !== false) {
        $url = substr($url, 0, $pos + 1);
    }

    return $url;
}

###
###  Return a string representation of a float value
###

function floatToStr($num)
{
    $str = sprintf('%f', $num);
    $str = preg_replace('/0*$/', '', $str);
    if (substr($str, -1) == '.') {
        $str = substr($str, 0, -1);
    }

    return $str;
}

###
###  Return the value of a POST parameter
###

function postValue($name, $defaultValue = null)
{
    $value = $defaultValue;
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    }

    return $value;
}

function pageFooter()
{
    $here = function($val) {
        return $val;
    };

    return <<< EOD
    <footer>
      <div>{$here(APP_NAME)} version {$here(APP_VERSION)} &copy; {$here(date('Y'))} <a href="//celtic-project.org/" target="_blank">ceLTIc Project</a> (powered by its open source <a href="https://github.com/celtic-project/LTI-PHP" target="_blank">LTI-PHP library</a>)</div>
    </footer>

EOD;
}

/**
 * Returns a string representation of a version 4 GUID, which uses random
 * numbers.There are 6 reserved bits, and the GUIDs have this format:
 *     xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
 * where 'x' is a hexadecimal digit, 0-9a-f.
 *
 * See http://tools.ietf.org/html/rfc4122 for more information.
 *
 * Note: This function is available on all platforms, while the
 * com_create_guid() is only available for Windows.
 *
 * Source: https://github.com/Azure/azure-sdk-for-php/issues/591
 *
 * @return string A new GUID.
 */
function getGuid()
{
    return sprintf('%04x%04x-%04x-%04x-%02x%02x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
        mt_rand(0, 65535), // 16 bits for "time_mid"
        mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with
// the most significant 4 bits being 0100
// to indicate randomly generated version
        mt_rand(0, 64) + 128, // 8 bits  for "clock_seq_hi", with
// the most significant 2 bits being 10,
// required by version 4 GUIDs.
        mt_rand(0, 256), // 8 bits  for "clock_seq_low"
        mt_rand(0, 65535), // 16 bits for "node 0" and "node 1"
        mt_rand(0, 65535), // 16 bits for "node 2" and "node 3"
        mt_rand(0, 65535)         // 16 bits for "node 4" and "node 5"
    );
}

?>