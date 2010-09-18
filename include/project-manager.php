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
		$scan = scandir($this->rootProjectPath);
		$scan = array_filter($scan, function($item) {
			return $item != '' && $item[0] != '.';
		});
		return $scan;
	}

	public function listRepositories($team)
	{
		$root = $this->rootProjectPath . '/' . $team . '/master/';
		$scan = scandir($root);
		$projects = array();
		foreach ($scan as $item)
		{
			if (preg_match('/([a-zA-Z0-9_- ])+\\.git$/', $item, $matches))
				$projects[] = $matches[1];
		}
		return $projects;
	}

	public function getMasterRepository($team, $name)
	{
		$path = $this->rootProjectPath . "/$team/master/$name.git";
		return new GitRepository($path);
	}

	public function getUserRepository($team, $name, $user)
	{
		$path = $this->rootProjectPath . "/$team/users/$user/$project";
		if (file_exists($path))
		{
			return new GitRepository($path);
		}
		else
		{
			return GitRepository::createRepository($path, false,
			                                       $this->getMasterRepository($team, $name));
		}
	}

	public function createRepository($team, $name)
	{
		$path = $this->rootProjectPath . "/$team/master/$name.git";
		GitRepository::createRepository($path, true);
	}

	public function deleteRepository($team, $name)
	{
		// TODO: implement me
	}
}
