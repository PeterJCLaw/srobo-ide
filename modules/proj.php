<?php

class ProjModule extends Module
{
	private $team;
	private $projectName;
	private $projRepo;

	const PROJ_ERROR_KEY        = 0x20;
	const AUTH_MASK             = 0x01;
	const NOT_IN_TEAM_MASK      = 0x02;
	const PROJ_NONEXISTANT_MASK = 0x04;

	public function __construct()
	{
		$auth = AuthBackend::getInstance();

        // bail if we aren't authenticated
        if ($auth->getCurrentUser() == null)
        {
        	// module does nothing if no authentication
            return;
        }

		$input = Input::getInstance();
		$this->team = $input->getInput("team");

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput("project");

		$this->openProject($this->team, $project);
		$this->projectName = $project;

		$this->installCommand('list', array($this, 'listProject'));
	}

	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserGroups()))
		{
			throw new Exception("proj attempted on team you aren't in", self::PROJ_ERROR_KEY | self::NOT_IN_TEAM_MASK);
		}

	}

	public function listProject()
	{
		$this->verifyTeam();
		$output = Output::getInstance();
		if ($this->projRepo != NULL)
		{
			$output->setOutput("files", $this->projRepo->listFiles("/"));
			return true;
		}
		else
		{
			throw new Exception("the project you attempted to list $this->projectName does not exist", self::PROJ_ERROR_KEY | self::PROJ_NONEXISTANT_MASK);
		}

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
		$this->createRepoIfNoneExists($team);
		$repoPath = $this->getRootRepoPath();
		$projPath = "$repoPath/$team/$project";

		$repo = null;
		if (is_dir($projPath))
		{
			$repo = new GitRepository($projPath);
		}

		$this->projRepo = $repo;
	}

	private function createRepoIfNoneExists($team)
	{
		$repoPath = $this->getRootRepoPath();
		$teamRepoPath = "$repoPath/$team";

		if (!is_dir($teamRepoPath))
		{
			mkdir($teamRepoPath);
		}
	}

}
