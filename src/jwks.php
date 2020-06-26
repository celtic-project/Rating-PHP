<?php

use ceLTIc\LTI\Jwt\Jwt;

/**
 * This page returns the JWKS.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @version   4.0.0
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('db.php');

$jwt = Jwt::getJwtClient();
$keys = $jwt::getJWKS(PRIVATE_KEY, SIGNATURE_METHOD, KID);

header('Content-type: application/json');
echo json_encode($keys);
?>