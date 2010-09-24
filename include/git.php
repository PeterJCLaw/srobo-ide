<?php

/**
 * A class to manage git repositories
 */
class GitRepository
{
	private $working_path;
	private $git_path;

	public function workingPath()
	{
		return $this->working_path;
	}

	public function gitPath()
	{
		return $this->git_path;
	}

	private static function gitBinaryPath()
	{
		$path = Configuration::getInstance()->getConfig('git_path');
		if (!$path)
			$path = 'git';
		return $path;
	}

	/**
	 * Constructs a git repo object on the path, will fail if the path isn't a git repository
	 */
	public function __construct($path)
	{
		if (!file_exists("$path/.git") || !is_dir("$path/.git"))
		{
			// this may be a bare repository, take a guess and see
			if (file_exists("$path/config") &&
			    file_exists("$path/objects") &&
			    is_dir("$path/objects") &&
			    file_exists("$path/branches") &&
			    is_dir("$path/branches"))
			{
				// almost certainly is
				$this->git_path = $path;
				$this->working_path = null;
			}
			else
			{
				throw new Exception("git repository at $path is corrupt", E_INTERNAL_ERROR);
			}
		}
		else
		{
			$this->working_path = $path;
			$this->git_path = "$path/.git";
		}
	}

    /**
     * Determines if a repository is bare
     */
	public function isBare()
	{
		return $this->working_path === null;
	}

    /**
     * Get the path of the git repository
     */
    public function getPath()
    {
        return $this->working_path;
    }

	/**
	 * Execute a command with the specified environment variables
	 */
	private function gitExecute($working, $command, $env = array(), $catchResult = false)
	{
		$bin = self::gitBinaryPath();
		$base = $working ? $this->working_path : $this->git_path;
		ide_log("$bin $command [cwd = $base]");
		$buildCommand = "$bin $command";
		$proc = proc_open($buildCommand, array(0 => array('file', '/dev/null', 'r'),
		                                       1 => array('pipe', 'w'),
		                                       2 => array('pipe', 'w')),
		                                 $pipes,
		                                 $base,
		                                 $env);
		$stdout = trim(stream_get_contents($pipes[1]));
		$stderr = stream_get_contents($pipes[2]);
		$status = proc_close($proc);
		if ($status != 0)
		{
			if ($catchResult)
			{
				return array(false, $stdout);
			}
			else
			{
				ide_log("\tfailed miserably!");
				ide_log("-- LOG --");
				ide_log("$stderr");
				ide_log("-- END LOG --");
			}
			return false;
		}
		else
		{
			if ($catchResult)
				return array(true, $stdout);
			else
				return trim($stdout);
		}
	}

	/**
	 * Creates a git repository on a specified path, fails if the path exists
	 */
	public static function createRepository($path, $bare = false, $source = null)
	{
		$bin = self::gitBinaryPath();
		ide_log("Creating a repository at $path (" . ($source ? "cloned" : "initial") . ")");
		if (!is_dir($path))
		{
			mkdir_full($path);
		}

		$shell_path   = escapeshellarg($path);

		if ($source !== null)
		{
			if (is_object($source))
				$source = $source->gitPath();

			$shell_source = escapeshellarg($source);
			shell_exec("$bin clone --shared --quiet " . ($bare ? "--bare " : "") .
					   "$shell_source $shell_path");
		}
		else
		{
			shell_exec("cd $shell_path ; $bin init" . ($bare ? " --bare" : ''));
			list($commitpath, $hash) = self::populateRepoObejects($shell_path);
			shell_exec("cd $shell_path ; $bin update-ref -m $commitpath refs/heads/master $hash");
		}
		list($commitpath, $hash) = self::populateRepoObejects($shell_path);
		shell_exec("cd $shell_path ; $bin update-ref -m $commitpath HEAD $hash");
		shell_exec("cd $shell_path ; $bin update-ref -m $commitpath refs/heads/master $hash");
		return new GitRepository($path);
	}

	/**
	 * Construct some of the internals of the git repo we're going to use.
	 */
	private static function populateRepoObejects($path)
	{
		$bin = self::gitBinaryPath();
		$hash = trim(shell_exec("cd $path ; $bin hash-object -w /dev/null"));
		$treepath = realpath('resources/base-tree');
		$commitpath = realpath('resources/initial-commit');
		$hash = trim(shell_exec("cd $path ; cat $treepath | sed s/_HASH_/$hash/g | $bin mktree"));
		$hash = trim(shell_exec("cd $path ; cat $commitpath | $bin commit-tree $hash"));
		return array($commitpath, $hash);
	}

	/**
	 * Gets the most recent revision hash
	 */
	public function getCurrentRevision()
	{
		return $this->gitExecute(true, 'describe --always');
	}

	/**
	 * Gets the hash of the most recent revision
	 */
	public function getFirstRevision()
	{
		$revisions = explode("\n", $this->gitExecute(false, 'rev-list --all'));
		return $revisions[count($revisions)-1];
	}

	/**
	 * Gets the log between the arguments
	 */
	public function log($oldCommit, $newCommit, $file=null)
	{
		$log = null;
		$logCommand = "log -M -C --pretty='format:%H;%aN <%aE>;%at;%s'";
		if ($file == null)
		{
			$log = $this->gitExecute(false, $logCommand);
		}
		else
		{
			$log = $this->gitExecute(false, $logCommand.' '.escapeshellarg($file));
		}
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
	 * Fetches changes.
	 */
	public function fetch()
	{
		$this->gitExecute(true, 'fetch origin');
	}

	/**
	 * Does an n-way merge.
	 */
	public function merge($branches)
	{
		$mergeOptions = array('--no-stat',
		                      '--quiet');
		list($success, $message) = $this->gitExecute(true, 'merge '
		                                             . implode(' ', $mergeOptions)
		                                             . ' '
		                                             . implode(' ', $branches));
		if ($success)
			return array();
		else
		{
			$conflicted_files = array();
			$lines = explode("\n", $message);
			foreach ($lines as $line)
			{
				if (preg_match('/^CONFLICT \\(content\\): Merge conflict in (.+)$', $line, $death))
				{
					$conflicted_files[] = $death;
				}
			}
			return $conflicted_files;
		}
	}

	/**
	 * Pushes changes upstream.
	 */
	public function push()
	{
		$this->gitExecute(true, 'push origin master');
	}

	/**
	 * Resets the repository back to HEAD
	 */
	public function reset()
	{
		$this->gitExecute(true, 'reset --hard');
		$this->gitExecute(true, 'clean -f -d');
	}

	/**
	 * performs a git commit
	 */
	public function commit($message, $name, $email)
	{
		$tmp = tempnam('/tmp', 'ide-');
		file_put_contents($tmp, $message);
		$this->gitExecute(true, "commit -F $tmp", array('GIT_AUTHOR_NAME'    => $name,
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
		$root = $this->working_path;
		$shell_root = escapeshellarg($root);
		$content = shell_exec("find $shell_root/* -type f");
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
		$files = scandir($this->working_path . "/$path");
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
		touch($this->working_path . "/$path");
		$shell_path = escapeshellarg($path);
	}

	/**
	 * Removes a file on the repo
	 */
	public function removeFile($path)
	{
		$shell_path = escapeshellarg($path);
		$this->gitExecute(true, "rm -f $shell_path");
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
			return file_get_contents($this->working_path . "/$path");
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
		file_put_contents($this->working_path . "/$path", $content);
	}

    /**
     * Stages a file
     */
    public function stage($path) {
		$shell_path = escapeshellarg($path);
		$this->gitExecute(true, "add $shell_path");
    }

	/**
	 * Gets a diff:
	 *  that a log entry provides,
	 *  between two commits,
	 *  between two commits for a specified file
	 */
	public function historyDiff($commitOld, $commitNew=null, $file=null)
	{
		if ($commitNew === null)
		{
			return $this->gitExecute(false, "log -p -1 $commitOld");
		}

		$command = 'diff -C -M '.$commitOld.'..'.$commitNew;
		if ($file === null)
		{
			return $this->gitExecute(false, $command);
		}
		else
		{
			return $this->gitExecute(false, $command.' -- '.escapeshellarg($file));
		}
	}

	/**
	 * Gets the currently cached diff, optionally for just one file
	 */
	public function cachedDiff($file=null)
	{
		$command = 'diff --cached';
		if ($file === null)
		{
			return $this->gitExecute(true, $command);
		}
		else
		{
			return $this->gitExecute(true, $command.' '.escapeshellarg($file));
		}
	}

	/**
	 * does a git clone on destination then deletes the .git directory
	 */
	public function archiveSourceZip($dest, $commit = 'HEAD')
	{
		// TODO: fix to actually obey commit
		touch($dest);
		$dest = realpath($dest);
		$shell_dest = escapeshellarg($dest);
		$this->gitExecute(true, "archive --format=zip $commit -6 > $shell_dest");
	}

	/**
	 * Reverts a specific commit
	 */
	public function revert($commit)
	{
		$this->gitExecute(false, "revert $commit");
	}

}
