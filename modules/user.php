<?php

class UserModule extends Module
{
	private $username;

	public function __construct()
	{
		$this->installCommand('info', array($this, 'getInfo'));
		$this->installCommand('settings-put', array($this, 'saveSettings'));
	}

	/**
	 * Ensures that we have a valid user.
	 * You can't do anything user related without being authed, but putting
	 * this in the constructor causes issues, since construction occurs
	 * before the auth cycle does.
	 * Returns the AuthBackend instance for convenience.
	 */
	private function ensureAuthed()
	{
		$auth = AuthBackend::getInstance();
		if (!($this->username = $auth->getCurrentUserName()))
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
		return $auth;
	}

	/**
	 * Get information about the user
	 */
	public function getInfo()
	{
		$output = Output::getInstance();
		$auth = $this->ensureAuthed();

		$teamNumbers = $auth->getCurrentUserTeams();
		$teams = array();
		foreach ($teamNumbers as $id)
		{
			$name = $this->displayNameForTeam($id);
			$teams[] = array('id' => $id, 'name' => $name);
		}

		$output->setOutput('display-name', $auth->displayNameForUser($this->username));
		$output->setOutput('email', $auth->emailForUser($this->username));
		$output->setOutput('teams', $teams);
		$output->setOutput('is-admin', $auth->isCurrentUserAdmin());
		$settingsManager = Settings::getInstance();
		$settings = $settingsManager->getSettings($this->username);
		$output->setOutput('settings', $settings);
		return true;
	}

	/**
	 * Save the user's settings
	 */
	public function saveSettings()
	{
		$this->ensureAuthed();
		$input = Input::getInstance();
		$settings = $input->getInput('settings');
		$settingsManager = Settings::getInstance();
		$settingsManager->setSettings($this->username, $settings);
		return true;
	}

	private function displayNameForTeam($team)
	{
		$ts = new TeamStatus($team);
		$name = $ts->getLive('name');
		if (empty($name))
		{
			// empty string renders better than 'null' which we otherwise get
			$name = '';
		}
		return $name;
	}
}
