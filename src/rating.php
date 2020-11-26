<?php
/**
 * This page processes an AJAX request to save a user rating for an item.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

// Initialise session and database
$db = null;
$ok = init($db, true);
if ($ok) {
// Ensure request is complete and for a student
    $ok = isset($_POST['id']) && isset($_POST['value']) && $_SESSION['isStudent'];
}
if ($ok) {
// Save rating
    $ok = false;
    $item = getItem($db, $_SESSION['resource_pk'], intval($_POST['id']));
    if (($item !== false) && saveRating($db, $_SESSION['user_pk'], $_POST['id'], $_POST['value'])) {
        updateGradebook($db, $_SESSION['user_resource_pk'], $_SESSION['user_pk'], $item, $_POST['value']);
        $ok = true;
    }
}

// Generate response
if ($ok) {
    $response = array('response' => 'Success');
} else {
    $response = array('response' => 'Fail');
}

// Return response
header('Content-type: application/json');
echo json_encode($response);
?>
