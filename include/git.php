<?php

/**
 * A class to manage git repositories
 */
class GitRepository
{
	private $path;

	/**
	 * Constructs a git repo object on the path, will fail if the path isn't a git repository
	 */
	public function __construct($path)
	{
		$this->path = $path;
		if (!file_exists("$path/.git") || !is_dir("$path/.git"))
			throw new Exception("git repository at $path is corrupt", E_INTERNAL_ERROR);
	}

	/**
	 * Execute a command with the specified environment variables
	 */
	private function gitExecute($command, $env = array())
	{
		$file = fopen('/tmp/git-log', 'a');
		fwrite($file, "git $command [cwd = $this->path]\n");
		fclose($file);
		$buildCommand = "git $command";
		$proc = proc_open($buildCommand, array(0 => array('file', '/dev/null', 'r'),
		                                       1 => array('pipe', 'w'),
		                                       2 => array('pipe', 'w')),
		                                 $pipes,
		                                 $this->path,
		                                 $env);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		$status = proc_close($proc);
		if ($status != 0)
		{
			$file = fopen('/tmp/git-log', 'a');
			fwrite($file, "\tfailed miserably!\n");
			fwrite($file, "-- LOG --\n");
			fwrite($file, "$stderr\n");
			fwrite($file, "-- END LOG --\n");
			fclose($file);
			return false;
		}
		else
		{
			return trim($stdout);
		}
	}

	/**
	 * Creates a git repository on a specified path, fails if the path exists
	 */
	public static function createRepository($path)
	{
		if (!is_dir($path) && mkdir($path))
		{
			shell_exec("cd $path ; git init");
		}
		return new GitRepository($path);
	}

	/**
	 * Gets the most recent revision hash
	 */
	public function getCurrentRevision()
	{
		return $this->gitExecute('describe --always');
	}

	/**
	 * Gets the hash of the most recent revision
	 */
	public function getFirstRevision()
	{
		$revisions = explode("\n", $this->gitExecute('rev-list --all'));
		return $revisions[count($revisions)-1];
	}

	/**
	 * Gets the log between the arguments
	 */
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

	/**
	 * Resets the repository back to HEAD
	 */
	public function reset()
	{
		$this->gitExecute('reset --hard');
		$this->gitExecute('clean -f -d');
	}

	/**
	 * performs a git commit
	 */
	public function commit($message, $name, $email)
	{
		$tmp = tempnam('/tmp', 'ide-');
		file_put_contents($tmp, $message);
		$this->gitExecute("commit -F $tmp", array('GIT_AUTHOR_NAME'    => $name,
		                                          'GIT_AUTHOR_EMAIL'   => $email,
		                                          'GIT_COMMITER_NAME'  => $name,
		                                          'GIT_COMMITER_EMAIL' => $email));
		unlink($tmp);
	}

	/**
	 * Gets the file tree for the git repository
	 */
	public function fileTreeCompat($base)
	{
		$root = $this->path;
		$content = shell_exec("find $root/* -type f");
		$parts = explode("\n", $content);
		$parts = array_map(function($x) use($root) { return str_replace("$root/", '', $x); }, $parts);
		$parts = array_filter($parts, function($x) { return $x != ''; });
		$hash = $this->getCurrentRevision();
		return array_map(function($x) use($hash, $base)
		{
			return array('kind' => 'FILE',
			             'name' => $x,
			             'rev'  => $hash,
			             'path' => "/$base/$x",
			         'children' => array(),
			         'autosave' => 0);
		}, $parts);
	}

	/**
	 * Lists the files within the top level of the repository
	 */
	public function listFiles($path)
	{
		$files = scandir($this->path . "/$path");
		$result = array();
		foreach ($files as $file)
		{
			if ($file[0] != '.')
			{
				$result[] = $file;
			}
		}

		return $result;
	}

	/**
	 * Creates a file on the repo that is empty
	 */
	public function createFile($path)
	{
		touch($this->path . "/$path");
		$this->gitExecute("add $path");
	}

	/**
	 * Removes a file on the repo
	 */
	public function removeFile($path)
	{
		$this->gitExecute("rm -f $path");
	}

	/**
	 * Moves a file within the repo
	 */
	public function moveFile($src, $dst)
	{
		$this->copyFile($src, $dst);
		$this->removeFile($src);
	}

	/**
	 * Copies a file between src and dst
	 */
	public function copyFile($src, $dst)
	{
		$this->createFile($dst);
		$content = $this->getFile($src);
		$this->putFile($dst, $content);
	}

	/**
	 * Gets the contents of the file, optionally of a specific revision
	 */
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

	/**
	 * Writes content to a file
	 */
	public function putFile($path, $content)
	{
		file_put_contents($this->path . "/$path", $content);
		$this->gitExecute("add $path");
	}

	/**
	 * Gets a diff between two commits
	 */
	public function diff($commitOld, $commitNew)
	{
		return $this->gitExecute("diff -C -M $commitOld..$commitNew");
	}

	/**
	 * does a git clone on destination then deletes the .git directory
	 */
	public function archiveSourceZip($dest, $commit = 'HEAD')
	{
		// TODO: fix to actually obey commit
		touch($dest);
		$dest = realpath($dest);
		$this->gitExecute("archive --format=zip $commit -6 > $dest");
	}

	/**
	 * Reverts a specific commit
	 */
	public function revert($commit)
	{
		$this->gitExecute("revert $commit");
	}

}
