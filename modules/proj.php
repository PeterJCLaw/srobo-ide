<?php

class ProjModule extends Module
{
	private $team;
	private $projectName;
	private $projectManager;
	private $projectRepository;

	public function __construct()
	{
		$auth = AuthBackend::getInstance();

        // bail if we aren't authenticated
        if ($auth->getCurrentUser() == null)
        {
        	// module does nothing if no authentication
            return;
        }

		try
		{
			$this->projectManager = ProjectManager::getInstance();
		}
		catch (Exception $e)
		{
			if ($e->getCode() == E_INTERNAL_ERROR)
			{
				// repo dir not set up
				// this may be valid for auth tests, so just don't init
				return;
			}
			else
			{
				// other error, rethrow
				throw $e;
			}
		}

		$input = Input::getInstance();
		$this->team = $input->getInput("team");

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput("project");

		$this->openProject($this->team, $project);
		$this->projectName = $project;

		$this->installCommand('new', array($this, 'createProject'));
		$this->installCommand('info', array($this, 'projectInfo'));
	}

	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserGroups()))
		{
			throw new Exception("proj attempted on team you aren't in", E_PERM_DENIED);
		}
	}

	public function createProject()
	{
		$this->verifyTeam();
		$this->openProject($this->team, $this->projectName);
	}

	public function projectInfo()
	{
		$output = Output::getInstance();
		$this->verifyTeam();
		$output->setOutput('project-info', array());
	}

	private function getRootRepoPath()
	{
		$config = Configuration::getInstance();
		$repoPath = $config->getConfig("repopath");
		$repoPath = str_replace('ROOT', '.', $repoPath);
		if (!file_exists($repoPath) || !is_dir($repoPath))
		{
			mkdir($repoPath);
		}
		return $repoPath;
	}

	private function openProject($team, $project)
	{
		if (in_array($team, $this->projectManager->listRepositories($team)))
		{
			$this->projectRepository = $this->projectManager->getRepository($team, $project);
		}
		else
		{
			$this->projectRepository = $this->projectManager->createRepository($team, $project);
		}
	}
}
