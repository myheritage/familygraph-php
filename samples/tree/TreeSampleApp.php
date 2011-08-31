<?php

require_once '../CommonUtility.php';

/**
 * Tree sample app - this application displays person's close family in a beautiful tree structure.
 * At first the application determines which Individual to use. Then, it gets all the families where the individual
 * is a child in using the 'child_in_families' field on Individual object. In order to get the individual siblings it
 * uses the 'children' field on the families where the individual is a child in (Family objects). Then, it gets the
 * individual spouses using the 'spouse_in_families' field on Individual object. On these families (Family objects) it
 * uses the 'children' field again to get the children of the individual. Finally, after it has references to the
 * person's close family, it gets the basic data on these individuals using a multiple-ids request. Then in order to
 * have personal photos for these individuals, it aggregates all the personal photos of these individuals and get
 * them as well.
 * 
 */
class TreeSampleApp
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
            $params = array('fields' => 'child_in_families,spouse_in_families');
            $this->individual = $this->getFamilyGraph()->api($individualId, $params);
        }
        
        return $this->individual;
    }

    /**
     * Gets close family of the individual including: Father, Mother, Siblings, Spouses, Children
     *
     * @throws FamilyGraphException
     * @return array
     */
    private function getCloseFamily()
    {
        $individual = $this->getIndividual();
        $individualId = $individual['id'];
        
        // get parents and siblings
        $fatherId = null;
        $motherId = null;
        $siblingIds = array();
        if (isset($individual['child_in_families'])) {
            $parentFamilyId = null;
            $childInFamilies = $individual['child_in_families'];
            foreach ($childInFamilies as $childInFamily) {
                if (isset($childInFamily['child_type'])) {
                    // skip adopted / foster parents families
                    continue;
                }

                $parentFamilyId = $childInFamily['family']['id'];
                break;
            }

            $params = array('fields' => 'husband,wife,children');
            $parentFamily = $this->getFamilyGraph()->api($parentFamilyId, $params);
            if ($parentFamily) {
                if (isset($parentFamily['husband'])) {
                    $fatherId = $parentFamily['husband']['id'];
                }

                if (isset($parentFamily['wife'])) {
                    $motherId = $parentFamily['wife']['id'];
                }

                if (isset($parentFamily['children'])) {
                    foreach ($parentFamily['children'] as $childInFamily) {
                        $siblingId = $childInFamily['child']['id'];
                        if ($siblingId !== $individualId) {
                            $siblingIds[] = $siblingId;
                        }
                    }
                }
            }
        }

        // get spouses families and their children
        $allChildrenIds = array();
        $spouseIds = array();
        $spousesFamily = array();
        if (isset($individual['spouse_in_families'])) {
            $spousesFamilyIds = array();
            $spouseInFamilies = $individual['spouse_in_families'];
            foreach ($spouseInFamilies as $spouseInFamily) {
                $spousesFamilyIds[] = $spouseInFamily['id'];
            }

            $params = array('fields' => 'husband,wife,children,status');
            $results = $this->getFamilyGraph()->api($spousesFamilyIds, $params);
            if ($results) {
                foreach ($results as $spouseFamily) {
                    $spouseId = null;
                    if (isset($spouseFamily['husband']) && $spouseFamily['husband']['id'] !== $individualId) {
                        $spouseId = $spouseFamily['husband']['id'];
                    }
                    else if (isset($spouseFamily['wife']) && $spouseFamily['wife']['id'] !== $individualId) {
                        $spouseId = $spouseFamily['wife']['id'];
                    }

                    if ($spouseId) {
                        $spouseIds[] = $spouseId;
                    }

                    $childIds = array();
                    if (isset($spouseFamily['children']) && $spouseFamily['children']) {
                        $children = $spouseFamily['children'];
                        foreach ($children as $child) {
                            $childId = $child['child']['id'];
                            $childIds[] = $childId;
                            $allChildrenIds[] = $childId;
                        }
                    }

                    $familyId = $spouseFamily['id'];
                    $spousesFamily[$familyId] = array(
                        'spouseId' => $spouseId,
                        'status'   => $spouseFamily['status'],
                        'childIds' => $childIds,
                    );
                }
            }
        }

        // get all relatives
        $relatives = array();
        $relativeIds = array_unique(array_filter(array_merge(array($individualId, $fatherId, $motherId), $siblingIds, $spouseIds, $allChildrenIds)));
        if (!empty($relativeIds)) {
            $params = array('fields' => 'name,gender,personal_photo,birth_date,is_alive,death_date');
            $relatives = $this->getFamilyGraph()->api($relativeIds, $params);
        }

        // get personal photos for all relatives
        $personalPhotos = $this->getCommonUtility()->getIndividualsPersonalPhotos($relatives);
        foreach ($relatives as &$relative) {
            $personalPhoto = null;
            if (isset($relative['personal_photo'], $personalPhotos[$relative['personal_photo']['id']])) {
                $personalPhoto = $personalPhotos[$relative['personal_photo']['id']];
            }
            $relative['personal_photo'] = $personalPhoto;
        }
        
        $results = array(
            'individualId'   => $individualId,
            'fatherId'       => $fatherId,
            'motherId'       => $motherId,
            'siblingIds'     => $siblingIds,
            'spousesFamily'  => $spousesFamily,
            'relatives'      => $relatives,
        );

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
                'closeFamily'     => $this->getCloseFamily(),
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
