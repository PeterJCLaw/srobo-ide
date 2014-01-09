<?php

class TeamModule extends Module
{
	const IMAGE = 'image';
	static $statusTextFields = array('feed', 'url', 'facebook', 'youtube', 'twitter', 'description', 'name');

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
		if ($authModule->getCurrentUserName() == null)
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

		// Handle the image entry separately, since it doesn't get a draft value
		$reviewState = $status->getReviewState(self::IMAGE);
		if ($reviewState !== null)
		{
			$reviewStates[self::IMAGE] = $reviewState;
		}

		$output->setOutput('items', $items);
		$output->setOutput('reviewed', $reviewStates);
		return true;
	}

	/**
	 * Helper method for saving the status as the current user,
	 *  and outputting a suitable message if it fails.
	 * This also outputs the news to the world.
	 */
	private function saveStatus($status, $extra = null)
	{
		$userName = AuthBackend::getInstance()->getCurrentUserName();
		$saved = $status->save($userName);
		if (!$saved)
		{
			$output = Output::getInstance();
			$output->setOutput('error', 'Unable to save team status');
		}
		else
		{
			$team = Input::getInstance()->getInput('team');
			Announce::that("Team\x033 $team\x0f updated their status$extra.");
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
		AuthBackend::ensureWrite($team);
		$config = Configuration::getInstance();

		$uploadLocation = $config->getConfig('team.status_images.dir');
		if (!is_dir($uploadLocation))
		{
			mkdir_full($uploadLocation);
		}

		$uploadPath = "$uploadLocation/$team";
		$path = move_uploaded_file_id('team-status-image-input', $uploadPath);

		$height = $config->getConfig('team.status_images.height');
		$width = $config->getConfig('team.status_images.width');
		$thumbHeight = $config->getConfig('team.status_thumbs.height');
		$thumbWidth = $config->getConfig('team.status_thumbs.width');

		// grab a resource of the image resized
		$image = new ResizableImage($path);
		$dest = path_change_extension($path, 'png');
		$image->resizeInto($width, $height, $dest);

		// remove the original, if different
		if ($path != $dest)
		{
			unlink($path);
		}

		// update the status store
		$status = new TeamStatus($team);
		$md5 = md5_file($dest);
		$status->setDraft(self::IMAGE, $md5);
		return $this->saveStatus($status, ' image');
	}

	public function putStatus()
	{
		$input = Input::getInstance();
		$team = self::getRequestTeamID();
		AuthBackend::ensureWrite($team);
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
