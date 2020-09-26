<?php

use ceLTIc\LTI;
use ceLTIc\LTI\Profile;

/**
 * This page processes a launch request from an LTI platform.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @version   4.0.0
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

class RatingTool extends LTI\Tool
{

    function __construct($dataConnector)
    {
        parent::__construct($dataConnector);

        $this->allowSharing = true;

        $this->baseUrl = getAppUrl();

        $this->vendor = new Profile\Item('ims', 'IMSGlobal', 'IMS Global Learning Consortium Inc', 'https://www.imsglobal.org/');
        $this->product = new Profile\Item('d751f24f-140e-470f-944c-2d92b114db40', 'Rating',
            'Sample LTI tool to create lists of items to be rated.', 'http://www.spvsoftwareproducts.com/php/rating/', VERSION);

        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role')));
        $optionalMessages = array(new Profile\Message('ContentItemSelectionRequest', 'connect.php',
                array('User.id', 'Membership.role')),
            new Profile\Message('DashboardRequest', 'connect.php', array('User.id'))
        );

        $this->resourceHandlers[] = new Profile\ResourceHandler(
            new Profile\Item('rating', 'Rating app', 'An example tool which generates lists of items for rating.'),
            'images/icon50.png', $requiredMessages, $optionalMessages);

        $this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));

        $this->signatureMethod = SIGNATURE_METHOD;
        $this->kid = KID;
        $this->rsaKey = PRIVATE_KEY;
        $this->requiredScopes = array(
            LTI\Service\LineItem::$SCOPE,
            LTI\Service\Score::$SCOPE,
            LTI\Service\Membership::$SCOPE
        );
    }

    function onLaunch()
    {
// Check the user has an appropriate role
        if ($this->userResult->isLearner() || $this->userResult->isStaff()) {
// Initialise the user session
            $_SESSION['consumer_pk'] = $this->platform->getRecordId();
            $_SESSION['resource_pk'] = $this->resourceLink->getRecordId();
            $_SESSION['user_consumer_pk'] = $this->userResult->getResourceLink()->getPlatform()->getRecordId();
            $_SESSION['user_resource_pk'] = $this->userResult->getResourceLink()->getRecordId();
            $_SESSION['user_pk'] = $this->userResult->getRecordId();
            $_SESSION['isStudent'] = $this->userResult->isLearner();
            $_SESSION['isContentItem'] = false;

// Redirect the user to display the list of items for the resource link
            $this->redirectUrl = getAppUrl();
        } else {
            $this->reason = 'Invalid role.';
            $this->ok = false;
        }
    }

    function onContentItem()
    {
// Check that the Platform is allowing the return of an LTI link
        $this->ok = in_array(LTI\ContentItem::LTI_LINK_MEDIA_TYPE, $this->mediaTypes) || in_array('*/*', $this->mediaTypes);
        if (!$this->ok) {
            $this->reason = 'Return of an LTI link not offered';
        } else {
            $this->ok = !in_array('none', $this->documentTargets) || (count($this->documentTargets) > 1);
            if (!$this->ok) {
                $this->reason = 'No visible document target offered';
            }
        }
        if ($this->ok) {
// Initialise the user session
            $_SESSION['consumer_pk'] = $this->platform->getRecordId();
            $_SESSION['resource_id'] = getGuid();
            $_SESSION['resource_pk'] = null;
            $_SESSION['user_consumer_pk'] = $_SESSION['consumer_pk'];
            $_SESSION['user_pk'] = null;
            $_SESSION['isStudent'] = false;
            $_SESSION['isContentItem'] = true;
            $_SESSION['lti_version'] = $this->platform->ltiVersion;
            $_SESSION['return_url'] = $this->returnUrl;
            $_SESSION['title'] = postValue('title', 'Rating item');
            $_SESSION['text'] = postValue('text');
            $_SESSION['data'] = postValue('data');
            $_SESSION['document_targets'] = $this->documentTargets;
// Redirect the user to display the list of items for the resource link
            $this->redirectUrl = getAppUrl();
        }
    }

    function onDashboard()
    {
        global $db;

        $title = APP_NAME;
        $appUrl = 'http://www.spvsoftwareproducts.com/php/rating/';
        $iconUrl = getAppUrl() . 'images/icon50.png';
        if (empty($this->context)) {
            $ratings = getUserSummary($db, $this->userResult->getResourceLink()->getPlatform()->getRecordId(),
                $this->userResult->getRecordId());
            $numRatings = count($ratings);
            $courses = array();
            $lists = array();
            $tot_rating = 0;
            foreach ($ratings as $rating) {
                $courses[$rating->lti_context_id] = true;
                $lists[$rating->resource_id] = true;
                $tot_rating += ($rating->rating / $rating->max_rating);
            }
            $numCourses = count($courses);
            $numLists = count($lists);
            if ($numRatings > 0) {
                $av_rating = floatToStr($tot_rating / $numRatings * 5);
            }
            $html = <<< EOD
        <p>
          Here is a summary of your rating of items:
        </p>
        <ul>
          <li><em>Number of courses:</em> {$numCourses}</li>
          <li><em>Number of rating lists:</em> {$numLists}</li>
          <li><em>Number of ratings made:</em> {$numRatings}</li>

EOD;
            if ($numRatings > 0) {
                $html .= <<< EOD
          <li><em>Average rating:</em> {$av_rating} out of 5</li>

EOD;
            }
            $html .= <<< EOD
        </ul>

EOD;
            $this->output = $html;
        } else {
            if ($this->userResult->isLearner()) {
                $ratings = getUserRatings($db, $this->context->getRecordId(), $this->userResult->getRecordId());
            } else {
                $ratings = getContextRatings($db, $this->context->getRecordId());
            }
            $resources = array();
            $totals = array();
            foreach ($ratings as $rating) {
                $tot = ($rating->rating / $rating->max_rating);
                if (array_key_exists($rating->title, $resources)) {
                    $resources[$rating->title] += 1;
                    $totals[$rating->title] += $tot;
                } else {
                    $resources[$rating->title] = 1;
                    $totals[$rating->title] = $tot;
                }
            }
            ksort($resources);
            $items = '';
            $n = 0;
            foreach ($resources as $title => $value) {
                $n++;
                $av = floatToStr($totals[$title] / $value * 5);
                $plural = '';
                if ($value <> 1) {
                    $plural = 's';
                }
                $items .= <<< EOD
    <item>
      <title>Link {$n}</title>
      <description>{$value} item{$plural} rated (average {$av} out of 5)</description>
    </item>
EOD;
            }
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
    </image>{$items}
  </channel>
</rss>
EOD;
            header('Content-type: text/xml');
            $this->output = $rss;
        }
    }

    function onRegister()
    {
// Initialise the user session
        $_SESSION['consumer_pk'] = $this->platform->getRecordId();
        $_SESSION['tc_profile_url'] = $_POST['tc_profile_url'];
        $_SESSION['tc_profile'] = $this->platform->profile;
        $_SESSION['return_url'] = $_POST['launch_presentation_return_url'];

// Redirect the user to process the registration
        $this->redirectUrl = getAppUrl() . 'register.php';
    }

    function onError()
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
<link href="css/rateit.css" media="screen" rel="stylesheet" type="text/css" />
<script src="js/jquery.min.js" type="text/javascript"></script>
<script src="js/jquery.rateit.min.js" type="text/javascript"></script>
<link href="css/rating.css" media="screen" rel="stylesheet" type="text/css" />
</head>
<body>
<h1>Error</h1>
<p style="font-weight: bold; color: #f00;">{$msg}</p>
</body>
</html>
EOD;
    }

}

?>
