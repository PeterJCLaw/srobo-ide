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
		//copy the master repository
		$masterPathOld = $this->getMasterRepoPath($team, $project);
		$masterPathNew = $this->getMasterRepoPath($team, $new);
		$s_old = escapeshellarg($masterPathOld);
		$s_new = escapeshellarg($masterPathNew);
		shell_exec("cp -r $s_old $s_new");
	}

	public function getMasterRepoPath($team, $project) {
		return $this->rootProjectPath . "/$team/master/$project.git";
	}

	public function getMasterRepository($team, $project)
	{
		$path = $this->getMasterRepoPath($team, $project);
		return new GitRepository($path);
	}

	public function getUserRepository($team, $project, $user)
	{
		$path = $this->rootProjectPath . "/$team/users/$user/$project";
		// return a handle to an existing checkout
		if (file_exists($path))
		{
			return new GitRepository($path);
		}
		// the user needs a clone of an existing master
		else
		{
			$master = $this->getMasterRepository($team, $project);
			$masterPath = $master->gitPath();
			return GitRepository::cloneRepository($masterPath, $path);
		}
	}

	public function updateRepository($team, $project, $user)
	{
		// grab the repo
		$userRepo = $this->getUserRepository($team, $project, $user);
		// grab unstaged changes
		$unstaged = $userRepo->unstagedChanges();
		// grab a list of all the folders as this won't be included in the unstagedChanges
		$folders = $userRepo->listFolders();
		// read them all
		$unstagedFiles = array();
		foreach ($unstaged as $key)
		{
			$unstagedFiles[$key] = $userRepo->getFile($key);
		}
		// reset --hard
		$userRepo->reset();
		// fetch
		$userRepo->fetch();
		// merge
		$conflicts = $userRepo->merge(array('origin/master'));
		// rewrite folders
		foreach ($folders as $path)
		{
			$userRepo->gitMKDir($path);
		}
		// rewrite files
		foreach ($unstagedFiles as $path => $data)
		{
			$userRepo->putFile($path, $data);
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
		$path = $this->rootProjectPath . "/$team/master/$project.git";
		GitRepository::createRepository($path, true);
		ide_log("Created a project $project for team $team");
	}

	public function deleteRepository($team, $project)
	{
		delete_recursive($this->rootProjectPath . "/$team/master/$project.git");
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
