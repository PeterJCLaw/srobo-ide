<?php

class TeamStatus
{
	private $statusPath;
	private $statusData;
	private $dirty = array();

	public function __construct($team)
	{
		$config = Configuration::getInstance();
		$basePath = $config->getConfig('team.status_dir');
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
			if (isset($this->statusData->$name->draft))
			{
				return $this->statusData->$name->draft;
			}
			elseif (isset($this->statusData->$name->live))
			{
				return $this->statusData->$name->live;
			}
		}
		return null;
	}

	public function newImage()
	{
		$dirty['image'] = true;
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
			$dirty[$name] = true;
		}
	}

	public function save($user)
	{
		foreach ($this->statusData as $item => $values)
		{
			if (isset($dirty[$item]) && $dirty[$item])
			{
				$values['uid'] = $user;
				$values['date'] = date('Y-m-d');
			}
		}

		$data = json_encode($this->statusData);
		$ret = file_put_contents($this->statusPath, $data);
		return (bool)$ret;
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
