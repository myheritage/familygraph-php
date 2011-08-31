<?php

require_once '../CommonUtility.php';

/**
 * Faces sample app - this application shows all tagged faces of a certain person.
 * In order to get all tags of a certain person it uses the 'tags' connection on Individual object. Afterwards,
 * it gets all the photos in which the person is tagged using a multiple-ids request.
 *
 */
class FacesSampleApp
{
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
     * The sample individual id - in use as a fallback
     *
     * @var string|null
     */
    private $sampleIndividualId;

    /**
     * The request individual id
     *
     * @var string|null
     */
    private $requestIndividualId;

    /**
     * The individual that will be displayed
     * 
     * @var $individual array
     */
    private $individual;

    /**
     * Constructs a new faces sample app object
     *
     * @param FamilyGraph $familyGraph the family graph
     * @param string|null $sampleIndividualId the sample individual id (optional)
     * @param string|null $requestIndividualId the request individual id (optional)
     *
     */
    public function __construct(FamilyGraph $familyGraph, $sampleIndividualId = null, $requestIndividualId = null)
    {
        $this->setFamilyGraph($familyGraph);
        $this->setSampleIndividualId($sampleIndividualId);
        $this->setRequestIndividualId($requestIndividualId);
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
     * Sets the sample individual id
     *
     * @param string|null $sampleIndividualId the sample individual id
     */
    private function setSampleIndividualId($sampleIndividualId)
    {
        $this->sampleIndividualId = $sampleIndividualId;
    }

    /**
     * Gets the sample individual id
     *
     * @return string|null the sample individual id
     */
    private function getSampleIndividualId()
    {
        return $this->sampleIndividualId;
    }

    /**
     * Sets the request individual id
     *
     * @param string|null $requestIndividualId the request individual id
     */
    private function setRequestIndividualId($requestIndividualId)
    {
        $this->requestIndividualId = $requestIndividualId;
    }

    /**
     * Gets the request individual id
     *
     * @return string|null the request individual id
     */
    private function getRequestIndividualId()
    {
        return $this->requestIndividualId;
    }

    /**
     * Determines which individual will be used
     * 1. If individual id was specified use it, otherwise
     * 2. Try to use the default individual for the current logged in user, otherwise
     * 3. Use the sample individual id
     *
     * @throws FamilyGraphException
     * @return string the chosen individual id
     */
    private function getIndividualId()
    {
        // 1. If individual id was specified use it
        $requestIndividualId = $this->getRequestIndividualId();
        if ($requestIndividualId) {
            return $requestIndividualId;
        }

        // 2. Try to use the default individual for the current logged in user
        $defaultIndividualId = $this->getCommonUtility()->getDefaultIndividualId();
        if ($defaultIndividualId) {
            return $defaultIndividualId;
        }

        // 3. Use the sample individual id
        return $this->getSampleIndividualId();
    }

    /**
     * Gets the individual
     *
     * @throws FamilyGraphException
     * @return array the individual
     */
    private function getIndividual()
    {
        if (!isset($this->individual)) {
            $individualId = $this->getIndividualId();
            $this->individual = $this->getFamilyGraph()->api($individualId, array('fields' => 'tags'));
        }

        return $this->individual;
    }

    /**
     * Gets a list of the individual tagged faces
     * Each element will hold a reference to the photo and to the tag
     *
     * @throws FamilyGraphException
     * @return array a list of the individual tagged faces
     */
    private function getTaggedFaces()
    {
        // get the individual tags
        $individual = $this->getIndividual();
        if (!isset($individual['tags']['data']) || empty($individual['tags']['data'])) {
            // no tags for the individual
            return array();
        }
        $tags = $individual['tags']['data'];
        
        // iterate over the tags and aggregate the only the photos among the media items
        $photoIds = array();
        foreach ($tags as $tag) {
            $mediaItemId = $tag['media_item']['id'];
            $mediaItemType = $this->getCommonUtility()->getMediaItemType($mediaItemId);
            if ($mediaItemType !== 'photo') {
                // skip non-photos
                continue;
            }
            
            $photoIds[] = $mediaItemId;
        }
        if (empty($photoIds)) {
            // the individual is not tagged in any photos
            return array();
        }
        
        // get the photos
        $photos = $this->getFamilyGraph()->api($photoIds);
        if (empty($photos)) {
            // failed to get the photos
            return array();
        }

        // prepare the results
        $results = array();
        foreach ($tags as $tag) {
            $mediaItemId = $tag['media_item']['id'];
            if (!isset($photos[$mediaItemId])) {
                // skip tags for which we don't have media item
                continue;
            }
            $photo = $photos[$mediaItemId];
            $data = array(
                'photo' => $photo,
                'tag'   => $tag,
            );

            $results[] = $data;
        }

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
                'individual'      => $this->getIndividual(),
                'taggedFaces'     => $this->getTaggedFaces(),
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
