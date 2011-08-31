<?php

/**
 * This class introduces common utilities for the sample apps viewer
 *
 */
class CommonViewerUtility
{
	/**
	 * Selects the thumbnail that best fits the requested width/height
	 *
	 * @param array $mediaItem the media item
	 * @param int $dimension the desired dimension
	 * @param string $type the desired dimension type (either 'width' or 'height')
	 *
	 * @return array the thumbnail that best fits the requested width/height on success, empty array otherwise
	 */
	public function getBestMediaItemThumbnail($mediaItem, $dimension, $type = 'width')
	{
		// check for thumbnails
		if (!isset($mediaItem['thumbnails'])) {
			// no thumbnails were found
			return array();
		}
		$thumbnails = $mediaItem['thumbnails'];

		// verify the type
		if ($type !== 'width' && $type !== 'height') {
			// use width by default
			$type = 'width';
		}

		// iterate over the thumbnail and choose the thumbnail that fits the best
		$bestThumbnail = $thumbnails[0];
		foreach ($thumbnails as $curThumbnail) {
			$currentDiff = abs($curThumbnail[$type] - $dimension);
			$bestDiff = abs($bestThumbnail[$type] - $dimension);
			if ($currentDiff < $bestDiff) {
				$bestThumbnail = $curThumbnail;
			}
		}

		return $bestThumbnail;
	}

	/**
	 * Returns a textual representation of the birth and death years of an individual
	 *
	 * @param $individual
	 *
	 * @return string
	 */
	public function getYearRange($individual)
	{
		// Set birth year
		if (isset($individual['birth_date']))
		{
			$birth_date = $individual['birth_date'];

			$birth_year = false;
			if ($birth_date)
			{
				$birth_year = $this->getEventDateYear($birth_date);
			}

			if (!$birth_year)
			{
				$birth_year = '?';
			}
			$year_range = $birth_year;
		}
		else
		{
			$year_range = '?';
		}

		// Set death year
		if (!$individual['is_alive'])
		{
			if (isset($individual['death_date']))
			{
				$death_date = $individual['death_date'];

				$death_year = false;
				if ($death_date)
				{
					$death_year = $this->getEventDateYear($death_date);
				}
				if (!$death_year)
				{
					$death_year = '?';
				}
				$year_range .= ' - ' . $death_year;
			}
			else
			{
				$year_range .= ' - ?';
			}
		}

		return $year_range;
	}

	/**
	 * Returns an HTML snippet for a tagged photo
	 *
	 * @param $photo
	 * @param $tag
	 * @param $targetWidth
	 * @param $targetHeight
	 * @param $thumbnail
	 * @return string
	 */
	function getTagHtml($photo, $tag, $targetWidth, $targetHeight, &$thumbnail)
	{
		// Defaults
		if (!isset($tag['w']) || !isset($tag['h']))
		{
			$tag['x'] = $tag['y'] = 0;
			$tag['w'] = $tag['h'] = 1;
		}

		// Calculate the factor and get the best-fitting thumbnail
		$imageWidth = $photo['width'];
		$imageHeight = $photo['height'];
		$wFactor = $targetWidth / ($tag['w'] * $imageWidth);
		$hFactor = $targetHeight / ($tag['h'] * $imageHeight);
		$factor = min($wFactor, $hFactor);
		$dimension = $imageWidth * $factor;
		$thumbnail = $this->getBestMediaItemThumbnail($photo, $dimension, 'height');

		$ratio = $imageWidth / $thumbnail['width'];
		if ($ratio != 1)
		{
			$imageWidth = $thumbnail['width'];
			$imageHeight = $thumbnail['height'];
			$w_factor = $targetWidth / ($tag['w'] * $imageWidth);
			$h_factor = $targetHeight / ($tag['h'] * $imageHeight);
			$factor = min($w_factor, $h_factor);
		}

		// Set the porthole dimensions
		$x = round($tag['x'] * $imageWidth * $factor);
		$y = round($tag['y'] * $imageHeight * $factor);
		$w = round($tag['w'] * $imageWidth * $factor);
		$h = round($tag['h'] * $imageHeight * $factor);
		$wPhoto = round($imageWidth * $factor);
		$hPhoto = round($imageHeight * $factor);

		$url = $thumbnail['url'];

		// Get the HTML snippet
		$html = '<div style="width:' . $w . 'px; height:' . $h . 'px; overflow:hidden; position:relative">';
		$html .= '<img src="' . $url . '" width="' . $wPhoto . '" height="' . $hPhoto . '" style="position:relative; top: -' . $y . 'px; left: -' . $x . 'px;" />';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns an HTML snippet for a single individual in the tree
	 *
	 * @param $individual array All the individual information
	 * @param $currentUrl string the current url
	 * @param $x int Where to render the individual box horizontally
	 * @param $y int Where to render the individual box vertically
	 * @param $relationshipCode string The relation to the root individual (P => Parent, R => Root, S => Spouse, C => Child, 'B' => Sibling)
	 * @param $status string The status of the individual (married etc.)
	 * @return string An HTML snippet for a single individual in the tree
	 */
	public function getIndividualBox($individual, $currentUrl, $x, $y, $relationshipCode, $status = '')
	{
		$individualBox = '';
		$isRoot = ($relationshipCode == 'R');
		$gender = $individual['gender'];
		if (strtoupper($gender) == 'M') {
			$genderClass = 'male';
		} else if (strtoupper($gender) == 'F') {
			$genderClass = 'female';
		} else {
			$genderClass = 'unknown';
		}

		// Mark the root individual with a special frame
		$individualBox .= '<div class="' . $genderClass . ($isRoot ? ' root_individual' : '') . '" style="left:' . $x . 'px; top:' . $y . 'px;">';
		$individualBox .= '<table><tr>';

		// Choose the best photo and use it
		$individualBox .= '<td width="65" valign="top" style="padding-right:5px">';
		if ($individual['personal_photo']) {
			$photo = $individual['personal_photo'];
			$image = $this->getBestMediaItemThumbnail($photo, 80, 'height');
			if ($image) {
				$individualBox .= '<img src="' . $image['url'] . '" width="60">';
			}
		}
		$individualBox .= '</td>';

		$individualBox .= '<td width="225" valign="top">';

		// Individual's name
		$name = htmlspecialchars($individual['name']);
		if (!$isRoot) {
			$url = $currentUrl . '?individualId=' . $individual['id'];
			$name = '<a href="' . $url . '">' . $name . '</a>';
		}
		$individualBox .= '<b>' . $name . '</b>';
        
		// Relationship to root individual
		$relationshipString = $this->getRelationshipString($relationshipCode, $gender);
		if (!empty($relationshipString)) {
			$individualBox .= '<div>Relationship:' . htmlspecialchars($relationshipString) . '</div>';
		}

		// Individual's birth date
		if (isset($individual['birth_date'])) {
			$birthDateYear = $this->getEventDateYear($individual['birth_date']);
			if ($birthDateYear) {
				$individualBox .= '<div>Born:' . $birthDateYear . '</div>';
			}
		}

		// Individual's death date (if applicable)
		if (isset($individual['death_date']) || !$individual['is_alive']) {
			if (isset($individual['death_date'])) {
				$deathDateYear = $this->getEventDateYear($individual['death_date']);
				if ($deathDateYear) {
					$individualBox .= '<div>Died:' . $deathDateYear . '</div>';
				}
			} else if (!$individual['is_alive']) {
				$individualBox .= '<div>Deceased</div>';
			}
		}

		// Individual's status (if applicable)
		if ($status) {
			$individualBox .= '<div>(' . htmlspecialchars($status) . ')</div>';
		}
		$individualBox .= '</td>';

		$individualBox .= '</tr></table>';
		$individualBox .= '</div>';

		return $individualBox;
	}

	/**
	 * Returns the year portion of a given date
	 *
	 * @param $date
	 * @return string
	 */
	private function getEventDateYear($date)
	{
		$year = '';
		if (isset($date['date']))
		{
			$dateParts = explode('-', $date['date']);
			$year = $dateParts[0];
		}

		return $year;
	}

	private function getRelationshipString($relationshipCode, $gender)
	{
		$relationShipString = '';
		switch (strtoupper($relationshipCode))
		{
			case 'P':
				if ($gender == 'M') {
					$relationShipString = 'Father';
				} else if ($gender == 'F') {
					$relationShipString = 'Mother';
				} else {
					$relationShipString = 'Parent';
				}
				break;
			case 'R':
				$relationShipString = 'You';
				break;
			case 'S':
				if ($gender == 'M') {
					$relationShipString = 'Husband';
				} else if ($gender == 'F') {
					$relationShipString = 'Wife';
				} else {
					$relationShipString = 'Spouse';
				}
				break;
			case 'C':
				if ($gender == 'M') {
					$relationShipString = 'Son';
				} else if ($gender == 'F') {
					$relationShipString = 'Daughter';
				} else {
					$relationShipString = 'Child';
				}
				break;
			case 'B':
				if ($gender == 'M') {
					$relationShipString = 'Brother';
				} else if ($gender == 'F') {
					$relationShipString = 'Sister';
				} else {
					$relationShipString = 'Sibling';
				}
				break;
		}

		return $relationShipString;
	}
}
