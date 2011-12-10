<?php

class TeamModule extends Module
{
	static $statusTextFields = array('feed', 'url', 'description', 'name');

	public function __construct()
	{
		$this->installCommand('list-members', array($this, 'listMembers'));
		$this->installCommand('list-projects', array($this, 'listProjects'));
		$this->installCommand('status-get', array($this, 'getStatus'));
		$this->installCommand('status-put', array($this, 'putStatus'));
		$this->installCommand('status-put-image', array($this, 'putStatusImage'));
	}

	/**
	 * Gets the requested team ID, or throws a suitable exception if something went wrong.
	 */
	private static function getRequestTeamID()
	{
		$authModule = AuthBackend::getInstance();
		if ($authModule->getCurrentUser() == null)
			throw new Exception('not authenticated', E_PERM_DENIED);
		$input  = Input::getInstance();
		$team = $input->getInput('team');
		if ($team == null)
			throw new Exception('need a team', E_MALFORMED_REQUEST);
		if (in_array($team, $authModule->getCurrentUserTeams()))
			return $team;
		else
			throw new Exception('you are not a member of that team', E_PERM_DENIED);
	}

	public function listMembers()
	{
		$team = self::getRequestTeamID();
		// TODO: implement this!
		$members = array($team);
		$output = Output::getInstance();
		$output->setOutput('team-members', $members);
		return true;
	}

	public function listProjects()
	{
		$team = self::getRequestTeamID();
		$manager    = ProjectManager::getInstance();
		$output     = Output::getInstance();
		$projects = $manager->listRepositories($team);
		$output->setOutput('team-projects', $projects);
		return true;
	}

	private static function loadStatus($team)
	{
		$settingspath = Configuration::getIsntance()->getConfig('settingspath');
		$statusPath = "$settingspath/$team-status.json";
		$status = json_decode(file_get_contents($statusPath));
		return $status;
	}

	public function getStatus()
	{
		$output = Output::getInstance();
		$team = self::getRequestTeamID();
		$status = new TeamStatus($team);

		foreach (self::$statusTextFields as $field)
		{
			$value = $status->getDraftOrLive($field);
			$output->setOutput($field, $value);
		}
		return true;
	}

	/**
	 * Helper method for saving the status as the current user,
	 *  and outputting a suitable message if it fails.
	 */
	private function saveStatus($status)
	{
		$user = AuthBackend::getInstance()->getCurrentUser();
		$saved = $status->save($user);
		if (!$saved)
		{
			$output = Output::getInstance();
			$output->setOutput('error', 'Unable to save team status');
		}
		return $saved;
	}

	/**
	 * Handle the users upload of a new image for the dashboard.
	 * This needs to be a separate method since file uploads are a pain.
	 */
	public function putStatusImage()
	{
		$input = Input::getInstance();
		$team = self::getRequestTeamID();

		$uploadLocation = Configuration::getIsntance()->getConfig('team.status_image_dir');
		if (!is_dir($uploadLocation))
		{
			mkdir_full($uploadLocation);
		}

		$uploadPath = "$uploadLocation/$team-image";
		$moved = move_uploaded_file_id('team-status-image', $uploadPath);

		if (!$moved)
		{
			$output = Output::getIsntance();
			$output->setOutput('error', 'Unable to save uploaded image');
			return false;
		}

		$status = new TeamStatus($team);
		$status->newImage();
		return $this->saveStatus($status);
	}

	public function putStatus()
	{
		$input = Input::getInstance();
		$team = self::getRequestTeamID();
		$status = new TeamStatus($team);

		// Handle the simple fields
		foreach (self::$statusTextFields as $field)
		{
			$value = $input->getInput($field, true);
			if ($value !== null)
			{
				$status->setDraft($field, $value);
			}
		}

		return $this->saveStatus($status);
	}
}
