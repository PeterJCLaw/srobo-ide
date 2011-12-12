<?php

class TeamStatus
{
	private $statusPath;
	private $statusData;
	private $dirty = array();

	private static function getStatusDir()
	{
		$config = Configuration::getInstance();
		$statusDir = $config->getConfig('team.status_dir');
		return $statusDir;
	}

	public function __construct($team)
	{
		$basePath = self::getStatusDir();
		if (!is_dir($basePath))
		{
			mkdir_full($basePath);
		}
		$this->statusPath = "$basePath/$team-status.json";
		$this->load();
//		echo '$this->statusData: '; var_dump($this->statusData);
	}

	public function load()
	{
		if (file_exists($this->statusPath))
		{
			$raw = file_get_contents($this->statusPath);
			$this->statusData = json_decode($raw);
		}
		else
		{
			$this->statusData = new stdClass();
		}
	}

	public function getDraftOrLive($name)
	{
		if (isset($this->statusData->$name))
		{
			if (!empty($this->statusData->$name->draft))
			{
				return $this->statusData->$name->draft;
			}
			elseif (!empty($this->statusData->$name->live))
			{
				return $this->statusData->$name->live;
			}
		}
		return null;
	}

	/**
	 * Updates one of the draft values in the team status.
	 * @param name: The name of the property to update.
	 * @param value: The value to set it to.
	 */
	public function setDraft($name, $value)
	{
		if (!isset($this->statusData->$name->draft) || $this->statusData->$name->draft != $value)
		{
			$this->statusData->$name->draft = $value;
			$this->statusData->$name->reviewed = false;
			$this->dirty[$name] = true;
		}
	}

	public function save($user)
	{
		foreach ($this->statusData as $item => $values)
		{
			if (isset($this->dirty[$item]) && $this->dirty[$item])
			{
				$values->uid = $user;
				$values->date = date('Y-m-d');
			}
		}

		$data = json_encode($this->statusData);
		$ret = file_put_contents($this->statusPath, $data);
		return (bool)$ret;
	}

	/**
	 * Gets the review state for a given item.
	 * @param item: The object containing the item to get the state for.
	 * @returns: (bool?) True if the item is valid, False if not valid, null if unknown.
	 */
	private static function _getReviewState($item)
	{
		if (empty($item) || empty($item->reviewed))
		{
			return null;
		}

		return isset($item->live) && $item->live === $item->draft;
	}

	/**
	 * Gets the review state for a given item.
	 * @param name: The name of the item to get the state for.
	 * @returns: (bool?) True if the item is valid, False if not valid, null if unknown.
	 */
	public function getReviewState($name)
	{
		$state = self::_getReviewState($this->statusData->$name);
		return $state;
	}

	/**
	 * Sets the review state for a given item.
	 * Will produce errors if the item doesn't match up with the given value.
	 */
	public function setReviewState($name, $reviewedValue, $isValid)
	{
		$item = $this->statusData->$name;
		if ($item->draft != $reviewedValue)
		{
			throw new Exception('Cannot set review for non-existent draft', E_MALFORMED_REQUEST);
		}

		$item->reviewed = true;

		if ($isValid === true)
		{
			$item->live = $item->draft;
		}
	}

	/**
	 * Gets a dictionary of the items that need reviewing against their
	 *  current draft value.
	 * Returns an empty array if the team's content is fully reviewed.
	 */
	public function itemsForReview()
	{
		$items = array();
		foreach ($this->statusData as $item => $values)
		{
			// null means not yet reviewed (else bool)
			if (self::_getReviewState($values) === null)
			{
				$items[$item] = $values->draft;
			}
		}
		return $items;
	}

	/**
	 * Convenience function, wrapping itemsForReview.
	 */
	public function needsReview()
	{
		$items = $this->itemsForReview();
		return !empty($items);
	}

	public static function listAllTeams()
	{
		$basePath = self::getStatusDir();
		$names = glob($basePath.'/*-status.json');
		$baseLen = strlen($basePath);
		$names = array_map(function($name) use($baseLen) {
			return substr($name, $baseLen + 1, -12);
		}, $names);

		return $names;
	}

	/**
	 * Gets the path to the file that we're using.
	 * Intended only for use by the tests.
	 */
	public function getStatusPath()
	{
		return $this->statusPath;
	}

	public function clearStatus()
	{
		@unlink($this->statusPath);
	}
}
