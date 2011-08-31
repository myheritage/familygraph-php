<?php

require_once '../CommonUtility.php';

/**
 * Sites sample app - this application shows basic data on all sites the logged in user is a member in.
 * At first, the application gets the 'memberships' connection on User object. In order to get the memberships
 * on the current logged in user, it uses the 'me' wildcard (me/memberships). Afterwards it gets the basic data
 * of the sites in which the logged in user is a member in including:
 *     name - the site title
 *     site_logo - the site logo (if it has one)
 *     creator - the site creator
 *     media_count - the number of media items in the site
 *     tree_count - the number of trees in the site
 * After getting the site logos of user's sites, it gets the information of these photos using a multiple-ids request.
 * 
 * Important: in order to use this application a user must be logged in.
 */
class SitesSampleApp
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
     * The logged in user memberships
     *
     * @var array
     */
    private $memberships;

    /**
     * Constructs a new sites sample app object
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
     * Gets the logged in user memberships
     *
     * @throws FamilyGraphException
     * @return array
     */
    private function getMemberships()
    {
        if (!isset($this->memberships)) {
            $this->memberships = array();

            $loggedInUser = $this->getCommonUtility()->getLoggedInUser();
            if ($loggedInUser) {
                $results = $this->getFamilyGraph()->api('me/memberships');
                if ($results && isset($results['data']) && count($results['data']) > 0) {
                    $memberships = $results['data'];

                    // aggregate site ids
                    $siteIds = array();
                    foreach($memberships as $membership) {
                        $siteId = $membership['site']['id'];
                        $siteIds[] = $siteId;
                    }

                    // get the sites where the current user is member in
                    $params = array('fields' => 'name,site_logo,creator,created_date,member_count,media_count,tree_count');
                    $sites = $this->getFamilyGraph()->api($siteIds, $params);
                    if ($sites) {
                        // get sites logo
                        $siteLogoIds = array();
                        foreach ($sites as $site) {
                            if (isset($site['site_logo'])) {
                                $siteLogoId = $site['site_logo']['id'];
                                $siteLogoIds[] = $siteLogoId;
                            }
                        }
                        $sitesLogo = array();
                        if (!empty($siteLogoIds)) {
                            $sitesLogo = $this->getFamilyGraph()->api($siteLogoIds);
                        }

                        // prepare the results
                        foreach ($memberships as $membership) {
                            $siteId = $membership['site']['id'];
                            if (!isset($sites[$siteId])) {
                                // failed to get data on a site
                                continue;
                            }
                            $site = $sites[$siteId];
                            $siteCreatorName = isset($site['creator']['name']) ? $site['creator']['name'] : null;
                            $lastVisitTime = isset($membership['last_visit_time']) ? $membership['last_visit_time'] : null;
                            $siteLogo = isset($site['site_logo'], $sitesLogo[$site['site_logo']['id']]) ? $sitesLogo[$site['site_logo']['id']] : null;

                            $this->memberships[] = array(
                                'siteName'        => $site['name'],
                                'isManager'       => $membership['is_manager'],
                                'visitCount'      => $membership['visit_count'],
                                'siteCreatorName' => $siteCreatorName,
                                'lastVisitTime'   => $lastVisitTime,
                                'siteLogo'        => $siteLogo,
                                'siteCreatedDate' => $site['created_date'],
                                'memberCount'     => $site['member_count'],
                                'mediaCount'      => $site['media_count'],
                                'treeCount'       => $site['tree_count'],
                            );
                        }
                    }
                }
			}
		}

		return $this->memberships;
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
                'memberships'     => $this->getMemberships(),
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
