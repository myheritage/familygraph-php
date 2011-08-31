<?php
/**
 * Copyright 2011 MyHeritage, Ltd.
 *
 * The Family Graph SDK is based on the Facebook PHP SDK:
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

if (!function_exists('curl_init')) {
    throw new Exception('FamilyGraph needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new Exception('FamilyGraph needs the JSON PHP extension.');
}

/**
 * Provides access to the MyHeritage Family Graph API. This class provides
 * a majority of the functionality needed, but the class is abstract
 * because it is designed to be sub-classed.  The subclass must
 * implement the three abstract methods listed at the bottom of
 * the file.
 *
 */
abstract class BaseFamilyGraph
{
    /**
     * Version.
     */
    const VERSION = '0.0.1';

    /**
     * Default options for curl.
     */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_ENCODING => '', // Empty string means all supported encodings
        CURLOPT_USERAGENT => 'familygraph-php-0.0.1',
    );

    /**
     * List of query parameters that get automatically dropped when rebuilding
     * the current URL.
     */
    protected static $DROP_QUERY_PARAMS = array(
        'code',
        'state',
        'error',
        'error_description'
    );

    /**
     * Maps aliases to MyHeritage domains.
     */
    public static $DOMAIN_MAP = array(
        'familygraph'   => 'https://familygraph.myheritage.com/',
        'accounts'      => 'https://accounts.myheritage.com/',
        'www'           => 'https://www.myheritage.com/',
    );

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The ID of the MyHeritage user, or null if the user is logged out.
     *
     * @var string
     */
    protected $userId;

    /**
     * A CSRF state variable to assist in the defense against CSRF attacks.
     */
    protected $state;

    /**
     * The OAuth access token received in exchange for a valid authorization
     * code.  null means the access token has yet to be determined.
     *
     * @var string
     */
    protected $accessToken = null;

    public function __construct($clientId, $clientSecret)
    {
        $this->setClientId($clientId);
        $this->setClientSecret($clientSecret);

        $state = $this->getPersistentData('state');
        if (!empty($state)) {
            $this->state = $this->getPersistentData('state');
        }
    }

    /**
     * Set the Application ID.
     *
     * @param string $appId The Application ID
     * @return BaseFamilyGraph
     */
    public function setClientId($appId)
    {
        $this->clientId = $appId;
        return $this;
    }

    /**
     * Get the client ID.
     *
     * @return string The client ID
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Set the API Secret.
     *
     * @param string $apiSecret The API Secret
     * @return BaseFamilyGraph
     */
    public function setClientSecret($apiSecret)
    {
        $this->clientSecret = $apiSecret;
        return $this;
    }

    /**
     * Get the client secret.
     *
     * @return string The client secret
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * Sets the access token for api calls.  Use this if you get
     * your access token by other means and just want the SDK
     * to use it.
     *
     * @param string $access_token an access token.
     * @return BaseFamilyGraph
     */
    public function setAccessToken($access_token)
    {
        $this->accessToken = $access_token;
        return $this;
    }

    /**
     * Determines the access token that should be used for API calls.
     * The first time this is called, $this->accessToken is set equal
     * to either a valid user access token, or it's set to the application
     * access token if a valid user access token wasn't available.  Subsequent
     * calls return whatever the first call returned.
     *
     * @return string The access token
     */
    public function getAccessToken()
    {
        if ($this->accessToken !== null) {
            // we've done this already and cached it.  Just return.
            return $this->accessToken;
        }

        $userAccessToken = $this->getUserAccessToken();
        if ($userAccessToken) {
            $this->setAccessToken($userAccessToken);
        }

        return $this->accessToken;
    }

    /**
     * Determines and returns the user access token, using
     * the authorization code if present.  The intent is to
     * return a valid user access token, or null if one is determined
     * to not be available.
     *
     * @return string A valid user access token, or null if one
     *                could not be determined.
     */
    protected function getUserAccessToken()
    {
        $code = $this->getCode();
        if ($code && $code != $this->getPersistentData('code')) {
            $accessToken = $this->getAccessTokenFromCode($code);
            if ($accessToken) {
                $this->setPersistentData('code', $code);
                $this->setPersistentData('access_token', $accessToken);
                return $accessToken;
            }

            // code was bogus, so everything based on it should be invalidated.
            $this->clearAllPersistentData();
            return null;
        }

        // as a fallback, just return whatever is in the persistent
        // store, knowing nothing explicit (authorization code, etc)
        // was present to shadow it (or we saw a code in $_REQUEST,
        // but it's the same as what's in the persistent store)
        return $this->getPersistentData('access_token');
    }

    /**
     * Get the user ID of the connected user, or null
     * if the MyHeritage user is not connected.
     *
     * @return string The user ID if available.
     */
    public function getUserId()
    {
        if ($this->userId !== null) {
            // we've already determined this and cached the value.
            return $this->userId;
        }

        $this->userId = $this->getUserFromAvailableData();

        return $this->userId;
    }

    /**
     * Determines the connected user by considering an authorization code,
     * and then falling back to any persistent store storing the user.
     *
     * @return string The id of the connected MyHeritage user,
     *                 or empty string if no such user exists.
     */
    protected function getUserFromAvailableData()
    {
        $userId = $this->getPersistentData('user_id');
        $persistedAccessToken = $this->getPersistentData('access_token');

        // use access_token to fetch user id if we have a user access_token, or if
        // the cached access token has changed.
        $accessToken = $this->getAccessToken();
        if ($accessToken && !($userId && $persistedAccessToken === $accessToken)) {
            $userId = $this->getUserFromAccessToken();
            if ($userId) {
                $this->setPersistentData('user_id', $userId);
            } else {
                $this->clearAllPersistentData();
            }
        }

        return $userId;
    }

    /**
     * Get a Login URL for use with redirects. By default, full page redirect is
     * assumed. If you are using the generated URL with a window.open() call in
     * JavaScript, you can pass in display=popup as part of the $params.
     *
     * The parameters:
     * - redirect_uri: the url to go to after a successful login
     * - scope: comma separated list of requested extended perms
     *
     * @param array $params Provide custom parameters
     * @return string The URL for the login flow
     */
    public function getLoginUrl($params = array())
    {
        $this->establishCSRFTokenState();
        $currentUrl = $this->getCurrentUrl();
        return $this->getUrl(
            'accounts',
            'oauth2/authorize',
            array_merge(
                array(
                     'response_type' => 'code',
                     'client_id' => $this->getClientId(),
                     'redirect_uri' => $currentUrl, // possibly overwritten
                     'state' => $this->state
                ),
                $params
            )
        );
    }

    /**
     * Invoke the Family Graph API.
     *
     * @param string $path The path (required)
     * @param array $params The query/post data
     *
     * @return mixed The decoded response object
     * @throws FamilyGraphApiException
     */
    public function api($path, $params = array())
    {
        // if array of object ids send it using the ids parameter
        if (is_array($path)) {
            $params['ids'] = implode(',', $path);
            $path = '';
        } else {
            // single id - in case it holds query string parse it and add it to the params array
            if (strpos($path, '?') !== false) {
                list($path, $pathQuery) = explode('?', $path, 2);
                $pathQueryArray = array();
                parse_str($pathQuery, $pathQueryArray);
                foreach ($pathQueryArray as $key => $value) {
                    // add only variables which do not exist in the params already
                    if (!isset($params[$key])) {
                        $params[$key] = $value;
                    }
                }
            }
        }

        $taken = -microtime(true);
        $result = $this->_oauthRequest(
                $this->getUrl('familygraph', $path),
                $params
        );
		$taken += microtime(true);
//		echo "$taken\tCall to $path with params = ", print_r($params), "<br>\n";

        return $result;
    }

    /**
     * Get the authorization code from the query parameters, if it exists,
     * and otherwise return false to signal no authorization code was
     * discoverable.
     *
     * @return mixed The authorization code, or null if the authorization
     *               code could not be determined.
     */
    protected function getCode()
    {
        if (isset($_REQUEST['code'])) {
            if ($this->state !== null
                    && isset($_REQUEST['state'])
                    && $this->state === $_REQUEST['state']
            ) {
                // CSRF state has done its job, so clear it
                $this->state = null;
                $this->clearPersistentData('state');
                return $_REQUEST['code'];
            } else {
                //self::errorLog('CSRF state token does not match one provided.');
                return null;
            }
        }

        return null;
    }

    /**
     * Retrieves the UID with the understanding that
     * $this->accessToken has already been set and is
     * seemingly legitimate.  It relies on MyHeritage's Family Graph API
     * to retrieve user information and then extract
     * the user ID.
     *
     * @return string Returns the UID of the MyHeritage user, or empty string
     *                 if the MyHeritage user could not be determined.
     */
    protected function getUserFromAccessToken()
    {
        try {
            $user_info = $this->api('me');
            return $user_info['id'];
        } catch (FamilyGraphException $ex) {
            return '';
        }
    }

    /**
     * Lays down a CSRF state token for this process.
     *
     * @return void
     */
    protected function establishCSRFTokenState()
    {
        if ($this->state === null) {
            $this->state = md5(uniqid(mt_rand(), true));
            $this->setPersistentData('state', $this->state);
        }
    }

    /**
     * Retrieves an access token for the given authorization code
     * (previously generated from myheritage.com on behalf of
     * a specific user).  The authorization code is sent to accounts.myheritage.com
     * and a legitimate access token is generated provided the access token
     * and the user for which it was generated all match, and the user is
     * either logged in to MyHeritage or has granted an offline access permission.
     *
     * @param string $code An authorization code.
     * @return mixed An access token exchanged for the authorization code, or
     *               false if an access token could not be generated.
     */
    protected function getAccessTokenFromCode($code)
    {
        if (empty($code)) {
            return false;
        }

        try {
            // need to circumvent json_decode by calling _oauthRequest
            // directly, since response isn't JSON format.
            $result = $this->makeRequest(
                $this->getUrl('accounts', 'oauth2/token'),
                'POST',
                array(
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->getClientId(),
                    'client_secret' => $this->getClientSecret(),
                    'redirect_uri' => $this->getCurrentUrl(),
                    'code' => $code
                )
            );
        } catch (FamilyGraphException $ex) {
            // most likely that user very recently revoked authorization.
            // In any event, we don't have an access token, so say so.
            self::errorLog("Failed to convert code to token: " . $ex->__toString());
            return false;
        }

        if (!isset($result['access_token'])) {
            self::errorLog("Could not exchange code for access token. Result: '" . print_r($result, true) . "'");
            return false;
        }
        
        return $result['access_token'];
    }

    /**
     * Make a OAuth Request.
     *
     * @param string $url The path (required)
     * @param array $params The query/post data
     *
     * @return string The decoded response object
     * @throws FamilyGraphApiException
     */
    protected function _oauthRequest($url, $params)
    {
        $headers = array();

        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        // json_encode all params values that are not strings
        foreach ($params as $key => $value) {
            if (!is_string($value)) {
                $params[$key] = json_encode($value);
            }
        }

        return $this->makeRequest($url, 'GET', $params, $headers);
    }

	private $curl = null;

    /**
     * Makes an HTTP request. This method can be overridden by subclasses if
     * developers want to do fancier things or use something other than curl to
     * make the request.
     *
     * @param string $url The URL to make the request to
     * @param string $method GET or post
     * @param array $params The parameters to use
     * @param array $headers HTTP headers to send
     *
     * @throws FamilyGraphException
     * @return string The response text
     */
    protected function makeRequest($url, $method = 'GET', $params = array(), $headers = array())
    {
        //var_dump($url); var_dump($params);
		if ($this->curl == null) {
			$this->curl = curl_init();
		}
		$ch = $this->curl;
		
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');

//        $debugHandle = fopen('/tmp/fgsdk.out', 'a');
//        curl_setopt($ch, CURLOPT_STDERR, $debugHandle);
//        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $opts = self::$CURL_OPTS;

        if (count($params) > 0) {
            $queryString = http_build_query($params, null, '&');
            if ($method == 'GET') {
                $url .= ((strpos($url, '?') === false) ? '?' : '&') . $queryString;
            } else {
                $opts[CURLOPT_POSTFIELDS] = $queryString;
            }
        }

        if (count($headers) > 0) {
//            fwrite($debugHandle, print_r($headers, true));
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

		//echo "cURL $url with headers:" , print_r($headers, true), "<br>\n";

        $opts[CURLOPT_URL] = $url;

//        // disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
//        // for 2 seconds if the server does not support this header.
//        if (isset($opts[CURLOPT_HTTPHEADER])) {
//            $existing_headers = $opts[CURLOPT_HTTPHEADER];
//            $existing_headers[] = 'Expect:';
//            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
//        } else {
//            $opts[CURLOPT_HTTPHEADER] = array('Expect:');
//        }

        curl_setopt_array($ch, $opts);
        $output = curl_exec($ch);
        
        if ($output === false) {
            $ex = new FamilyGraphException(curl_error($ch), 'CurlError', curl_errno($ch));
            curl_close($ch);
            throw $ex;
        }
        //curl_close($ch);

        $result = json_decode($output, true);
        if ($result === null) {
            throw new FamilyGraphException("Failed to parse response: $output", 'JsonError');
        }

        // results are returned, errors are thrown
        if (is_array($result) && isset($result['error'])) {
            $type = $result['error'];
            $message = isset($result['error_description']) ? $result['error_description'] : 'Unknown error';

            if ($type == 'invalid_token') {
                // The access token is not valid. Clear the persistent store
                $this->setAccessToken(null);
                $this->userId = 0;
                $this->clearAllPersistentData();
            }

            $ex = new FamilyGraphException($message, $type);
            $ex->setRawData($result);
            throw $ex;
        }

        return $result;
    }

    /**
     * Build the URL for given domain alias, path and parameters.
     *
     * @param $name string The name of the domain
     * @param $path string Optional path (without a leading slash)
     * @param $params array Optional query parameters
     *
     * @return string The URL for the given parameters
     */
    protected function getUrl($name, $path = '', $params = array())
    {
        $url = self::$DOMAIN_MAP[$name];
        if ($path) {
            if ($path[0] === '/') {
                $path = substr($path, 1);
            }
            $url .= $path;
        }
        if ($params) {
            $url .= '?' . http_build_query($params, null, '&');
        }

        return $url;
    }

    /**
     * Returns the Current URL, stripping it of known FB parameters that should
     * not persist.
     *
     * @return string The current URL
     */
    protected function getCurrentUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'
                ? 'https://'
                : 'http://';
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $parts = parse_url($currentUrl);

        $query = '';
        if (!empty($parts['query'])) {
            // drop known fb params
            $params = explode('&', $parts['query']);
            $retained_params = array();
            foreach ($params as $param) {
                if ($this->shouldRetainParam($param)) {
                    $retained_params[] = $param;
                }
            }

            if (!empty($retained_params)) {
                $query = '?' . implode($retained_params, '&');
            }
        }

        // use port if non default
        $port = isset($parts['port'])
                && (($protocol === 'http://' && $parts['port'] !== 80)
                        || ($protocol === 'https://' && $parts['port'] !== 443))
                ? ':' . $parts['port'] : '';

        // rebuild
        return $protocol . $parts['host'] . $port . $parts['path'] . $query;
    }

    /**
     * Returns true if and only if the key or key/value pair should
     * be retained as part of the query string.  This amounts to
     * a brute-force search of the very small list of MyHeritage-specific
     * params that should be stripped out.
     *
     * @param string $param A key or key/value pair within a URL's query (e.g.
     *                     'foo=a', 'foo=', or 'foo'.
     *
     * @return boolean
     */
    protected function shouldRetainParam($param)
    {
        foreach (self::$DROP_QUERY_PARAMS as $drop_query_param) {
            if (strpos($param, $drop_query_param . '=') === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prints to the error log if you aren't in command line mode.
     *
     * @param string $msg Log message
     */
    protected static function errorLog($msg)
    {
        // disable error log if we are running in a CLI environment
        // @codeCoverageIgnoreStart
        if (php_sapi_name() != 'cli') {
            error_log($msg);
        }
        // uncomment this if you want to see the errors on the page
        //print "FamilyGraph: {$msg}\n";
        // @codeCoverageIgnoreEnd
    }

    /**
     * Each of the following four methods should be overridden in
     * a concrete subclass, as they are in the provided FamilyGraph class.
     * The FamilyGraph class uses PHP sessions to provide a primitive
     * persistent store, but another subclass--one that you implement--
     * might use a database, memcache, or an in-memory cache.
     *
     * @see FamilyGraph
     */

    /**
     * Stores the given ($key, $value) pair, so that future calls to
     * getPersistentData($key) return $value. This call may be in another request.
     *
     * @param string $key
     * @param array $value
     *
     * @return void
     */
    abstract protected function setPersistentData($key, $value);

    /**
     * Get the data for $key, persisted by BaseFamilyGraph::setPersistentData()
     *
     * @param string $key The key of the data to retrieve
     * @param boolean $default The default value to return if $key is not found
     *
     * @return mixed
     */
    abstract protected function getPersistentData($key, $default = null);

    /**
     * Clear the data with $key from the persistent storage
     *
     * @param string $key
     * @return void
     */
    abstract protected function clearPersistentData($key);

    /**
     * Clear all data from the persistent storage
     *
     * @return void
     */
    abstract protected function clearAllPersistentData();
}


/**
 * Thrown when an API call returns an exception.
 */
class FamilyGraphException extends RuntimeException
{
    /**
     * The type of the error
     *
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $rawData;

    public function __construct($message, $type = 'FamilyGraphError', $code = 0)
    {
        $this->type = $type;
        parent::__construct($message, $code);
    }


    /**
     * Returns the type of the error
     * The type might be:
     *  'FamilyGraphError' - Unexpected general error
     *  'JsonError' - The returned response is not a valid JSON
     *  'CurlError' - Curl error
     *  OAuth2 error codes like: 'invalid_token', 'invalid_client', 'missing_token', etc.
     *  REST API return error codes like: 403, 404, etc.
     *
     * @return string|int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * To make debugging easier.
     *
     * @return string The string representation of the error
     */
    public function __toString()
    {
        $str = $this->getType() . ': ';
        if ($this->code != 0) {
            $str .= $this->code . ': ';
        }
        return $str . $this->message;
    }

    /**
     * @param array $rawData
     */
    public function setRawData($rawData)
    {
        $this->rawData = $rawData;
    }

    /**
     * @return array
     */
    public function getRawData()
    {
        return $this->rawData;
    }
}
