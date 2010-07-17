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
		if (!is_string($name) || $name == '')
			throw new Exception('name was not a name', 2);
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
			throw new Exception("couldn't find project dir: $rpp", 2);
		}
		$this->rootProjectPath = $rpp;
	}

	public function rootProjectPath()
	{
		return $this->rootProjectPath;
	}

	public function listTeams()
	{
		$contents = array_filter(scandir($this->rootProjectPath),
		                         function ($x) { return $x[0] != '.'; });
		sort($contents);
		return $contents;
	}

	public function listRepositories($team)
	{
		$this->verifyName($team);
		$contents = array_filter(scandir($this->teamDirectory($team)),
		                         function ($x) { return $x[0] != '.'; });
		sort($contents);
		return $contents;
	}

	public function teamDirectory($team)
	{
		$this->verifyName($team);
		$dir = $this->rootProjectPath . '/' . $team;
		if (!is_dir($dir))
			mkdir($dir);
		return $dir;
	}

	public function getRepository($team, $path)
	{
		$this->verifyName($team);
		$this->verifyName($name);
		return new GitRepository($this->teamDirectory($team) . '/' . $name);
	}

	public function createRepository($team, $name)
	{
		$this->verifyName($team);
		$this->verifyName($name);
		return GitRepository::createRepository($this->teamDirectory($team) . '/' . $name);
	}

	public function deleteRepository($team, $path)
	{
		$this->verifyName($team);
		$this->verifyName($path);
		// DELETE EVERYTHING RAAAAARGH
		$path = $this->teamDirectory($team) . '/' . $path;
		shell_exec("rm -rf $path");
	}
}
