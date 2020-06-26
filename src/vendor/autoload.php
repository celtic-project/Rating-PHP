<?php
/**
 * This page provides a function to autoload a class file - it will be automatically overwritten when composer is used.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
spl_autoload_register(function ($class) {

    // base directory for the class files
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, $class) . '.php';

    // Update location if class requested is from the ceLTIc\LTI class library
    $file = str_replace(DIRECTORY_SEPARATOR . 'ceLTIc' . DIRECTORY_SEPARATOR . 'LTI' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'celtic' . DIRECTORY_SEPARATOR . 'lti' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR, $file);

    // Update location if class requested is from the Firebase\php-jwt class library
    $file = str_replace(DIRECTORY_SEPARATOR . 'Firebase' . DIRECTORY_SEPARATOR . 'JWT' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'firebase' . DIRECTORY_SEPARATOR . 'php-jwt' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
        $file);

    // if the file exists, require it
    if (file_exists($file)) {
        require($file);
    }
});
?>
