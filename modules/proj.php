<?php

class ProjModule extends Module
{
    private $team;
    private $projectName;
    private $projRepo;

	const PROJ_ERROR_KEY   = 0x20;
	const AUTH_MASK        = 0x01;
	const NOT_IN_TEAM_MASK = 0x02;

	public function __construct()
	{
        $auth = AuthBackend::getInstance();

        // bail if we aren't authenticated
        if ($auth->getCurrentUser() == null)
        {
            throw new Exception("proj/list attempted without authentication", self::PROJ_ERROR_KEY | self::AUTH_MASK);
        }

        $input = Input::getInstance();
        $this->team = $input->getInput("team");

        // also bail if the user isn't in the team they're trying to list
        if (!in_array($this->team, $auth->getCurrentUserGroups()))
        {
            throw new Exception("proj/list attempted on team you aren't in", self::PROJ_ERROR_KEY | self::NOT_IN_TEAM_MASK);
        }

        // check that the project exists and is a git repo otherwise construct
        // the project directory and git init it
        $project = $input->getInput("project");
        $this->createProjectIfNonExistant($this->team, $project);
        $this->projectName = $project;

		$this->installCommand('list', array($this, 'listProject'));
	}

    public function listProject()
    {
        $output = Output::getInstance();
        $output->setOutput("files", $this->projRepo->listFiles("/"));
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

    private function createProjectIfNonExistant($team, $project)
    {
        $this->createRepoIfNoneExists($team);
        $repoPath = $this->getRootRepoPath();
        $projPath = "$repoPath/$team/$project";

        $repo = null;
        if (!is_dir($projPath))
        {
            $repo = GitRepository::createRepository($projPath);
        }
        else
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
