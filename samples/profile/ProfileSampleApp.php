<?php

require_once '../CommonUtility.php';

/**
 * Profile sample app - this application shows data on a profile (Individual).
 * An Individual object contains lots of varied data and this application shows only a part of it including:
 * personal photo, events, photos where the person is tagged in, notes, citations and immediate family.
 * At first, the application has to decide which Individual will be displayed. If an Individual is specified,
 * it uses it otherwise it tries to use one of a root individuals in one of the current logged in user sites.
 * Afterwards, it asks for the Individual relevant data using a request with specified fields like: birth_date,
 * death_date, personal_photo, events and immediate_family. Once it has the immediate family, it aggregates the
 * personal photo ids and uses a multiple-ids request to get all the data of these photos.
 * 
 */
class ProfileSampleApp
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
     * The root individuals in all the trees of all the current user sites
     * 
     * @var array
     */
    private $rootIndividualsInAllTrees;

    /**
     * An array of the individual immediate family
     *
     * @var array
     */
    private $immediateFamily;

    /**
     * An associative array with photo id as the key and the photo data as value
     *
     * @var array
     */
    private $personalPhotos;

    /**
     * Constructs a new faces sample app object
     *
     * @param FamilyGraph $familyGraph the family graph
     * @param string $sampleIndividualId the sample individual id
     * @param string|null $requestIndividualId the request individual id (optional)
     *
     */
    public function __construct(FamilyGraph $familyGraph, $sampleIndividualId, $requestIndividualId = null)
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
     * Gets information about the root individuals in all the trees of all the sites the current user is a member in
     *
     * @throws FamilyGraphException
     * @return array an array where each element holds basic data about a root individual in one of the current user trees
     */
    private function getRootIndividualsInAllTrees()
    {
        if (!isset($this->rootIndividualsInAllTrees)) {
            $this->rootIndividualsInAllTrees = array();

            // get all the site ids the current user is a member in
            $isUserLoggedIn = $this->getCommonUtility()->getIsUserLoggedIn();
            if ($isUserLoggedIn) {
                $memberships = $this->getFamilyGraph()->api('me/memberships');
                if ($memberships && isset($memberships['data']) && count($memberships['data']) > 0) {
                    // aggregate the site ids
                    $siteIds = array();
                    foreach($memberships['data'] as $membership) {
                        $siteIds[] = $membership['site']['id'];
                    }

                    // get all the sites trees
                    $sitesTrees = $this->getFamilyGraph()->api($siteIds, array('fields' => 'trees'));
                    if ($sitesTrees) {
                        // iterate over the trees and extract the root individual from them
                        foreach ($sitesTrees as $siteTrees) {
                            if (!isset($siteTrees['trees']['data']) || !$siteTrees['trees']['data']) {
                                // skip sites with no trees
                                continue;
                            }

                            // iterate over the trees
                            $trees = $siteTrees['trees']['data'];
                            foreach ($trees as $tree) {
                                if (!isset($tree['root_individual'])) {
                                    // skip trees with no root individual
                                    continue;
                                }

                                $rootIndividualData = array(
                                    'siteName'               => $siteTrees['name'],
                                    'treeName'               => $tree['name'],
                                    'treeRootIndividualId'   => $tree['root_individual']['id'],
                                    'treeRootIndividualName' => $tree['root_individual']['name'],
                                );

                                $this->rootIndividualsInAllTrees[] = $rootIndividualData;
                            }
                        }
                    }
                }
            }
        }
        
        return $this->rootIndividualsInAllTrees;
    }

    /**
     * Determines which individual will be used
     * 1. If individual id was specified use it, otherwise
     * 2. Try to use the default individual for the current logged in user, otherwise
     * 3. Try to use one of the root individuals in one of the current user trees, otherwise
     * 4. Use the sample individual id
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

        // 3. Try to use one of the root individuals in one of the current user trees
        $rootIndividualsInAllTrees = $this->getRootIndividualsInAllTrees();
        if (!empty($rootIndividualsInAllTrees)) {
            return $rootIndividualsInAllTrees[0]['treeIndividualId'];
        }

        // 4. Use the sample individual id
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
            $params = array('fields' => 'birth_date,is_alive,death_date,personal_photo,events,media,notes,citations,immediate_family');
            $individual = $this->getFamilyGraph()->api($individualId, $params);

            // filter out media items which are not photos
            if (isset($individual['media'], $individual['media']['data'])) {
                $photos = array();
                $mediaItems = $individual['media']['data'];
                foreach ($mediaItems as $mediaItem) {
                    $mediaItemId = $mediaItem['id'];
                    $mediaItemType = $this->getCommonUtility()->getMediaItemType($mediaItemId);
                    if ($mediaItemType !== 'photo') {
                        // skip non photos (audio / video / document)
                        continue;
                    }
                    
                    $photos[] = $mediaItem;
                }

                $individual['media']['data'] = $photos;
            }

            $this->individual = $individual;
        }
        
        return $this->individual;
    }

    /**
     * Returns all the information we wish to display on the selected individual
     *
     * @throws FamilyGraphException
     * @return array
     */
    private function getImmediateFamily()
    {
        if (!isset($this->immediateFamily)) {
            $this->immediateFamily = array();

            $relativeIds = array();
            $relativeIdToRelationshipDescriptionMapping = array();
            $individual = $this->getIndividual();
            if (isset($individual['immediate_family']['data']) && count($individual['immediate_family']['data']) > 0) {
                // aggregate relative ids
                $immediateFamily = $individual['immediate_family']['data'];
                foreach ($immediateFamily as $relative) {
                    $relativeId = $relative['individual']['id'];
                    $relativeIds[] = $relativeId;
                    $relativeIdToRelationshipDescriptionMapping[$relativeId] = $relative['relationship_description'];
                }
            }

            // get relatives names and personal photo ids
            if (!empty($relativeIds)) {
                $params = array('fields' => 'name,personal_photo');
                $results = $this->getFamilyGraph()->api($relativeIds, $params);
                if ($results) {
                    foreach ($results as $relative) {
                        $relativeId = $relative['id'];
                        $relative['relationship_description'] = isset($relativeIdToRelationshipDescriptionMapping[$relativeId]) ? $relativeIdToRelationshipDescriptionMapping[$relativeId] : '';
                        $this->immediateFamily[] = $relative;
                    }
                }
            }
        }

        return $this->immediateFamily;
    }

    /**
     * Get main individual and all his relatives personal photos
     *
     * @throws FamilyGraphException
     * @return array
     */
    private function getPersonalPhotos()
    {
        if (!isset($this->personalPhotos)) {
            $individual = $this->getIndividual();
            $immediateFamily = $this->getImmediateFamily();
            $individuals = array_merge(array($individual), $immediateFamily);
            $this->personalPhotos = $this->getCommonUtility()->getIndividualsPersonalPhotos($individuals);
        }
        
        return $this->personalPhotos;
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
                'loginUrl'                  => $this->getFamilyGraph()->getLoginUrl(),
                'isUserLoggedIn'            => $this->getCommonUtility()->getIsUserLoggedIn(),
                'currentUserName'           => $this->getCommonUtility()->getCurrentUserName(),
                'individual'                => $this->getIndividual(),
                'rootIndividualsInAllTrees' => $this->getRootIndividualsInAllTrees(),
                'immediateFamily'           => $this->getImmediateFamily(),
                'personalPhotos'            => $this->getPersonalPhotos(),
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
