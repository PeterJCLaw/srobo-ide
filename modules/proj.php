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
		$this->team = $input->getInput("team", true);

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput("project", true);

		if ($this->team && $project)
		{
			$this->openProject($this->team, $project);
			$this->projectName = $project;
		}

		$this->installCommand('new', array($this, 'createProject'));
		$this->installCommand('info', array($this, 'projectInfo'));
		$this->installCommand('log', array($this, 'projectLog'));
		$this->installCommand('del', array($this, 'deleteProject'));
	}

	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserTeams()))
		{
			throw new Exception("proj attempted on team you aren't in", E_PERM_DENIED);
		}
	}

	public function createProject()
	{
		$this->verifyTeam();
		$this->openProject($this->team, $this->projectName);
	}

	public function deleteProject()
	{
		$this->verifyTeam();
		$projPath = $this->projectManager->teamDirectory($this->team);
		$projPath .= "/$this->projectName";
		if (is_dir($projPath))
		{
			$this->projectManager->deleteRepository($this->team, $this->projectName);
			return TRUE;
		}
		else
		{
			throw new Exception("attempted to delete nonexistant project", E_PROJ_NONEXISTANT);
		}

	}

	public function projectInfo()
	{
		$output = Output::getInstance();
		$this->verifyTeam();
		$output->setOutput('project-info', array());
	}

	public function projectLog()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		if ($this->projectRepository == null)
		{
			return FALSE;
		}

		$output = Output::getInstance();
		$input = Input::getInstance();
		$currRev = $input->getInput("start-commit", true);

		if ($currRev == null)
		{
			$currRev = $this->projectRepository->getCurrentRevision();
		}

		$firstRev = $input->getInput("end-commit", true);

		if ($firstRev == null) {
			$firstRev = $this->projectRepository->getFirstRevision();
		}

		$output->setOutput("log", $this->projectRepository->log($firstRev, $currRev));
		return TRUE;
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
