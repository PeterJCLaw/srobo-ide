<?php

class AdminModule extends Module
{
	private $username;

	public function __construct()
	{
		$this->installCommand('team-name-put', array($this, 'saveTeamName'));
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
		if (!($this->username = $auth->getCurrentUser()))
		{
			throw new Exception('You are not logged in', E_PERM_DENIED);
		}
		if (!$auth->isCurrentUserAdmin())
		{
			throw new Exception('You do not have admin privileges', E_PERM_DENIED);
		}
		return $auth;
	}

	/**
	 * Save the change to the team name
	 */
	public function saveTeamName()
	{
		$auth   = $this->ensureAuthed();
		$input  = Input::getInstance();
		$output = Output::getInstance();

		$team = $input->getInput('id');
		$name = $input->getInput('name');

		$auth->setTeamDesc($team, $name);

		$output->setOutput('id', $team);
		// TODO: detect failure, and return the old value in that case.
		$output->setOutput('name', $name);
	}
}
