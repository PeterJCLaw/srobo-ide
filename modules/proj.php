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

		$commitResult = $this->projectRepository->commit($message,
		                                                 $currentUser,
		                                                 "$currentUser@srobo.org");
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

		$servePath = $config->getConfig('zipurl') . "/$this->team/$this->projectName/$hash";

		// ensure that the serve folder exists
		if (!file_exists($servePath) && !mkdir_full($servePath))
		{
			// can't do anything if there's no folder for us to use
			return false;
		}

		// get a fresh tmpdir so there can't possibly be clashes.
		$tmpDir = tmpdir();

		$this->projectRepository->archiveSourceZip("$tmpDir/robot.zip", $hash);

		$this->unzip($tmpDir);
		$this->pyenvZip($tmpDir, $servePath);
		$output->setOutput('url', "$servePath/robot.zip");

		// remove our temporary folder so that we don't fill up /tmp
		delete_recursive($tmpDir);
		return true;
	}

	private function unzip($path)
	{
		$s_path = escapeshellarg($path);
		$ret = shell_exec("cd $s_path && unzip robot.zip && rm -f robot.zip");
		return $ret;
	}

	private function pyenvZip($path, $servePath)
	{
		$s_path = escapeshellarg($path);
		$s_servePath = escapeshellarg($servePath);
		$ret = shell_exec("python2.7 pyenv/make-zip $s_path $s_servePath/robot.zip");
		return $ret;
	}

	public function completeArchive($projdir)
	{
		ide_log("archiving: zip -r $projdir/robot.zip $projdir/");
		$s_projdir = escapeshellarg($projdir);
		shell_exec("cd $s_projdir && zip -r robot_t.zip *");
		shell_exec("mv $s_projdir/robot_t.zip $s_projdir/robot.zip");
	}

	private function fastwrap($oldname, $newname)
	{
		$config = Configuration::getInstance();
		$fastwrapScript = $config->getConfig('fastwrap_script_path');
		$fastwrapDir = $config->getConfig('fastwrap_dir_path');

		$s_fastwrapScript = escapeshellarg($fastwrapScript);
		$s_fastwrapDir    = escapeshellarg($fastwrapDir);
		$s_oldname        = escapeshellarg($oldname);
		$s_newname        = escapeshellarg($newname);

		$ret = shell_exec("$s_fastwrapScript $s_oldname $s_newname $s_fastwrapDir/*");
		return $ret;
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
