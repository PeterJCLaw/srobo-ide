<?php

class TeamModule extends Module
{
	public function __construct()
	{
	}

	public function listMembers()
	{
		$authModule = AuthBackend::getInstance();
		if ($authModule->getCurrentUser() == null)
			throw new Exception("not authenticated", E_PERM_DENIED);
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$team = $input->getInput('team');
		if ($team == null)
			throw new Exception("need a team", E_MALFORMED_REQUEST);
		if (in_array($team, $authModule->getCurrentUserGroups()))
			$members = array($team);
		else
			throw new Exception("you are not a member of that team", E_PERM_DENIED);
		$output->addOutput('team-members', $members);
	}

	public function listProjects()
	{
		$authModule = AuthBackend::getInstance();
		$manager    = ProjectManager::getInstance();
		$input      = Input::getInstance();
		$output     = Output::getInstance();
		$team = $input->getInput('team');
		if ($team == null)
			throw new Exception("need a team", E_MALFORMED_REQUEST);
		if (in_array($team, $authModule->getCurrentUserGroups()))
			$projects = $manager->listRepositories($team);
		else
			throw new Exception("you are not a member of that team", E_PERM_DENIED);
		$output->addOutput('team-projects', $members);
	}
}
