<?php

class GitRepository
{
	private $path;

	public function __construct($path)
	{
		$this->path = $path;
		if (!file_exists("$path/.git") || !is_dir("$path/.git"))
			throw new Exception("git repository is corrupt", 2);
	}

	private function gitExecute($command)
	{
		$path = $this->path;
		return trim(shell_exec("cd $path ; git $command"));
	}

	public static function createRepository($path)
	{
		mkdir($path);
		shell_exec("cd $path ; git init");
		return new GitRepository($path);
	}

	public function getCurrentRevision()
	{
		return $this->gitExecute("describe --always");
	}
}
