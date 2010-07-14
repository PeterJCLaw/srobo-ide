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

	private function gitExecute($command, $env = array())
	{
		$path = $this->path;
		$command = "cd $path ; ";
		if (!empty($env))
		{
			$command .= "env ";
			foreach ($env as $key=>$value)
			{
				$command .= escapeshellarg("$key=$value ");
			}
		}
		$command .= "git $command";
		return trim(shell_exec($command));
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

	public function reset()
	{
	}

	public function commit($message, $name, $email)
	{
	}

	public function listFiles($path)
	{
	}

	public function createFile($path)
	{
	}

	public function removeFile($path)
	{
	}

	public function getFile($path, $commit = null) // pass $commit to get a particular revision
	{
	}

	public function putFile($path, $content)
	{
	}

	public function diff($commitOld, $commitNew)
	{
	}

	public function getSource($dest, $commit = 'HEAD')
	{
	}

	public function revert($commit)
	{
	}
}
