<?php

use ceLTIc\LTI;

/**
 * This page generates configuration information for Canvas platforms.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

$url = getAppUrl();
$domain = parse_url($url, PHP_URL_HOST);

$here = function($val) {
    return $val;
};
$custom = "";

if (!isset($_GET['json'])) {
    foreach (CUSTOM_FIELDS as $field => $val)
        $custom .= '    <lticm:property name="' . $field . '">' . $val . "</lticm:property>\n";
    $xml = <<< EOD
<?xml version="1.0" encoding="UTF-8"?>
<cartridge_basiclti_link xmlns="http://www.imsglobal.org/xsd/imslticc_v1p0"
                         xmlns:blti = "http://www.imsglobal.org/xsd/imsbasiclti_v1p0"
                         xmlns:lticm ="http://www.imsglobal.org/xsd/imslticm_v1p0"
                         xmlns:lticp ="http://www.imsglobal.org/xsd/imslticp_v1p0"
                         xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance"
                         xsi:schemaLocation = "http://www.imsglobal.org/xsd/imslticc_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd
    http://www.imsglobal.org/xsd/imsbasiclti_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticm_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticp_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd">
  <blti:title>{$here(APP_NAME)}</blti:title>
  <blti:description>{$here(APP_NAME)} LTI App</blti:description>
  <blti:icon>{$url}images/icon16.png</blti:icon>
  <blti:launch_url>{$url}connect.php</blti:launch_url>
  <blti:extensions platform="canvas.instructure.com">
    <lticm:property name="tool_id">{$here(TOOL_ID)}</lticm:property>
    <lticm:property name="privacy_level">public</lticm:property>
    <lticm:property name="domain">{$domain}</lticm:property>
    <lticm:property name="oauth_compliant">true</lticm:property>
{$custom}
  </blti:extensions>
  <blti:vendor>
    <lticp:code>{$here(VENDOR_CODE)}</lticp:code>
    <lticp:name>{$here(VENDOR_NAME)}</lticp:name>
    <lticp:description>{$here(VENDOR_DESCRIPTION)}</lticp:description>
    <lticp:url>{$here(VENDOR_URL)}</lticp:url>
    <lticp:contact>
      <lticp:email>{$here(VENDOR_EMAIL)}</lticp:email>
    </lticp:contact>
  </blti:vendor>
</cartridge_basiclti_link>
EOD;

    header("Content-Type: application/xml; ");

    echo $xml;
} else {
    foreach (CUSTOM_FIELDS as $field => $val)
        $custom .= '    "' . $field . '": "' . $val . "\",\n";
    $custom = rtrim($custom, ",\n");
    $json = <<< EOD
{
  "title": "{$here(APP_NAME)}",
  "description": "Access to {$here(APP_NAME)} using LTI",
  "privacy_level": "public",
  "oidc_initiation_url": "{$url}connect.php",
  "target_link_uri": "{$url}connect.php",
  "scopes": [
    "{$here(LTI\Service\Membership::$SCOPE)}"
  ],
  "extensions": [
    {
      "domain": "{$domain}",
      "tool_id": "{$here(TOOL_ID)}",
      "platform": "canvas.instructure.com",
      "privacy_level": "public",
      "settings": {
        "text": "{$here(APP_NAME)}",
        "icon_url": "{$url}icon16.png",
        "placements": [
          {
            "placement": "course_navigation",
            "message_type": "LtiResourceLinkRequest"
          }
        ]
      }
    }
  ],
  "public_jwk_url": "{$url}jwks.php",
  "custom_fields": {
{$custom}
  }
}
EOD;

    header("Content-Type: application/json; ");

    echo $json;
}
?>
