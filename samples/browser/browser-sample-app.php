<?php

/**
 * Browser sample app - this application simply sends requests to the server and gets the response.
 * The response is being beautified by the browser response formatter.
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
$path = isset($_GET['path']) ? $_GET['path'] : '';
$samplePath = $config['sampleSiteId'];

$stickyParams = array();

$pathParts = parse_url($path);
if (!empty($pathParts['query'])) {
    $pathQueryParams = array();
    parse_str($pathParts['query'], $pathQueryParams);
    foreach (array('lang', 'bearer_token') as $param) {
        if (isset($pathQueryParams[$param])) {
            $stickyParams[$param] = $pathQueryParams[$param];
        }
    }
}

// Initialize browser response formatter
require_once 'BrowserResponseFormatter.php';
$browserResponseFormatter = new BrowserResponseFormatter();
$browserResponseFormatter->setStickyParams($stickyParams);

// Initialize the sample application which is responsible to get all the relevant data
require_once 'BrowserSampleApp.php';
$browserSampleApp = new BrowserSampleApp($familyGraph, $samplePath, $path);
$sampleAppData = $browserSampleApp->getData();
$loginUrl = $sampleAppData['loginUrl'];
$isUserLoggedIn = $sampleAppData['isUserLoggedIn'];
$currentUserName = $sampleAppData['currentUserName'];
$logoutUrl = $_SERVER['PHP_SELF'] . '?logout';
if (!isset($sampleAppData['exception'])) {
    $path = $sampleAppData['path'];
    $results = $sampleAppData['results'];
    $htmlSnippet = $browserResponseFormatter->formatResponse($results);
} else {
    /** @var $exception FamilyGraphException */
    $exception = $sampleAppData['exception'];
    $rawData = $exception->getRawData();
    if (isset($rawData)) {
        $htmlSnippet = $browserResponseFormatter->formatResponse($rawData);
    } else {
        $htmlSnippet = htmlspecialchars($exception->getType() . ' ' . $exception->getMessage());
    }
}

?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Family Graph Browser App</title>
    <style type="text/css">
        body
		{
			margin: 0;
			font-family: arial;
			font-size: 13px;
			color: #333;
			line-height: 18px;
        }
		#container
		{
			font-family: sans-serif;
			line-height: 15px;
			margin-left: 15px;
		}
        .prop
		{
            font-weight: bold;
        }
        .null
		{
            color: red;
        }
        .bool
		{
            color: blue;
        }
        .num
		{
            color: blue;
        }
        .string
		{
            color: green;
            white-space: pre-wrap;
        }
    </style>

    <script src="https://secure.myheritage.com/FP/Assets/Cache/Tagshot/prototype/prototype_v1279637188.js" type="text/javascript"></script>
    <script language="javascript">
        /**
         * On id clicked
         *
         * @param url the url
         */
        function idClicked(url)
        {
            $('path').value = url;
            $('browser_form').submit();
        }

        /**
         * Opens the results in a new window
         *
         */
        function openResultsInNewWindow()
        {
            var path = $('path').value;
            var url = buildLinkById(path) + window.location.search;
            window.open(url);
        }
    </script>
</head>
<body>

<?php

if (!$isUserLoggedIn) { ?>
	<div style="padding: 6px 0; background-color: #F8F8F8; padding-left: 15px">
		<p>You are not logged in, so running sample app using the sample developers site.<br/>
		To run the sample app on your own family site, please <a href="<?php print $loginUrl; ?>">log in</a>
	</div>
<?php } else { ?>
	<div style="padding: 6px 0; background-color: #F8F8F8; padding-left: 15px">
		You are logged in as <?php print htmlspecialchars($currentUserName); ?>
		&nbsp;(<a href="<?php print $logoutUrl; ?>">Logout</a>)
	</div>
<?php } ?>
	<!-- Separator -->
	<hr style="margin-top: 0;">

	<div id="container">
	<form name="browser_form" id="browser_form" method="get">
		<label for="path">Path: </label>
		<input id="path" name="path" type="text" value="<?php print htmlspecialchars($path); ?>" style="width: 350px;">
		<input type="submit" name="run" value="Run">
	</form>
    <div id="json"><?php print $htmlSnippet; ?></div>
	</div>
</body>
</html>
