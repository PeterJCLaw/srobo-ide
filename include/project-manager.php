<?php

class ProjectManager
{
	private static $singleton = null;
	private $rootProjectPath;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new ProjectManager();
		return self::$singleton;
	}

	private function verifyName($name)
	{
		if ((!is_int($name) && !is_string($name)) || $name === '')
			throw new Exception('name was not a name', E_INTERNAL_ERROR);
	}

	public function __construct()
	{
		$config = Configuration::getInstance();
		$this->setRootProjectPath(str_replace('ROOT', '.', $config->getConfig('repopath')));
	}

	public function setRootProjectPath($rpp)
	{
		if (!is_dir($rpp))
		{
			throw new Exception("couldn't find project dir: $rpp", E_INTERNAL_ERROR);
		}
		$this->rootProjectPath = $rpp;
	}

	public function rootProjectPath()
	{
		return $this->rootProjectPath;
	}

	public function listTeams()
	{
		if (!file_exists($this->rootProjectPath))
			return array();
		$scan = scandir($this->rootProjectPath);
		$scan = array_filter($scan, function($item) {
			return $item != '' && $item[0] != '.';
		});
		return $scan;
	}

	public function listRepositories($team)
	{
		$root = $this->rootProjectPath . '/' . $team . '/master/';
		if (!file_exists($root))
			return array();
		$scan = scandir($root);
		$projects = array();
		foreach ($scan as $item)
		{
			if (preg_match('/^([^\"]+)\\.git$/', $item, $matches))
			{
				$projects[] = $matches[1];
			}
		}
		return $projects;
	}

	public function copyRepository($team, $project, $new)
	{
		if (strpos($new, '/') !== FALSE)
		{
			return FALSE;
		}
		//copy the master repository
		$masterPathOld = $this->getMasterRepoPath($team, $project);
		$masterPathNew = $this->getMasterRepoPath($team, $new);
		$ret = copy_recursive($masterPathOld, $masterPathNew);
		return $ret;
	}

	public function getMasterRepoPath($team, $project)
	{
		return $this->rootProjectPath . "/$team/master/$project.git";
	}

	public function getMasterRepository($team, $project)
	{
		$path = $this->getMasterRepoPath($team, $project);
		return GitRepository::GetOrCreate($path);
	}

	public function getUserRepository($team, $project, $user)
	{
		$path = $this->rootProjectPath . "/$team/users/$user/$project";
		// return a handle to an existing checkout
		if (file_exists($path))
		{
			return GitRepository::GetOrCreate($path);
		}
		// the user needs a clone of an existing master
		else
		{
			$masterPath = $this->getMasterRepoPath($team, $project);
			// Get an instance of the master repo to lock it.
			// Since the user repo we're creating doesn't exist, we can't
			// lock it so we lock the master instead. This prevents any
			// other requests for this user repo from trying to create it
			// while we're doing so.
			$masterRepo = GitRepository::GetOrCreate($masterPath);

			// check this is valid (throws if not) - master repos are bare.
			GitRepository::EnsureBareRepo($masterPath);
			$userRepo = GitRepository::cloneRepository($masterPath, $path);

			// Force-clear our lock on the master
			$masterRepo = null;

			return $userRepo;
		}
	}

	public function updateRepository($team, $project, $user)
	{
		// grab the repo
		$userRepo = $this->getUserRepository($team, $project, $user);
		// grab unstaged changes
		$unstaged = $userRepo->unstagedChanges();
	//	echo 'unstaged: ';
	//	var_dump($unstaged);
		// grab a list of all the folders as this won't be included in the unstagedChanges
		$folders = $userRepo->listFolders();
		// read them all
		$unstagedFiles = array();
		$fileTimes = array();
		foreach ($unstaged as $key)
		{
			$unstagedFiles[$key] = $userRepo->getFile($key);
			$fileTimes[$key] = $userRepo->fileMTime($key);
		}
	//	echo 'unstagedFiles: ';
	//	var_dump($unstagedFiles);
	//	echo 'fileTimes: ';
	//	var_dump($fileTimes);
		// reset --hard
		$userRepo->reset();
		// fetch
		$userRepo->fetch();
		// merge
		$email = UserInfo::makeCommitterEmail($user);
		$conflicts = $userRepo->merge(array('origin/master'), $user, $email);
		// rewrite folders
		foreach ($folders as $path)
		{
			$userRepo->gitMKDir($path);
		}
	//	echo 'unstagedFiles after mkdirs: ';
	//	var_dump($unstagedFiles);
		// rewrite files
		foreach ($unstagedFiles as $path => $data)
		{
			if ($data === FALSE)
			{
				$userRepo->removeFile($path);
			}
			else
			{
				$userRepo->putFile($path, $data);
				$userRepo->touchFile($path, $fileTimes[$path]);
			}
		}
		// check for conflicts
		if (empty($conflicts))
		{
			$userRepo->push();
			return array();
		}
		else
		{
			return $conflicts;
		}
	}

	public function createRepository($team, $project)
	{
		if (strpos($project, '/') !== FALSE)
		{
			return FALSE;
		}
		$path = $this->getMasterRepoPath($team, $project);
		$repo = GitRepository::createRepository($path, true);
		ide_log(LOG_INFO, "Created a project $project for team $team");
		return $repo;
	}

	public function deleteRepository($team, $project)
	{
		$master_path = $this->getMasterRepoPath($team, $project);
		delete_recursive($master_path);
		$users = scandir($this->rootProjectPath . "/$team/users");
		foreach ($users as $user)
		{
			if ($user[0] != '.')
			{
				delete_recursive($this->rootProjectPath . "/$team/users/$user/$project.git");
			}
		}
	}
}
