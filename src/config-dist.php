<?php

use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * This page contains the configuration settings for the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
###
###  Application settings
###
// Uncomment the next line to log all PHP messages
//  error_reporting(E_ALL);
// Set the application logging level
Util::$logLevel = LogLevel::Error;

// Specify a prefix (starting with '/') when the REQUEST_URI server variable is missing the first part of the real path
define('REQUEST_URI_PREFIX', '');

###
###  App specific settings
###
define('TOOL_ID', 'lti13');
define('SESSION_NAME', 'lti13');
define('TOOL_BASE_URL', '');
define('TOOL_UUID', '6a629dc8-7f50-438c-a106-8d9560acdfda'); // Linux command: uuidgen
define('APP_NAME', 'Basic LTI 1.3');
define('APP_DESCRIPTION', 'An LTI 1.3 test app.');
define('APP_VERSION', '0.1.0');
define('APP_URL', 'https://github.com/kylejtuck/Basic-LTI-PHP/');
define('VENDOR_CODE', 'kjt');
define('VENDOR_NAME', 'Kyle J Tuck');
define('VENDOR_DESCRIPTION', 'Independent developer');
define('VENDOR_URL', 'https://github.com/kylejtuck');
define('VENDOR_EMAIL', 'kylejtuck@gmail.com');
define('INSTRUCTOR_ONLY', true);
define('DEFAULT_DISABLED', true);
define('CUSTOM_FIELDS', array(
#	'COURSE_NUMBER'=>"\$Canvas.course.id",
#	'COURSE_SIS_ID'=>"\$Canvas.course.sisSourceId"
));

###
###  Database connection settings
###
define('DB_NAME', '');  // e.g. 'mysql:dbname=MyDb;host=localhost' or 'sqlite:php-rating.sqlitedb'
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_TABLENAME_PREFIX', '');

###
###  LTI 1.3 Security settings
###
define('SIGNATURE_METHOD', 'RS256');
define('KID', '');  // A random string to identify the key value
define('PRIVATE_KEY', <<< EOD
-----BEGIN RSA PRIVATE KEY-----
Insert private key here
-----END RSA PRIVATE KEY-----
EOD
);

###
###  Dynamic registration settings
###
define('AUTO_ENABLE', false);
define('ENABLE_FOR_DAYS', 0);
?>
