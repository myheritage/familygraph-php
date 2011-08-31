<?php

/**
 * Tree sample app - this application displays person's close family in a beautiful tree structure.
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
$sampleIndividualId = $config['sampleIndividualId'];
$requestedIndividualId = isset($_GET['individualId']) ? $_GET['individualId'] : null;

// Initialize the sample application which is responsible to get all the relevant data
require_once 'TreeSampleApp.php';
$treeSampleApp = new TreeSampleApp($familyGraph, $sampleIndividualId, $requestedIndividualId);
$sampleAppData = $treeSampleApp->getData();
$isUserLoggedIn = $sampleAppData['isUserLoggedIn'];
$currentUserName = $sampleAppData['currentUserName'];
$loginUrl = $sampleAppData['loginUrl'];
$currentUrl = $_SERVER['PHP_SELF'];
$logoutUrl = $currentUrl . '?logout';
if (!isset($sampleAppData['exception'])) {
	$individual = $sampleAppData['individual'];
	$closeFamily = $sampleAppData['closeFamily'];
	$individualId = $closeFamily['individualId'];
	$fatherId = $closeFamily['fatherId'];
	$motherId = $closeFamily['motherId'];
	$siblingIds = $closeFamily['siblingIds'];
	$spousesFamily = $closeFamily['spousesFamily'];
	$relatives = $closeFamily['relatives'];
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
		<title>Tree Sample App</title>
		<style>
		body, td
		{
			font-family: arial;
			font-size: 13px;
			color: #333;
			line-height: 18px;
		}
		body
		{
			margin: 0;
		}
		a
		{
			color: #3B5998;
		}
		table
		{
			display: table;
			border-collapse: collapse;
			border-spacing: 0;
		}
		.male,
		.female,
		.unknown,
		.individual,
		.root_individual
		{
			padding: 5px;
			width: 300px;
			height: 105px;
			position: absolute;
		}
		.male
		{
			background-color: rgb(182,222,252);
		}
		.female
		{
			background-color: rgb(254,206,230);
		}
		.unknown
		{
			background-color: rgb(210, 210, 210);
		}
		.root_individual
		{
			border: solid 2px blue;
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
		<div style="padding: 6px 0; background-color: #F8F8F8">
		<?php if ($isUserLoggedIn) : ?>
			<p class="profileSampleFramework">You are logged in as <?php print htmlspecialchars($currentUserName); ?>
			&nbsp;(<a href="<?php print $logoutUrl; ?>">Logout</a>)
		<?php else : ?>
			<p class="profileSampleFramework">You are not logged in, so running sample app using the sample developers site.<br/>
			To run the sample app on your own family site, please <a href="<?php print $loginUrl; ?>">log in</a>
		<?php endif; ?>
		</div>
		<?php
		if (isset($exception))
		{
			print htmlspecialchars($errorDescription);
			die();
		}
		?>

		<!-- Separator -->
		<hr style="margin-top: 0;">

		<?php
		if (empty($individual)) :
		?>
			No root individual<p>
		<?php
		else :
		?>
		<div style="position:absolute">
		<h1>Family tree of <?php print htmlspecialchars($individual['name']); ?></h1>
		<?php
		// Now draw the tree!
		$x = 5;
		$y = 60;
		$dx = 60;
		$dy = 125;
		$labeldy = 20;
		$labelHTMLSnippetFormat = '<div style="position: absolute; left: %dpx; top: %dpx;">%s</div>';
		
		if ($fatherId || $motherId) {
			print sprintf($labelHTMLSnippetFormat, $x, $y, 'Parents');
			$y += $labeldy;
		}
		
		// Father
		if ($fatherId)
		{
			print $commonViewerUtility->getIndividualBox($relatives[$fatherId], $currentUrl, $x, $y, 'P');
			$y += $dy;
		}
		
		// Mother
		if ($motherId)
		{
			print $commonViewerUtility->getIndividualBox($relatives[$motherId], $currentUrl, $x, $y, 'P');
			$y += $dy;
		}
		if ($fatherId || $fatherId)
		{
			$x += $dx;
		}
		
		// Main individual
		print sprintf($labelHTMLSnippetFormat, $x, $y, 'Me');
		$y += $labeldy;
		print $commonViewerUtility->getIndividualBox($relatives[$individualId], $currentUrl, $x, $y, 'R');
		$y += $dy;
		
		// Spouses and children
		foreach ($spousesFamily as $spouseFamily)
		{
			if (isset($spouseFamily['spouseId']))
			{
				// Spouse
				print sprintf($labelHTMLSnippetFormat, $x, $y, 'Spouse');
				$y += $labeldy;
				print $commonViewerUtility->getIndividualBox($relatives[$spouseFamily['spouseId']], $currentUrl, $x, $y, 'S', $spouseFamily['status']);
				$y += $dy;
			}

			// Children
			if (!empty($spouseFamily['childIds']))
			{
				$x += $dx;

				print sprintf($labelHTMLSnippetFormat, $x, $y, 'Children');
				$y += $labeldy;
				foreach ($spouseFamily['childIds'] as $childId)
				{
					print $commonViewerUtility->getIndividualBox($relatives[$childId], $currentUrl, $x, $y, 'C');
					$y += $dy;
				}

				$x -= $dx;
			}
		}

		// Siblings
		if (!empty($siblingIds))
		{
			print sprintf($labelHTMLSnippetFormat, $x, $y, 'Siblings');
			$y += $labeldy;
						   
			foreach ($siblingIds as $siblingId)
			{
				print $commonViewerUtility->getIndividualBox($relatives[$siblingId], $currentUrl, $x, $y, 'B');
				$y += $dy;
			}
		}
		?>
		<?php endif; ?>
		</div>
	</body>
</html>