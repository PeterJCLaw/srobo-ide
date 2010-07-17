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

	public function __construct()
	{
		$config = Configuration::getInstance();
		$this->setRootProjectPath(str_replace('ROOT', '.', $config->getConfig('repopath')));
	}

	public function setRootProjectPath($rpp)
	{
		if (!is_dir($rpp))
		{
			throw new Exception(2, "couldn't find project dir");
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
		$contents = array_filter(scandir($this->teamDirectory($team)),
		                         function ($x) { return $x[0] != '.'; });
		sort($contents);
		return $contents;
	}

	public function teamDirectory($team)
	{
		$dir = $this->rootProjectPath . '/' . $team;
		if (!is_dir($dir))
			mkdir($dir);
		return $dir;
	}

	public function getRepository($team, $path)
	{
		return new GitRepository($this->teamDirectory($team) . '/' . $path);
	}

	public function createRepository($team, $name)
	{
		return GitRepository::createRepository($this->teamDirectory($team) . '/' . $path);
	}

	public function deleteRepository($team, $path)
	{
		// DELETE EVERYTHING RAAAAARGH
		$path = $this->teamDirectory($team) . '/' . $path;
		shell_exec("rm -rf $path");
	}
}
