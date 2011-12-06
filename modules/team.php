<?php

class TeamModule extends Module
{
	public function __construct()
	{
		$this->installCommand('list-members', array($this, 'listMembers'));
		$this->installCommand('list-projects', array($this, 'listProjects'));
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
}
