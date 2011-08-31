<?php

require_once '../CommonUtility.php';

/**
 * Browser sample app - this application simply sends requests to the server and gets the response.
 * The response is being beautified by the browser response formatter.
 * 
 */
class BrowserSampleApp {

    /**
     * The family graph
     *
     * @var FamilyGraph
     */
    private $familyGraph;

    /**
     * The common utility
     *
     * @var CommonUtility
     */
    private $commonUtility;

    /**
     * The sample path
     *
     * @var string
     */
    private $samplePath;

    /**
     * The path
     *
     * @var string
     */
    private $path;

    /**
     * The actual path
     *
     * @var string
     */
    private $actualPath;

    /**
     * Constructs a new browser sample app object
     *
     * @param FamilyGraph $familyGraph the family graph
     * @param string $samplePath the sample path
     * @param string $path the path
     *
     */
    public function __construct(FamilyGraph $familyGraph, $samplePath = '', $path = '')
    {
        $this->setFamilyGraph($familyGraph);
        $this->setSamplePath($samplePath);
        $this->setPath($path);
    }

    /**
     * Sets the family graph
     *
     * @param FamilyGraph $familyGraph the family graph
     */
    private function setFamilyGraph(FamilyGraph $familyGraph)
    {
        $this->familyGraph = $familyGraph;
    }

    /**
     * Gets the family graph
     *
     * @return FamilyGraph the family graph
     */
    private function getFamilyGraph()
    {
        return $this->familyGraph;
    }

    /**
     * Gets the common utility
     *
     * @return CommonUtility the common utility
     */
    private function getCommonUtility()
    {
        if (!isset($this->commonUtility)) {
            $this->commonUtility = new CommonUtility($this->getFamilyGraph());
        }

        return $this->commonUtility;
    }

    /**
     * Sets the sample path
     *
     * @param string $samplePath the sample path
     *
     * @return void
     */
    private function setSamplePath($samplePath)
    {
        $this->samplePath = $samplePath;
    }

    /**
     * Gets the sample path
     *
     * @return string the sample path
     */
    private function getSamplePath()
    {
        return $this->samplePath;
    }

    /**
     * Sets the path
     *
     * @param string $path the path
     *
     * @return void
     */
    private function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Gets the path
     *
     * @return string the path
     */
    private function getPath()
    {
        return $this->path;
    }

    /**
     * Builds the actual path
     * 1. If a path was specified use it, otherwise
     * 2. If a user is logged in use 'me' as the path, otherwise
     * 3. Use the sample path
     *
     * @return string the actual path
     */
    private function getActualPath()
    {
        if (!isset($this->actualPath)) {
            $this->actualPath = '';
            
            $path = $this->getPath();
            if ($path) {
                // if a path was specified use it
                $this->actualPath = $path;
            } else {
                // check if the a user is logged in
                if ($this->getCommonUtility()->getIsUserLoggedIn()) {
                    $this->actualPath = 'me';
                } else {
                    $this->actualPath = $this->getSamplePath();
                }
            }
        }
        
        return $this->actualPath;
    }

    /**
     * Run the request and get the results
     *
     * @throws FamilyGraphException
     * @return array the results
     */
    private function getResults()
    {
        $path = $this->getActualPath();
        $params = array('metadata' => 1);
        $results = $this->getFamilyGraph()->api($path, $params);
        return $results;
    }
    
    /**
     * Returns all the data that is needed by the view to render the sample
     *
     * @return array
     */
    public function getData()
    {
        try {
            $data = array(
                'loginUrl'        => $this->getFamilyGraph()->getLoginUrl(),
                'isUserLoggedIn'  => $this->getCommonUtility()->getIsUserLoggedIn(),
                'currentUserName' => $this->getCommonUtility()->getCurrentUserName(),
                'path'            => $this->getActualPath(),
                'results'         => $this->getResults(),
            );
        } catch (FamilyGraphException $ex) {
            $data = array(
                'loginUrl'        => $this->getFamilyGraph()->getLoginUrl(),
                'isUserLoggedIn'  => $this->getCommonUtility()->getIsUserLoggedIn(),
                'currentUserName' => $this->getCommonUtility()->getCurrentUserName(),
                'exception'       => $ex,
            );
        }

        return $data;
    }
}