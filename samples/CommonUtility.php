<?php

/**
 * This class introduces common utilities for the sample apps
 *
 */
class CommonUtility {

    /**
     * @var FamilyGraph
     */
    private $familyGraph;

    /**
     * Indicates whether a user is logged in or not
     *
     * @var bool
     */
    private $isUserLoggedIn;

    /**
     * The logged in user if available, empty array otherwise
     *
     * @var array
     */
    private $loggedInUser;
    
    /**
     * Constructs a new common utility object
     * 
     * @param FamilyGraph $familyGraph the family graph
     * 
     */
    public function __construct(FamilyGraph $familyGraph)
    {
        $this->setFamilyGraph($familyGraph);
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
     * Returns true if a user is logged in, false otherwise
     *
     * @return bool true if a used is logged in, false otherwise
     */
    public function getIsUserLoggedIn()
    {
        if (!isset($this->isUserLoggedIn)) {
            $userId = $this->getFamilyGraph()->getUserId();
            $this->isUserLoggedIn = (!empty($userId));
        }
        
        return $this->isUserLoggedIn;
    }
    
	/**
	 * Gets the logged in user (if available)
	 * We may or may not have this data based on whether the user is logged in or not
     * 
     * @throws FamilyGraphException
	 * @return array the logged in user if available, empty array otherwise
	 */
	public function getLoggedInUser()
	{
		if (!isset($this->loggedInUser)) {
            $this->loggedInUser = array();
            
            if ($this->getIsUserLoggedIn()) {
                // get the logged in user
                $this->loggedInUser = $this->getFamilyGraph()->api('me');
            }
        }
        
        return $this->loggedInUser;
	}

    /**
     * Gets the current user name if found, empty string otherwise
     *
     * @return string the current user name if found, empty string otherwise
     */
    public function getCurrentUserName()
    {
        $currentUserName = '';
        try {
            $loggedInUser = $this->getLoggedInUser();
            if ($loggedInUser) {
                $currentUserName = $loggedInUser['name'];
            }
        } catch (FamilyGraphException $ex) {
            // ignore error
        }
        
        return $currentUserName;
    }

    /**
     * Extracts the media item type
     *
     * @param string $mediaItemId the media item id
     *
     * @return string|null the media item type on success, empty string otherwise
     */
    public function getMediaItemType($mediaItemId)
    {
        $type = '';
        $matches = array();
        if (preg_match('/^(audio|document|photo|video)-.*$/', $mediaItemId, $matches)) {
            $type = $matches[1];
        }
        
        return $type;
    }

    /**
     * Gets the default individual id for the current logged in user using the following logic:
     * 1. If no user is logged return empty string, otherwise
     * 2. A user is logged in, try to use its default individual, otherwise
     * 3. Try to use one of the root individuals in one of the user sites, otherwise
     * 4. return empty string
     *
     * @throws FamilyGraphException
     * @return string the chosen individual id
     */
    public function getDefaultIndividualId()
    {
        // 1. If no user is logged return empty string
        $loggedInUser = $this->getLoggedInUser();
        if (!$loggedInUser) {
            // no user is logged in
            return '';
        }

        // 2. A user is logged in, try to use its default individual
        $loggedInUser = $this->getLoggedInUser();
        if (isset($loggedInUser['default_individual'])) {
            return $loggedInUser['default_individual']['id'];
        }

        // 3. Try to use one of the root individuals in one of the user sites
        // get the sites where the user is a member in
        $results = $this->getFamilyGraph()->api('me/memberships');
        if ($results && isset($results['data']) && count($results['data']) > 0) {
            // iterate over the sites and try to get the root individual
            $memberships = $results['data'];
            foreach($memberships as $membership) {
                // for each site get the default root individual
                $siteId = $membership['site']['id'];
                $site = $this->getFamilyGraph()->api($siteId, array('fields' => 'default_root_individual'));
                if (isset($site['default_root_individual'])) {
                    $individualId = $site['default_root_individual']['id'];
                    return $individualId;
                }
            }
        }
        
        // 4. failed to find a default individual
        return '';
    }

    /**
     * Gets personal photos for a list of individuals
     *
     * @param array $individuals the individuals
     *
     * @throws FamilyGraphException
     * @return array personal photos for a list of individuals
     */
    public function getIndividualsPersonalPhotos($individuals)
    {
        $personalPhotos = array();
        $uniquePersonalPhotoIds = array();
        foreach ($individuals as $individual) {
            if (isset($individual['personal_photo'])) {
                $uniquePersonalPhotoIds[$individual['personal_photo']['id']] = true;
            }
        }

        if (!empty($uniquePersonalPhotoIds)) {
            $personalPhotoIds = array_keys($uniquePersonalPhotoIds);
            $personalPhotos = $this->getFamilyGraph()->api($personalPhotoIds);
        }

        return $personalPhotos;
    }
}