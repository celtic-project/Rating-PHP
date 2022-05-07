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

if (!isset($_GET['json'])) {
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
  <blti:description>Access to {$here(APP_NAME)} using LTI</blti:description>
  <blti:icon>{$url}images/icon16.png</blti:icon>
  <blti:launch_url>{$url}connect.php</blti:launch_url>
  <blti:extensions platform="canvas.instructure.com">
    <lticm:property name="tool_id">rating</lticm:property>
    <lticm:property name="privacy_level">public</lticm:property>
    <lticm:property name="domain">{$domain}</lticm:property>
    <lticm:property name="oauth_compliant">true</lticm:property>
  </blti:extensions>
  <blti:vendor>
    <lticp:code>spvsp</lticp:code>
    <lticp:name>SPV Software Products</lticp:name>
    <lticp:description>Provider of open source educational tools.</lticp:description>
    <lticp:url>http://www.spvsoftwareproducts.com/</lticp:url>
    <lticp:contact>
      <lticp:email>stephen@spvsoftwareproducts.com</lticp:email>
    </lticp:contact>
  </blti:vendor>
</cartridge_basiclti_link>
EOD;

    header("Content-Type: application/xml; ");

    echo $xml;
} else {

    $json = <<< EOD
{
  "title": "{$here(APP_NAME)}",
  "description": "Access to {$here(APP_NAME)} using LTI",
  "privacy_level": "public",
  "oidc_initiation_url": "{$url}connect.php",
  "target_link_uri": "{$url}connect.php",
  "scopes": [
    "{$here(LTI\Service\LineItem::$SCOPE)}",
    "{$here(LTI\Service\Score::$SCOPE)}",
    "{$here(LTI\Service\Membership::$SCOPE)}"
  ],
  "extensions": [
    {
      "domain": "{$domain}",
      "tool_id": "rating",
      "platform": "canvas.instructure.com",
      "privacy_level": "public",
      "settings": {
        "text": "{$here(APP_NAME)}",
        "icon_url": "{$url}icon16.png",
        "placements": [
          {
            "placement": "assignment_selection",
            "message_type": "LtiDeepLinkingRequest"
          }
        ]
      }
    }
  ],
  "public_jwk_url": "{$url}jwks.php",
  "custom_fields": {
  }
}
EOD;

    header("Content-Type: application/json; ");

    echo $json;
}
?>