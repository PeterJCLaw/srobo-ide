<?php

class GitRepository
{
	private $path;

	public function __construct($path)
	{
		$this->path = $path;
		if (!file_exists("$path/.git") || !is_dir("$path/.git"))
			throw new Exception("git repository at $path is corrupt", E_INTERNAL_ERROR);
	}

	private function gitExecute($cmd, $env = array())
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
		$command .= "git $cmd";
		return trim(shell_exec($command));
	}

	public static function createRepository($path)
	{
		if (@mkdir($path))
		{
			shell_exec("cd $path ; git init");
		}
		return new GitRepository($path);
	}

	public function getCurrentRevision()
	{
		return $this->gitExecute("describe --always");
	}

	public function log($oldCommit, $newCommit)
	{
		$log = $this->gitExecute("log -M -C --pretty='format:%H;%aN <%aE>;%at;%s'");
		$lines = explode("\n", $log);
		$results = array();
		foreach ($lines as $line)
		{
			$exp     = explode(';', $line);
			$hash    = array_shift($exp);
			$author  = array_shift($exp);
			$time    = (int)array_shift($exp);
			$message = implode(';', $exp);
			$results[] = array('hash'    => $hash,
			                   'author'  => $author,
			                   'time'    => $time,
			                   'message' => $message);
		}
		return $results;
	}

	public function reset()
	{
		$this->gitExecute("reset --hard");
		$this->gitExecute("clean -f -d");
	}

	public function commit($message, $name, $email)
	{
		$tmp = tempnam("/tmp", "ide-");
		file_put_contents($tmp, $message);
		$this->gitExecute("commit -F $tmp", array('GIT_AUTHOR_NAME'    => $name,
		                                          'GIT_AUTHOR_EMAIL'   => $email,
		                                          'GIT_COMMITER_NAME'  => $name,
		                                          'GIT_COMMITER_EMAIL' => $email));
		unlink($tmp);
	}

	public function listFiles($path)
	{
        $files = scandir($this->path . $path);
        $result = array();
        foreach ($files as $file)
        {
            if ($file[0] != ".")
            {
                $result[] = file;
            }

        }

        return $result;
	}

	public function createFile($path)
	{
		file_put_contents('', $this->path . "/$path");
		$this->gitExecute("add $path");
	}

	public function removeFile($path)
	{
		$this->gitExecute("rm -f $path");
	}

	public function moveFile($src, $dst)
	{
		$this->copyFile($src, $dst);
		$this->removeFile($src);
	}

	public function copyFile($src, $dst)
	{
		$this->createFile($dst);
		$content = $this->getFile($src);
		$this->putFile($dst, $content);
	}

	public function getFile($path, $commit = null) // pass $commit to get a particular revision
	{
		if ($commit === null)
		{
			return file_get_contents($this->path . "/$path");
		}
		else
		{
			return '';
		}
	}

	public function putFile($path, $content)
	{
		file_put_contents($this->path . "/$path", $content);
		$this->gitExecute("add $path");
	}

	public function diff($commitOld, $commitNew)
	{
		return $this->gitExecute("diff -C -M $commitOld..$commitNew");
	}

	public function cloneSource($dest, $commit = 'HEAD')
	{
		// TODO: fix to actually obey commit
		shell_exec("git clone " . $this->path . " $commit");
		shell_exec("rm -rf $dest/.git");
	}

	public function revert($commit)
	{
		$this->gitExecute("revert $commit");
	}
}
