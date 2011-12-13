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
		$settingspath = Configuration::getInstance()->getConfig('settingspath');
		$statusPath = "$settingspath/$team-status.json";
		$status = json_decode(file_get_contents($statusPath));
		return $status;
	}

	public function getStatus()
	{
		$output = Output::getInstance();
		$team = self::getRequestTeamID();
		$status = new TeamStatus($team);

		$reviewStates = array();
		$items = array();
		foreach (self::$statusTextFields as $field)
		{
			$items[$field] = $status->getDraftOrLive($field);
			$reviewState = $status->getReviewState($field);
			if ($reviewState !== null)
			{
				$reviewStates[$field] = $reviewState;
			}
		}
		$output->setOutput('items', $items);
		$output->setOutput('reviewed', $reviewStates);
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

		$uploadLocation = Configuration::getInstance()->getConfig('team.status_images.dir');
		if (!is_dir($uploadLocation))
		{
			mkdir_full($uploadLocation);
		}

		$uploadPath = "$uploadLocation/$team-image";
		$path = move_uploaded_file_id('team-status-image-input', $uploadPath);

		$height = Configuration::getInstance()->getConfig('team.status_images.height');
		$width = Configuration::getInstance()->getConfig('team.status_images.width');

		// grab a resource of the image resized
		$image = new ResizableImage($path);
		$newImageResource = $image->createResizedImage($width, $height);

		// remove the original
		unlink($path);

		// save, with .png extension
		$path = path_change_extension($path, 'png');
		imagepng($newImageResource, $path);

		// free up the resource.
		imagedestroy($newImageResource);

		// update the status store
		$status = new TeamStatus($team);
		$md5 = md5_file($path);
		$status->setDraft('image', $md5);
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
