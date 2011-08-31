<?php

/**
 * Profile sample app - this application shows data on a specific profile (Individual).
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
require_once 'ProfileSampleApp.php';
$profileSampleApp = new ProfileSampleApp($familyGraph, $sampleIndividualId, $requestedIndividualId);
$sampleAppData = $profileSampleApp->getData();
$isUserLoggedIn = $sampleAppData['isUserLoggedIn'];
$currentUserName = $sampleAppData['currentUserName'];
$loginUrl = $sampleAppData['loginUrl'];
$currentUrl = $_SERVER['PHP_SELF'];
$logoutUrl = $currentUrl . '?logout';
if (!isset($sampleAppData['exception'])) {
    $individual = $sampleAppData['individual'];
    $rootIndividualsInAllTrees = $sampleAppData['rootIndividualsInAllTrees'];
    $immediateFamily = $sampleAppData['immediateFamily'];
    $personalPhotos = $sampleAppData['personalPhotos'];
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
		<title>Profile Sample App</title>
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
			text-decoration: none;
		}

		a:hover
		{
			text-decoration: underline;
		}
		h1
		{
			margin: 0;
			font-size: 18px;
			font-weight: bold;
		}

		h2
		{
			font-size: 16px;
			font-weight: bold;
			margin: 25px 0 8px 0;
			border-bottom: 1px solid #777;
		}

		h3
		{
			display: block;
			font-size: 1.17em;
			margin: 1em 0;
			font-weight: bold;
		}
		.item
		{
			padding-bottom: 6px;
		}
		table
		{
			display: table;
			border-collapse: collapse;
			border-spacing: 0;
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

			// Shows all the faces of a person as tagged in photos
			function ShowFaces(id)
			{
				window.open("/samples/faces/faces-sample-app.php?individualId=" + id, "faces", "width=1050,height=600,modal=yes,alwaysRaised=yes,scrollbars=1");
			}

			// Callback for the individual drop-list
			function SelectIndividual(individuals)
			{
				var index = individuals.selectedIndex;
				var id = individuals.options[index].value;
				window.location = '<?php print $currentUrl; ?>?individualId=' + id;
			}
		</script>
	</head>
	<body>

	<!-- Header -->
	<div style="padding: 6px 0; background-color: #F8F8F8">
	<?php if ($isUserLoggedIn) : ?>
		<p class="profileSampleFramework">You are logged in as <?php print htmlspecialchars($currentUserName); ?>
		&nbsp;(<a href="<?php print $logoutUrl; ?>">Logout</a>)<br/>
		<?php if ($requestedIndividualId) : ?>
			<!-- Show a link to get back to the root person -->
			Change main individual:
			<a href="<?php print $currentUrl; ?>">Back to root person</a>
		<?php else :
			if (count($rootIndividualsInAllTrees) > 1) :
		?>
				Change main individual:
				<br>
				<!-- Drop-list with all the root individuals in all the family trees which are in all the family sites that the current user is a member in -->
				<select name="individuals" onchange="javascript:SelectIndividual(this)">
                    <option value="0">Select</option>
					<?php foreach ($rootIndividualsInAllTrees as $currentIndividualInformation) : ?>
					<option value="<?php print $currentIndividualInformation['treeRootIndividualId']; ?>"><?php print htmlspecialchars($currentIndividualInformation['treeRootIndividualName']); ?> [in: <?php print htmlspecialchars($currentIndividualInformation['siteName']); ?> : <?php print htmlspecialchars($currentIndividualInformation['treeName']); ?>]</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		<?php endif; ?>
		</p>
	<?php else: ?>
		<p class="profileSampleFramework">You are not logged in, so running sample app using the sample developers site.<br/>
		To run the sample app on your own family site, please <a href="<?php print $loginUrl; ?>">log in</a>
	<?php endif; ?>
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
    
	<?php
	if (empty($individual))
	{
		// No root individual supplied
		print 'No root individual<p>';
	}
	else
	{
		// Get all the information we want to display
		$events = (isset($individual['events']) ? $individual['events'] : null);
		$media = (isset($individual['media']) ? $individual['media'] : null);
		$notes = (isset($individual['notes']) ? $individual['notes'] : null);
		$citations = (isset($individual['citations']) ? $individual['citations'] : null);
        $personalPhotoImage = null;
        if (isset($individual['personal_photo']['id'], $personalPhotos[$individual['personal_photo']['id']])) {
            $personalPhoto = $personalPhotos[$individual['personal_photo']['id']];
            $personalPhotoImage = $commonViewerUtility->getBestMediaItemThumbnail($personalPhoto, 192, 'width');
        }
	?>

	<table class="profileSampleFramework">
		<tr>
			<!-- Main individual image -->
			<td valign='top' width="192">
				<?php if ($personalPhotoImage) : ?>
					<img src="<?php print $personalPhotoImage['url']; ?>" width="192">
				<?php else : ?>
					No photo
				<?php endif; ?>

				<div><a href="/samples/tree/tree-sample-app.php?individualId=<?php print $individual['id']; ?>" target="tree">Show family tree</a></div>
			</td>

			<!-- Gap -->
			<td width="18"></td>

			<!-- Main individual information -->
			<td valign='top' width="790">
				<!-- name -->
				<h1><?php print htmlspecialchars($individual['name']); ?></h1>

				<!-- Life span -->
				<div><?php print htmlspecialchars($commonViewerUtility->getYearRange($individual)); ?></div>

				<!-- Main individual events -->
				<?php if (isset($events) && isset($events['data']) && count($events['data']) > 0) : ?>
					<h2>Events</h2>
					<?php
					foreach ($events['data'] as $event) :
						if (isset($event['date']['text'])) :
							$date = $event['date']['text'];
						else :
							$date = '';
						endif;
					?>
						<div class="item">
							<div><b><?php print htmlspecialchars($event['title']); ?></b></div>
							<?php if ($date) : ?>
								<div><?php print htmlspecialchars($date); ?></div>
							<?php endif; ?>
							<?php if (isset($event['place']) && $event['place']) : ?>
								<div><?php print htmlspecialchars($event['place']); ?></div>
							<?php endif; ?>
							<?php if (isset($event['header']) && $event['header']) : ?>
								<div><?php print htmlspecialchars($event['header']); ?></div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Main individual photos -->
				<?php if (isset($media) && isset($media['data']) && count($media['data']) > 0) :
					$total_width = 0;
					$max_total_width = 780;
					$max_height = 50;
					$more_photos = false;
				?>
					<h2>Photos (<?php print count($media['data']); ?>)</h2>
					<?php
					foreach ($media['data'] as $photo) :
						$image = $commonViewerUtility->getBestMediaItemThumbnail($photo, $max_height, 'height');
						if ($image) :
							$height = min($max_height, $image['height']);
							$ratio = $image['height'] / $height;
							$width = ceil($image['width'] / $ratio);
							$total_width += ($width + 5);
							if ($total_width > $max_total_width) :
								$more_photos = true;
								break;
							endif;

							$name = '';
							if (isset($photo['name'])) :
								$name = $photo['name'];
							endif;
							$large_image = $commonViewerUtility->getBestMediaItemThumbnail($photo, 400, 'width');
					?>
							<img src="<?php print $image['url']; ?>" height="<?php print $height; ?>" title="<?php print htmlspecialchars($name); ?>"
								onclick="OpenFullPhoto('<?php print $large_image['url']; ?>', '<?php print $large_image['width']; ?>', '<?php print $large_image['height']; ?>')"
								style="cursor:pointer; padding-right:5px">
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ($more_photos) : ?>
						<br>and more...
					<?php endif; ?>

					<br>
					<a href="javascript:ShowFaces('<?php print $individual["id"]; ?>')"><b>Show faces</b></a>
				<?php endif; ?>

				<!-- Main individual notes -->
				<?php if (isset($notes) && isset($notes['data']) && count($notes['data']) > 0) : ?>
					<h2>Notes</h2>
					<?php foreach ($notes['data'] as $note) : ?>
						<div class="item"><?php print htmlspecialchars($note['text']); ?></div>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Main individual citations -->
				<?php if (isset($citations) && isset($citations['data']) && count($citations['data']) > 0) : ?>
					<h2>Citations</h2>
					<?php
					foreach ($citations['data'] as $citation) :
					?>
						<div class="item">
							<div>Source: <?php print htmlspecialchars($citation['source']['name']); ?></div>
							<?php if (!empty($citation['page'])) : ?>
								<div>Page/URL: <?php print htmlspecialchars($citation['page']); ?><div>
							<?php endif; ?>
							<?php if (!empty($citation['confidence'])) : ?>
								<div>Confidence: <?php print htmlspecialchars($citation['confidence']); ?><div>
							<?php endif; ?>
							<?php if (!empty($citation['text'])) : ?>
								<div><?php print htmlspecialchars($citation['text']); ?></div>
							<?php endif; ?>
							</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Main individual immediate family -->
				<?php if ($immediateFamily) :
					$immediateFamilyIndex = 1;
				?>
					<h2>Immediate family</h2>
					<table cellspacing="0" cellpadding="0" border="0">
					<?php
					foreach ($immediateFamily as $relative) :
						$relativeProfileUrl = $currentUrl . '?individualId=' . $relative['id'];
						$relativePersonalPhotoImage = null;
                        if (isset($relative['personal_photo']['id'], $personalPhotos[$relative['personal_photo']['id']])) {
                            $relativePersonalPhoto = $personalPhotos[$relative['personal_photo']['id']];
                            $relativePersonalPhotoImage = $commonViewerUtility->getBestMediaItemThumbnail($relativePersonalPhoto, 30, 'width');
                        }
                        if ($relativePersonalPhotoImage) {
                            $photoData = '<a href="' . $relativeProfileUrl . '"><img src="' . $relativePersonalPhotoImage['url'] . '" width="' . min($relativePersonalPhotoImage['width'], 30) . '"></a>';
                        } else {
                            $photoData = '&nbsp;';
                        }
						if ($immediateFamilyIndex % 3 == 1) : ?>
						<tr>
						<?php endif; ?>

							<td valign="top" width="30" style="padding-bottom: 26px;">
							<!-- Photo -->
								<?php print $photoData; ?>
							</td>
							<td valign="top" width="33.33%" style="padding: 0 9px 26px;">
								<!-- Name and relationship -->
								<div><a href="<?php print $relativeProfileUrl; ?>"><b><?php print htmlspecialchars($relative['name']); ?></b></a></div>
								<?php if ($relative['relationship_description']) : ?>
									</div><?php print htmlspecialchars($relative['relationship_description']); ?></div>
								<?php endif; ?>
							</td>

						<?php if ($immediateFamilyIndex % 3 == 0) : ?>
						</tr>
						<?php endif; ?>
						<?php $immediateFamilyIndex++; ?>
					<?php endforeach; ?>
					<?php if ($immediateFamilyIndex % 3 != 0) : ?>
						<td></td>
					<?php endif; ?>
					</table>
				<?php endif; ?>
			</td>
		</tr>
	</table>
<?php
	}
?>

</body>
</html>
