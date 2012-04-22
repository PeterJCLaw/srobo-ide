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

		$this->installCommand('new', array($this, 'createProject'));
		$this->installCommand('info', array($this, 'projectInfo'));
		$this->installCommand('log', array($this, 'projectLog'));
		$this->installCommand('del', array($this, 'deleteProject'));
		$this->installCommand('commit', array($this, 'commitProject'));
		$this->installCommand('co', array($this, 'checkoutProject'));
		$this->installCommand("copy", array($this, "copyProject"));
		$this->installCommand('zip', array($this, 'redirectToZip'));
		$this->installCommand('update', array($this, 'updateProject'));
	}

	protected function initModule()
	{
		$this->projectManager = ProjectManager::getInstance();

		$input = Input::getInstance();
		$this->team = $input->getInput('team');

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput('project');

		$this->openProject($this->team, $project);
		$this->projectName = $project;
	}

	private function updateProject()
	{
		$this->verifyTeam();

		if ($this->projectRepository == null)
		{
			return false;
		}

		$auth = AuthBackend::getInstance();
		$currentUser = $auth->getCurrentUser();

		$this->projectManager->updateRepository($this->team,
		                                        $this->projectName,
		                                        $currentUser);
		return true;
	}

	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserTeams()))
		{
			throw new Exception('proj attempted on team you aren\'t in', E_PERM_DENIED);
		}
	}

	/**
	 * creates a project, requires team and project input keys to be present
	 */
	public function createProject()
	{
		$this->verifyTeam();
		$this->openProject($this->team, $this->projectName, true);
		return true;
	}

	public function copyProject()
	{
		$input = Input::getInstance();
		$this->projectManager->copyRepository($this->team, $this->projectName, $input->getInput("new-name"));
		return true;
	}

	/**
	 * deletes a project, requires that the team and project input keys are present
	 */
	public function deleteProject()
	{
		$this->verifyTeam();
		$this->projectManager->deleteRepository($this->team, $this->projectName);
		return true;
	}

	public function projectInfo()
	{
		$output = Output::getInstance();
		$this->verifyTeam();
		$output->setOutput('project-info', array());
		return true;
	}

	public function commitProject()
	{
		$this->verifyTeam();

		if ($this->projectRepository == null)
		{
			return false;
		}

		$output = Output::getInstance();
		$input  = Input::getInstance();
		$auth   = AuthBackend::getInstance();
		$message = $input->getInput('message');

		$currentUser = $auth->getCurrentUser();

		$files = $input->getInput("paths");
		//gaurd project state by doing a reset
		$this->projectRepository->unstageAll();

		//stage the files
		foreach ($files as $file)
		{
			$this->projectRepository->stage($file);
		}

		$currentUserEmail = UserInfo::makeCommitterEmail($currentUser);
		$commitResult = $this->projectRepository->commit($message,
		                                                 $currentUser,
		                                                 $currentUserEmail);
		// couldn't make the commit
		if (!$commitResult)
		{
			return false;
		}
		$conflicts = $this->projectManager->updateRepository($this->team,
		                                                     $this->projectName,
		                                                     $currentUser);
		$output->setOutput('merges', $conflicts);
		$output->setOutput('commit', $this->projectRepository->getCurrentRevision());
		return true;
	}

	public function projectLog()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		if ($this->projectRepository == null)
		{
			return false;
		}

		$output = Output::getInstance();
		$input = Input::getInstance();
		$currRev = $input->getInput('start-commit', true);
		$firstRev = $input->getInput('end-commit', true);

		// if the revisions are null then it just grabs the whole log
		$output->setOutput('log', $this->projectRepository->log($firstRev, $currRev));
		return true;
	}

	public function checkoutProject()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		if ($this->projectRepository == null)
		{
			return false;
		}

		$config = Configuration::getInstance();
		$output = Output::getInstance();
		$input = Input::getInstance();

		$hash = $input->getInput('rev');

		// we need an actual hash so that our serve path doesn't clash
		// even if it does then it shouldn't matter as it'll still be the same hash
		if ($hash == 'HEAD')
		{
			$hash = $this->projectRepository->getCurrentRevision();
		}

		$projNameEscaped = rawurlencode($this->projectName);
		$servePath = $config->getConfig('zipurl') . "/$this->team/$this->projectName/$hash";
		$serveUrl = $config->getConfig('zipurl') . "/$this->team/$projNameEscaped/$hash";

		// ensure that the serve folder exists
		if (!file_exists($servePath) && !mkdir_full($servePath))
		{
			// can't do anything if there's no folder for us to use
			return false;
		}

		$helper = new CheckoutHelper($this->projectRepository, $this->team);
		$helper->buildZipFile("$servePath/robot.zip", $hash);

		$output->setOutput('url', "$serveUrl/robot.zip");
		// NB: this is intentionally also returned -- if they ask for HEAD,
		// this will contain the actual hash.
		$output->setOutput('rev', $hash);

		return true;
	}

	public function redirectToZip()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		if ($this->projectRepository == null)
		{
			return false;
		}

		$config = Configuration::getInstance();
		$output = Output::getInstance();

		$this->checkoutProject();
		$zipPath = $output->getOutput('url');
		$basepath = str_replace('//', '/', dirname($_SERVER['SCRIPT_NAME']).'/');
		header('Location: '.$basepath.$zipPath);
	}

	private function getRootRepoPath()
	{
		$config = Configuration::getInstance();
		$repoPath = $config->getConfig('repopath');
		$repoPath = str_replace('ROOT', '.', $repoPath);
		if (!file_exists($repoPath) || !is_dir($repoPath))
		{
			mkdir($repoPath);
		}
		return $repoPath;
	}

	private function openProject($team, $project, $createOnDemand = false)
	{
		if (!in_array($project, $this->projectManager->listRepositories($team)))
		{
			if ($createOnDemand)
			{
				ide_log(LOG_INFO, "On-demand creation of project $project for team $team");
				$this->projectManager->createRepository($team, $project);
			}
			else
			{
				return;
			}
		}
		$this->updateProject();
		$this->projectRepository =
			$this->projectManager->getUserRepository($team, $project, AuthBackend::getInstance()->getCurrentUser());
	}
}
