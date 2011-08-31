<?php

/**
 * Faces sample app - this application shows all tagged faces of a certain person.
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
require_once 'FacesSampleApp.php';
$facesSampleApp = new FacesSampleApp($familyGraph, $sampleIndividualId, $requestedIndividualId);
$sampleAppData = $facesSampleApp->getData();
$isUserLoggedIn = $sampleAppData['isUserLoggedIn'];
$currentUserName = $sampleAppData['currentUserName'];
$loginUrl = $sampleAppData['loginUrl'];
$logoutUrl = $_SERVER['PHP_SELF'] . '?logout';
if (!isset($sampleAppData['exception'])) {
    $individual = $sampleAppData['individual'];
    $taggedFaces = $sampleAppData['taggedFaces'];
    $nTaggedFaces = count($taggedFaces);
} else {
    /** @var $exception FamilyGraphException */
    $exception = $sampleAppData['exception'];
    $errorDescription = $exception->getType() . ' ' . $exception->getMessage();
}

// Initialize the sample application's common viewer utility which contains utility functions that are common for the samples
require_once '../CommonViewerUtility.php';
$commonViewerUtility = new CommonViewerUtility();

$thumbnailWidth = 80;
$thumbnailHeight = 80;
?>
<!doctype html>
<html>
	<head>
		<title>Faces Sample App</title>
		<style>
		body, td
		{
			font-family: "lucida grande",tahoma,verdana,arial,sans-serif;
			font-size: 13px;
			color: #333;
			line-height: 18px;
		}
		body
		{
			margin: 0;
		}
		.profileSampleFramework
		{
			width: 1000px;
			margin: auto;
		}
		</style>

		<script>

			// Opens a new window with the selected photo
			function OpenFullPhoto(url, w, h)
			{
				window.open(url, "photo", "width=" + w + ",height=" + h + ",modal=yes,alwaysRaised=yes");
			}
		</script>
	</head>
	<body>
		<!-- Header -->
		<div style="padding: 6px 0; background-color: #F8F8F8">
		<?php if ($isUserLoggedIn) { ?>
			<p class="profileSampleFramework">You are logged in as <?php print htmlspecialchars($currentUserName); ?>
			&nbsp;(<a href="<?php print $logoutUrl; ?>">Logout</a>)
		<?php } else { ?>
			<p class="profileSampleFramework">You are not logged in, so running sample app using the sample developers site.<br/>
			To run the sample app on your own family site, please <a href="<?php print $loginUrl; ?>">log in</a>
		<?php } ?>
		</div>

        <!-- Separator -->
        <hr style="margin-top: 0;">
        <?php
        if (isset($exception))
        {
            print htmlspecialchars($errorDescription);
            die();
        }
        ?>

		<div  class="profileSampleFramework">
		<h1>Faces of <?php print htmlspecialchars($individual['name']); ?></h1>
		
		<div>
		<?php if ($nTaggedFaces == 0) { ?>
			Not tagged in any photo
		<?php } else if ($nTaggedFaces == 1) { ?>
			Tagged in one photo
		<?php } else { ?>
			Tagged in <?php print $nTaggedFaces ?> photos
		<?php } ?>
		</div>

		<?php if ($nTaggedFaces > 0) { ?>
		<table>
			<?php for ($i = 0; $i < $nTaggedFaces; $i++) {
                $taggedFace = $taggedFaces[$i];
                $photo = $taggedFace['photo'];
                $tag = $taggedFace['tag'];
                $thumbnail = null;
                $tagHtml = $commonViewerUtility->getTagHtml($photo, $tag, $thumbnailWidth, $thumbnailHeight, $thumbnail);
            ?>
				<?php if ($i % 12 == 0) { ?>
					<tr>
				<?php } ?>
				<td style="cursor:pointer" onclick="OpenFullPhoto('<?php print $thumbnail['url']; ?>', '<?php print $thumbnail['width']; ?>', '<?php print $thumbnail['height']; ?>')"><?php print $tagHtml; ?></td>
				<?php if ($i % 12 == 11 || $i == $nTaggedFaces - 1) { ?>
					</tr>
				<?php } ?>
			<?php } ?>
		</table>
		<?php } ?>
		</div>
	</body>
</html>
