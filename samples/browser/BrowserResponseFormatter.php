<?php

/**
 * Browser response formatter
 * The formatter takes the JSON results of a request and beautifies by creating html snippet with links to objects
 * references, links instead of just urls (like image urls), etc.
 * 
 */
class BrowserResponseFormatter
{
    /**
     * The browser app url
     *
     * @var null|string
     */
    private $browserAppUrl;

    /**
     * Indicates whether to create links while formatting the response or not
     * 
     * @var bool
     */
    private $createLinks;

    /**
     * @var string
     */
    private $linkTarget;

    /**
     * Array of parameters that need to be appended to every link
     *
     * @var array
     */
    private $stickyParams = array();

    /**
     * Constructs a new response formatter
     *
     * @param string $browserAppUrl the browser application url
     * @param bool $createLinks indicates whether to create links while formatting the response or not
     */
    public function __construct($browserAppUrl = null, $createLinks = true)
    {
        $this->setBrowserAppUrl($browserAppUrl);
        $this->setCreateLinks($createLinks);
    }

    /**
     * Sets the browser application url
     *
     * @param null|string $browserAppUrl the browser application url
     * @return void
     */
    private function setBrowserAppUrl($browserAppUrl)
    {
        $this->browserAppUrl = $browserAppUrl;
    }

    /**
     * Returns the browser application url
     *
     * @return null|string the browser application url
     */
    private function getBrowserAppUrl()
    {
        return $this->browserAppUrl;
    }

    /**
     * Sets the create links
     *
     * @param bool $createLinks indicates whether to create links while formatting the response or not
     * @return void
     */
    public function setCreateLinks($createLinks)
    {
        $this->createLinks = $createLinks;
    }

    /**
     * Gets the create links
     *
     * @return bool true if links will be created while formatting the response, false otherwise
     */
    public function getCreateLinks()
    {
        return $this->createLinks;
    }

    /**
     * @param $linkTarget
     */
    public function setLinkTarget($linkTarget)
    {
        $this->linkTarget = $linkTarget;
    }

    /**
     * Set the parameters that need to be appended to every link
     *
     * @param $stickyParams
     * @return void
     */
    public function setStickyParams($stickyParams)
    {
        $this->stickyParams = $stickyParams;
    }
    
    /**
     * Handles the ajax response
     *
     * @param Object $jsonObject The JSON object to format
     * @return string the formatted response
     */
    public function formatResponse($jsonObject)
    {
        return '<pre>' . $this->stringifyJsonObject($jsonObject) . '</pre>';
    }

    /**
     * Stringifies the given JSON object
     *
     * @param Object $jsonObject the JSON object
     *
     * @return string the stringified JSON object
     */
    private function stringifyJsonObject($jsonObject)
    {
        return $this->formatJsonObject('', $jsonObject, 0, false);
    }

    /**
     * Formats a JSON object
     *
     * @param string $jsonKey the key
     * @param mixed $jsonValue the value
     * @param int $indentation the indentation
     * @param bool $isConnection indicates whether the field is a connection or not
     * 
     * @return string the formatted JSON object
     */
    private function formatJsonObject($jsonKey, $jsonValue, $indentation, $isConnection)
    {
        $resultsString = '';
        $newline = '<br>';
        $space = "\t";

        if (is_array($jsonValue)) {
            $results = array();
            $isArray = isset($jsonValue[0]);
            $value = null;
            $element = null;

            $isConnection = ($jsonKey === 'connections');
            if ($isArray) {
                foreach ($jsonValue as $value) {
                    $element = str_repeat($space, $indentation + 1) . $this->formatJsonObject('', $value, $indentation + 1, $isConnection);
                    $results[] = $element;
                }
            } else {
                foreach ($jsonValue as $key => $value) {
                    $element = str_repeat($space, $indentation + 1) . $this->formatKey($key) . ': ' . $this->formatJsonObject($key, $value, $indentation + 1, $isConnection);
                    $results[] = $element;
                }
            }

            $resultsString = $newline . implode($results, ',' . $newline) . $newline . str_repeat($space, $indentation);

            if ($isArray) {
                $resultsString = '[' . $resultsString . ']';
            } else {
                $resultsString = '{' . $resultsString . '}';
            }

            return $resultsString;
        }

        // Format the $value
        if ($jsonKey == 'id' || $jsonKey == 'handle' || $jsonKey == 'previous' || $jsonKey == 'next' || $isConnection) {
            $jsonValue = $this->formatId($jsonValue);
        } else if (strpos($jsonKey, 'url') !== false || $jsonKey === 'link') {
            $jsonValue = $this->formatMediaUrl($jsonValue);
        } else if (is_string($jsonValue)) {
            $jsonValue = htmlspecialchars($jsonValue);
        }
        
        $resultsString .= $this->formatValue($jsonValue);

        return $resultsString;
    }

    /**
     * Formats the key
     *
     * @param string $key the key
     *
     * @return string the formatted key
     */
    private function formatKey($key)
    {
        return '<span class="prop">' . $key . '</span>';
    }

    /**
     * Formats the value
     *
     * @param string $value the value
     *
     * @return string the formatted value
     */
    private function formatValue($value)
    {
        if ($value === null) {
            $formattedValue = '<span class="null">' . $value . '</span>';
        } else if (is_string($value)) {
            $formattedValue = '<span class="string">"' . $value . '"</span>';
        } else if (is_bool($value)) {
            $formattedValue = '<span class="bool">' . ($value ? 'true' : 'false') . '</span>';
        } else {
            $formattedValue = '<span class="num">' . $value . '</span>';
        }
        
        return $formattedValue;
    }

    /**
     * Formats the object id
     *
     * @param string $id the object id
     *
     * @return string the formatted object id
     */
    private function formatId($id)
    {
        if (!$this->getCreateLinks()) {
            return $id;
        }

        $path = $this->appendStickyParams($id);

        $url = $this->browserAppUrl . "?path=" . rawurlencode($path);

        $attrs = array();
        $attrs[] = 'href="' . $url . '"';
        if (!empty($this->linkTarget)) {
            $attrs[] = 'target="' . $this->linkTarget . '"';
        }
        
        return '<a ' . implode($attrs, ' ') . '>' . $id . '</a>';
    }

    private function appendStickyParams($path)
    {
        if (count($this->stickyParams) == 0) {
            return $path;
        }

        $path .= (strpos($path, '?') !== false) ? '&' : '?';
        $path .= http_build_query($this->stickyParams);

        return $path;
    }

    /**
     * Returns the given media url formatted
     *
     * @param string $mediaUrl the media url
     *
     * @return string the given media url formatted
     */
    private function formatMediaUrl($mediaUrl)
    {
        return '<a href="' . $mediaUrl . '" target="_blank">' . $mediaUrl . '</a>';
    }
}
