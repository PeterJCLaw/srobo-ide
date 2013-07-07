<?php

class ProjModule extends Module
{
	private $team;
	private $projectName;
	private $projectManager;

	public function __construct()
	{
		// Hook our cron hanlder. This runs even without a user.
		$mm = ModuleManager::getInstance();
		if ($mm->moduleExists('cron') && ($cron = $mm->getModule('cron')) != null)
		{
			$cron->subscribe(__CLASS__, array($this, 'cron'));
		}

		$auth = AuthBackend::getInstance();

		// bail if we aren't authenticated
		if ($auth->getCurrentUserName() == null)
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
		$this->installCommand('search', array($this, 'searchProject'));
	}

	protected function initModule()
	{
		$this->projectManager = ProjectManager::getInstance();

		$input = Input::getInstance();
		$this->team = $input->getInput('team');

		$project = $input->getInput('project');
		$this->projectName = $project;
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
		$this->verifyTeam();
		$config = Configuration::getInstance();
		$repo_clone_url = $config->getConfig('repo_clone_url');

		$auth = AuthBackend::getInstance();
		$userName = $auth->getCurrentUserName();
		var_dump($repo_clone_url);
		$repo_clone_url = sprintf($repo_clone_url, $userName, $this->team, $this->projectName);
		var_dump($repo_clone_url);

		$output = Output::getInstance();
		$output->setOutput('repoUrl', $repo_clone_url);
		return true;
	}

	public function commitProject()
	{
		$this->verifyTeam();

		$repo = $this->openProject($this->team, $this->projectName);
		if ($repo == null)
		{
			return false;
		}

		$output = Output::getInstance();
		$input  = Input::getInstance();
		$auth   = AuthBackend::getInstance();
		$message = $input->getInput('message');

		$currentUser = $auth->getCurrentUserName();

		$files = $input->getInput("paths");
		//gaurd project state by doing a reset
		$repo->unstageAll();

		//stage the files
		foreach ($files as $file)
		{
			$repo->stage($file);
		}

		$currentUserEmail = UserInfo::makeCommitterEmail($currentUser);
		$commitResult = $repo->commit($message,
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
		$output->setOutput('commit', $repo->getCurrentRevision());
		return true;
	}

	public function projectLog()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		$repo = $this->projectManager->getMasterRepository($this->team, $this->projectName);
		if ($repo == null)
		{
			return false;
		}

		$output = Output::getInstance();
		$input = Input::getInstance();
		$currRev = $input->getInput('start-commit', true);
		$firstRev = $input->getInput('end-commit', true);

		// if the revisions are null then it just grabs the whole log
		$output->setOutput('log', $repo->log($firstRev, $currRev));
		return true;
	}

	public function searchProject()
	{
		$repo = $this->openProject($this->team, $this->projectName);
		if ($repo == null)
		{
			return false;
		}

		$input = Input::getInstance();
		$query = $input->getInput('query');
		$is_regex = FALSE; // $input->getInput('regex');

		$output = Output::getInstance();
		$raw_results = $repo->grep($query, $is_regex);
		var_dump($raw_results);
		$results = array();
		foreach ($raw_results as $fileName => $matches)
		{
			$fpath = "/$this->projectName/$fileName";
			$results[$fpath] = $matches;
		}
		$output->setOutput('results', $results);
		return true;
	}

	public function checkoutProject()
	{
		$this->verifyTeam();

		//bail if we aren't in a repo
		$repo = $this->projectManager->getMasterRepository($this->team, $this->projectName);
		if ($repo == null)
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
			$hash = $repo->getCurrentRevision();
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

		$helper = new CheckoutHelper($repo, $this->team);
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
				return null;
			}
		}

		$this->verifyTeam();
		$userName = AuthBackend::getInstance()->getCurrentUserName();
		$repo = $this->projectManager->getUserRepository($this->team, $this->projectName, $userName);
		return $repo;
	}

	public function cron()
	{
		$config = Configuration::getInstance();
		$zipRoot = $config->getConfig('zipurl');
		$zip_max_age = $config->getConfig('zips.max_age');
		$zip_max_age *= 60;	// input is minutes

		$now = time();
		$zip_delete_date = $now - $zip_max_age;	// $zip_max_age ago

		$overall = true;

		$dirIterator = new RecursiveDirectoryIterator($zipRoot);
		foreach (new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::CHILD_FIRST) as $file)
		{
			// Remove all zip files
			if ($file->isFile())
			{
				$ext = $file->getExtension();
				$last_access = $file->getATime();
				// Only zip files that haven't been accessed recently
				if ($ext == 'zip' && $last_access < $zip_delete_date)
				{
					$result = unlink($file);
					$overall = $overall && $result;
				}
			}
			// Remove all the empty child folders
			elseif ($file->isDir())
			{
				// avoid trying to interact with the current folder
				// or the parent folder. Trying to remove these here would
				// probably error, and almost certainly have odd results.
				$base_name = $file->getBasename();
				if ($base_name == '.' || $base_name == '..')
				{
					continue;
				}

				$name = $file->getPathname();
				$items = scandir($name);
				if (count($items) == 2) // . & ..
				{
					rmdir($name);
				}
			}
		}

		return $overall;
	}
}
