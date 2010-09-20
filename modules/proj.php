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
		$this->team = $input->getInput('team', true);

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput('project', true);

		if ($this->team && $project)
		{
			$this->openProject($this->team, $project);
			$this->projectName = $project;
		}

		$this->installCommand('new', array($this, 'createProject'));
		$this->installCommand('info', array($this, 'projectInfo'));
		$this->installCommand('log', array($this, 'projectLog'));
		$this->installCommand('del', array($this, 'deleteProject'));
		$this->installCommand('commit', array($this, 'commitProject'));
		$this->installCommand('co', array($this, 'checkoutProject'));
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
		$this->openProject($this->team, $this->projectName);
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

		$this->projectRepository->commit($message,
		                                 $auth->displayNameForUser($currentUser),
		                                 $auth->emailForUser($currentUser));
		$conflicts = $this->projectManager->updateRepository($this->team,
		                                                     $this->projectName,
		                                                     $currentUser);
		$output->setOutput('merges', $conflicts);
		$output->setOutput('commit', $this->projectRepository->getCurrentRevision());
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

		if ($currRev == null)
		{
			$currRev = $this->projectRepository->getCurrentRevision();
		}

		$firstRev = $input->getInput('end-commit', true);

		if ($firstRev == null)
		{
			$firstRev = $this->projectRepository->getFirstRevision();
		}

		$output->setOutput('log', $this->projectRepository->log($firstRev, $currRev));
		return TRUE;
	}

	public function checkoutProject()
	{
		$this->verifyTeam();
		$config = Configuration::getInstance();

		//bail if we aren't in a repo
		if ($this->projectRepository == null)
		{
			return false;
		}

		$output = Output::getInstance();
		$input = Input::getInstance();

		$teamdir = $config->getConfig('zippath') . '/' . $this->team;
		if (!is_dir($teamdir))
			mkdir($teamdir);
		$projdir = "$teamdir/$this->projectName";
		if (!is_dir($projdir))
			mkdir($projdir);
		$this->projectRepository->archiveSourceZip("$projdir/robot.zip");

		$output->setOutput('url', $config->getConfig('zipurl') . "/$this->team/$this->projectName/robot.zip");
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

	private function openProject($team, $project)
	{
		if (!in_array($team, $this->projectManager->listRepositories($team)))
			$this->projectManager->createRepository($team, $project);
		$this->projectRepository =
		    $this->projectManager->getUserRepository($team, $project, AuthBackend::getInstance()->getCurrentUser());
	}
}
