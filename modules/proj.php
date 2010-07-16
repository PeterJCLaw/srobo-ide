<?php

class ProjModule extends Module
{
    private $team;
    private $projectName;
    private $projRepo;

    private static $instance;
    private static $PROJ_ERROR_KEY = 0x20;
    private static $AUTH_MASK = 1;
    private static $NOT_IN_TEAM_MASK = 2;

	public function __construct()
	{
        $auth = AuthBackend::getInstance();

        // bail if we aren't authenticated
        if ($auth->getCurrentUser() == null)
        {
            throw new Exception("proj/list attempted without authentication", ProjModule::PROJ_ERROR_KEY | ProjModule::AUTH_MASK);
        }

        $input = Input::getInstance();
        $this->team = $input->getInput("team");

        // also bail if the user isn't in the team they're trying to list
        if (!in_array($this->team, $auth->getCurrentUserGroups()))
        {
            throw new Exception("proj/list attempted on team you aren't in", ProjModule::PROJ_ERROR_KEY | ProjModule::NOT_IN_TEAM_MASK);
        }

        // check that the project exists and is a git repo otherwise construct
        // the project directory and git init it
        $project = $input->getInput("project");
        $this->createProjectIfNonExistant($this->team, $project);
        $this->projectName = $project;

		$this->installCommand('list', "ProjModule::listProject");

        ProjModule::$instance = $this;
	}

    public static function listProject()
    {
        $instance = ProjModule::$instance;
        return $instance->projRepo->listFiles("/");
    }

    private function getRootRepoPath()
    {
        $config = Configuration::getInstance();
        $repoPath = $config->getConfig("repopath");
        return str_replace("ROOT", getcwd(), $repoPath);
    }

    private function createprojectIfNonExistant($team, $project)
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
