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
			throw new Exception("not authenticated", 4);
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$team = $input->getInput('team');
		if ($team == null)
			throw new Exception("need a team", 1);
		if (in_array($team, $authModule->getCurrentUserGroups()))
			$members = array($team);
		else
			throw new Exception("you are not a member of that team", 4);
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
			throw new Exception("need a team", 1);
		if (in_array($team, $authModule->getCurrentUserGroups()))
			$projects = $manager->listRepositories($team);
		else
			throw new Exception("you are not a member of that team", 4);
		$output->addOutput('team-projects', $members);
	}
}
