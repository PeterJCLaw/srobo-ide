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
		$this->installCommand("copy", array($this, "copyProject"));
		$this->installCommand('zip', array($this, 'redirectToZip'));
		$this->installCommand('update', array($this, 'updateProject'));
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
	}

	public function copyProject()
	{
		$input = Input::getInstance();
		$this->projectManager->copyRepository($this->team, $this->projectName, $input->getInput("new-name"));
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

		$files = $input->getInput("paths");
		//gaurd project state by doing a reset
		$this->projectRepository->unstageAll();

		//stage the files
		foreach ($files as $file)
		{
			$this->projectRepository->stage($file);
		}

		$this->projectRepository->commit($message,
		                                 $currentUser,
		                                 "$currentUser@srobo.org");
		$conflicts = $this->projectManager->updateRepository($this->team,
		                                                     $this->projectName,
		                                                     $currentUser);
		$output->setOutput('merges', $conflicts);
		$output->setOutput('commit', $this->projectRepository->getCurrentRevision());
		$output->setOutput('success', true);
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

		$teamdir = $config->getConfig('zippath') . '/' . $this->team;
		if (!is_dir($teamdir))
		{
			mkdir($teamdir);
		}
		$projdir = "$teamdir/$this->projectName";
		if (!is_dir($projdir))
		{
			mkdir($projdir);
		}

		delete_recursive($projdir.'/*');
		$hash = $input->getInput('rev');
    ide_log("faces1");
		$this->projectRepository->archiveSourceZip("$projdir/robot.zip", $hash);
        $this->rezip($projdir);
    ide_log("faces2");
    $this->projectRepository->writePyenvTo($projdir);
    ide_log("faces3");
    $this->completeArchive($projdir);
    ide_log("faces4");


	$output->setOutput('url', $config->getConfig('zipurl') . "/$this->team/$this->projectName/robot.zip");
	}


    public function rezip($projdir) {
        $projdir = escapeshellarg($projdir);
        $moveto = tempnam("/tmp/", "robot");
        shell_exec("cd $projdir && unzip robot.zip && rm -f robot.zip && zip robot.zip * && mv robot.zip /tmp/$rand.zip && rm * && mv /tmp/$rand.zip ./robot.zip");
    }

  public function completeArchive($projdir)
  {
    ide_log("archiving: zip -r $projdir/robot.zip $projdir/");
        $projdir = escapeshellarg($projdir);
    shell_exec("cd $projdir && zip -r robot_t.zip *");
    shell_exec("mv $projdir/robot_t.zip $projdir/robot.zip");
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
		$input = Input::getInstance();

		$zipPath = $config->getConfig('zippath').'/'.$this->team.'/'.$this->projectName.'/robot.zip';
		if (!is_file($zipPath))
		{
			$this->checkoutProject();
		}
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
				ide_log("On-demand creation of project $project for team $team");
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
