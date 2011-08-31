<?php

/**
 * Sites sample app - this application shows basic data on all sites the logged in user is a member in.
 *
 */

// init
require_once '../../sdk/familygraph.php';
$config = include '../Config.php';
$familyGraph = new FamilyGraph($config['clientId'], $config['clientSecret']);

// Did the user ask to logout?
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
}

// Initialize parameters
$sampleIndividualId = isset($config['sampleIndividualId']) ? $config['sampleIndividualId'] : null;
$requestedIndividualId = isset($_GET['individualId']) ? $_GET['individualId'] : null;

// Initialize the sample application which is responsible to get all the relevant data
require_once 'SitesSampleApp.php';
$sitesSampleApp = new SitesSampleApp($familyGraph, $sampleIndividualId, $requestedIndividualId);
$sampleAppData = $sitesSampleApp->getData();
$isUserLoggedIn = $sampleAppData['isUserLoggedIn'];
$currentUserName = $sampleAppData['currentUserName'];
$loginUrl = $sampleAppData['loginUrl'];
$logoutUrl = $_SERVER['PHP_SELF'] . '?logout';
if (!isset($sampleAppData['exception'])) {
    $memberships = $sampleAppData['memberships'];
} else {
    /** @var $exception FamilyGraphException */
    $exception = $sampleAppData['exception'];
    $errorDescription = $exception->getType() . ' ' . $exception->getMessage();
}

// Initialize the sample application's common viewer utility which contains utility functions that are common for the samples
require_once '../CommonViewerUtility.php';
$commonViewerUtility = new CommonViewerUtility();

?>
<!doctype html>
<html>
	<head>
		<title>Sites Sample App</title>
		<style>
		body, td {
			font-family: arial;
			font-size: 13px;
			color: #333;
			line-height: 18px;
		}
		a {
			color: #3B5998;
		}
		table {
			display: table;
			border-collapse: collapse;
			border-spacing: 0;
		}
		.site {
			border: solid 1px black;
			padding: 5px;
			margin-bottom: 10px;
		}
		.profileSampleFramework
		{
			width: 1000px;
			margin: auto;
		}
		</style>
	</head>
	<body>
		<!-- Header -->
		<?php if (!$isUserLoggedIn) : ?>
			<div style="padding: 6px 0; background-color: #F8F8F8">
				<p class="profileSampleFramework">This sample app can only run on your site.<br/>
				To run the sample app on your own family site, please <a href="<?php print $loginUrl; ?>">log in</a>
			</div>
			<?php exit; ?>
		<?php endif; ?>
        <?php
        if (isset($exception))
        {
            print htmlspecialchars($errorDescription);
            die();
        }
        ?>
		<div style="position:absolute">
			<h1>Hello <?php print htmlspecialchars($currentUserName); ?></h1>
			<?php if (count($memberships) == 0) : ?>
				<div>You are not a member in any family site on MyHeritage.com</div>
			<?php else : ?>
				<div>You are a member in <?php print count($memberships); ?> family sites on MyHeritage.com</div><p>
				<?php foreach ($memberships as $membership) {
                    $siteLogoImage = null;
                    if ($membership['siteLogo']) {
                        $siteLogoImage = $commonViewerUtility->getBestMediaItemThumbnail($membership['siteLogo'], 200, 'width');
                    }
                ?>
				<div class="site">
					<table>
						<tr>
							<td width="200" valign="top" align="center">
								<?php if ($siteLogoImage) : ?>
								<img src="<?php print $siteLogoImage['url']; ?>" width="<?php print $siteLogoImage['width']; ?>">
								<?php endif; ?>
							</td>

							<td width="20"></td>

							<td valign="top">
								<div><b><?php print htmlspecialchars($membership['siteName']); ?></b></div>
								<?php if ($membership['isManager']) : ?>
								<div>You are the manager of this site</div>
								<?php endif; ?>
								<?php if (isset($membership['lastVisitTime'])) : ?>
								<div>You last visited this site on <?php print $membership['lastVisitTime'] ?></div>
								<?php endif; ?>
								<div>You visited this site <?php print $membership['visitCount'] ?> times</div>
								<hr>
                                <?php if (isset($membership['siteCreatorName'], $membership['siteCreatedDate'])) : ?>
								<div>Site was created by <?php print htmlspecialchars($membership['siteCreatorName']); ?> on <?php print $membership['siteCreatedDate']; ?></div>
                                <?php endif; ?>
								<div>Number of media items: <?php print $membership['mediaCount']; ?></div>
								<div>Number of family trees: <?php print $membership['treeCount']; ?></div>
							</td>
						</tr>
					</table>
				</div>
				<?php } ?>
			<?php endif; ?>
		</div>
	</body>
</html>
