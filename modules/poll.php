<?php

/**
 * Module for polling data from the server
 */
class PollModule extends Module
{
	private $username;

	public function __construct()
	{
		$this->installCommand('poll', array($this, 'poll'));
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
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
		return $auth;
	}

	/**
	 * Return a collection of useful pieces of information about the current
	 * state of the repos, teams, etc. that this user is part of.
	 */
	public function poll()
	{
		$this->ensureAuthed();
		$input  = Input::getInstance();
		$output = Output::getInstance();

		$team = $input->getInput('team');

		$manager = ProjectManager::getInstance();
		$projects = $manager->listRepositories($team);

		$projectRevs = array();
		foreach ($projects as $project)
		{
			$repo = $manager->getMasterRepository($team, $project);
			$projectRevs[$project] = $repo->getCurrentRevision();
		}

		$output->setOutput('projects', $projectRevs);
	}
}
