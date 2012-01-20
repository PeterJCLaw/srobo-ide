<?php

//define a compression level constant, must be between 0 and 9
// set to 0 since we have to re-zip it anyway
define('COMPRESSION_LEVEL', 0);

/**
 * A class to manage git repositories
 */
class GitRepository
{
	private $working_path;
	private $git_path;
	private $lock_fd;

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

	public static function badCharacters()
	{
		return array(':', '"');
	}

	public static function checkPath($path)
	{
		$badChars = self::badCharacters();
		foreach ($badChars as $char)
		{
			if (strpos($path, $char) !== FALSE)
			{
				throw new Exception("Invalid character ($char) found in path.", E_MALFORMED_REQUEST);
			}
		}
	}

	/**
	 * Constructs a git repo object on the path, will fail if the path isn't a git repository.
	 * This factory method manages caching of the handles such that threads can't deadlock.
	 */
	public static function GetOrCreate($path)
	{
		static $handles = array();
		if (!isset($handles[$path]))
		{
			$handles[$path] = new GitRepository($path);
		}
		return $handles[$path];
	}

	/**
	 * Constructs a git repo object on the path, will fail if the path isn't a git repository
	 */
	private function __construct($path)
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

		/* Acquire an exclusive lock on the git repository */
		$lockfile = "$this->git_path/cyanide-lock";
		ide_log(LOG_DEBUG, "Creating a lock on '$lockfile'.");
		$this->lock_fd = fopen( $lockfile, "w" );
		fwrite( $this->lock_fd, getmypid() );
		flock( $this->lock_fd, LOCK_EX );
		ide_log(LOG_DEBUG, "Got a lock on '$lockfile': '$this->lock_fd'.");
	}

	public function __destruct()
	{
		/* Free our lock on the repository - manually since PHP 5.3.2 */
		ide_log(LOG_DEBUG, "Dropping lock on '$this->lock_fd'.");
		flock($this->lock_fd, LOCK_UN);
		fclose( $this->lock_fd );
		ide_log(LOG_DEBUG, "Closed '$this->lock_fd'.");
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

	public function unstageAll()
	{
		$this->gitExecute(true, "reset");
	}

	/**
	 * Execute a git command with the specified environment variables.
	 * @param working: Whether or not the command should be run from a working checkout.
	 * @param s_command: The command to run, already escaped for the command line.
	 * @parm env: An array with the environment variables for the command that will be run.
	 * @parm catchResult: Whether or not to catch the result in the event of failure.
	 * @returns: If not catching failures (see catchResult) then either the process's stdout if the call succeeds or False otherwise.
	 *           If catching failures then an array whose first element is a boolean success indicator, and whose second contains the process's stdout.
	 */
	private function gitExecute($working, $s_command, $env = array(), $catchResult = false)
	{
		$s_bin = escapeshellarg(self::gitBinaryPath());
		$base = $working ? $this->working_path : $this->git_path;
		ide_log(LOG_DEBUG, "$s_bin $s_command [cwd = $base]");
		$s_buildCommand = "$s_bin $s_command";
		$proc = proc_open($s_buildCommand, array(0 => array('file', '/dev/null', 'r'),
		                                       1 => array('pipe', 'w'),
		                                       2 => array('pipe', 'w')),
		                                 $pipes,
		                                 $base,
		                                 $env);
		$stdout = stream_get_contents($pipes[1]);
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
				ide_log(LOG_ERR, "$s_bin $s_command [cwd = $base]");
				ide_log(LOG_ERR, "\tfailed miserably with exit code $status!");
				ide_log(LOG_ERR, "-- LOG --");
				ide_log(LOG_ERR, "$stderr");
				ide_log(LOG_ERR, "-- END LOG --");
			}
			return false;
		}
		else
		{
			if ($catchResult)
				return array(true, $stdout);
			else
				return $stdout;
		}
	}

	/**
	 * Creates a git repository on a specified path, fails if the path exists
	 */
	public static function createRepository($path, $bare = false, $source = null)
	{
		self::checkPath($path);
		$s_bin = escapeshellarg(self::gitBinaryPath());
		ide_log(LOG_INFO, "Creating a repository at $path (" . ($source ? "cloned" : "initial") . ")");
		if (!is_dir($path))
		{
			mkdir_full($path);
		}

		$s_path   = escapeshellarg($path);
		$s_bare   = ($bare ? ' --bare' : '');

		// Clone an existing master repo
		if ($source !== null)
		{
			if (is_object($source))
			{
				$source = $source->gitPath();
			}

			$s_source = escapeshellarg($source);
			shell_exec($s_bin.' clone --shared --quiet'.$s_bare.' '.$s_source.' '. $s_path);
			shell_exec("cd $s_path ; $s_bin config core.sharedRepository all");
			shell_exec("cd $s_path ; $s_bin config receive.denyNonFastForwards true");
		}
		// Make a shiny new master repo
		else
		{
			shell_exec("cd $s_path ; $s_bin init --shared=all" . $s_bare);
			list($commitpath, $hash) = self::populateRepoObjects($path);
			$s_commitpath = escapeshellarg($commitpath);
			$s_hash = escapeshellarg($hash);
			shell_exec("cd $s_path ; $s_bin update-ref -m $s_commitpath refs/heads/master $s_hash");
		}
		list($commitpath, $hash) = self::populateRepoObjects($path);
		$s_commitpath = escapeshellarg($commitpath);
		$s_hash = escapeshellarg($hash);
		shell_exec("cd $s_path ; $s_bin update-ref -m $s_commitpath HEAD $s_hash");
		shell_exec("cd $s_path ; $s_bin update-ref -m $s_commitpath refs/heads/master $s_hash");
		return self::GetOrCreate($path);
	}

	/**
	 * Clones a git repository.
	 * @param from: the source repo
	 * @param to: the path to put the new repo (fails if this path exists)
	 * @returns: a GitRepository object for the new repo
	 */
	public static function cloneRepository($from, $to)
	{
		self::checkPath($to);
		$s_bin = escapeshellarg(self::gitBinaryPath());

		ide_log(LOG_INFO, "Cloning a repository at $from to $to.");

		if (file_exists($to))
		{
			throw new Exception('Path already exists!', E_INTERNAL_ERROR);
		}

		$s_from = escapeshellarg($from);
		$s_to   = escapeshellarg($to);

		shell_exec("$s_bin clone $s_from $s_to");

		return self::GetOrCreate($to);
	}

	/**
	 * Construct some of the internals of the git repo we're going to use.
	 */
	private static function populateRepoObjects($path)
	{
		$s_bin = escapeshellarg(self::gitBinaryPath());
		$s_path = escapeshellarg($path);
		$s_hash = trim(shell_exec("cd $s_path ; $s_bin hash-object -w /dev/null"));
		$s_treepath = escapeshellarg(realpath('resources/base-tree'));
		$commitpath = realpath('resources/initial-commit');
		$s_commitpath = escapeshellarg($commitpath);
		$s_hash = trim(shell_exec("cd $s_path ; cat $s_treepath | sed s/_HASH_/$s_hash/g | $s_bin mktree"));
		$hash = trim(shell_exec("cd $s_path ; cat $s_commitpath | $s_bin commit-tree $s_hash"));
		return array($commitpath, $hash);
	}

	/**
	 * Gets the most recent revision hash
	 */
	public function getCurrentRevision()
	{
		$rawRevision = $this->gitExecute(false, 'describe --always');
		return trim($rawRevision);
	}

	/**
	 * Gets the hash of the oldest revision
	 */
	public function getFirstRevision()
	{
		$revisions = explode("\n", trim($this->gitExecute(false, 'rev-list --all')));
		return $revisions[count($revisions)-1];
	}

	public function gitMKDir($path)
	{
		$dir = $this->working_path . "/" . $path;
		// cope with the folder already existing
		if (is_dir($dir) && file_exists($dir))
		{
			return true;
		}
		return mkdir_full($dir);
	}

	/**
	 * Gets the log between the arguments
	 * @param oldCommit: The commit to start at.
	 * @param newCommit: The commit to end at.
	 * @param file: The file to limit the revisions to.
	 * @returns: An array of the revisions in the given range.
	 */
	public function log($oldCommit, $newCommit, $file=null)
	{
		$log = null;
		$s_logCommand = "log -M -C --pretty='format:%H;%aN <%aE>;%at;%s'";

		if ($oldCommit !== null)
		{
			$s_oldCommit = escapeshellarg($oldCommit);
			$s_logCommand .= ' '.$s_oldCommit;

			if ($newCommit !== null)
			{
				$s_newCommit = escapeshellarg($newCommit);
				$s_logCommand .= '..'.$s_newCommit;
			}
		}

		if ($file != null)
		{
			$s_logCommand .= ' --follow -- '.escapeshellarg($file);
		}

		$log = $this->gitExecute(false, $s_logCommand);
		$server_user = trim(shell_exec('whoami'));

		$lines = explode("\n", $log);
		$results = array();
		foreach ($lines as $line)
		{
			$exp     = explode(';', $line);
			$hash    = array_shift($exp);
			$author  = array_shift($exp);
			// begins with the apache user
			if (strpos($author, $server_user) === 0)
			{
				continue;
			}
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
	 * @param branches: An array of branches to merge.
	 * @param name: The name of user to use for the merge commit.
	 * @param email: The email of user to use for the merge commit.
	 */
	public function merge($branches, $name, $email)
	{
		$mergeOptions = array('--no-stat',
		                      '--quiet');
		// environment variables are safe anyway.
		$s_committerEnv = array('GIT_AUTHOR_NAME'    => $name,
		                      'GIT_AUTHOR_EMAIL'   => $email,
		                      'GIT_COMMITER_NAME'  => $name,
		                      'GIT_COMMITER_EMAIL' => $email);
		list($success, $message) = $this->gitExecute(true, 'merge '
		                                             . implode(' ', $mergeOptions)
		                                             . ' '
		                                             . implode(' ', $branches),
		                                             $s_committerEnv,	// env
		                                             true);  	// catchResult
		if ($success)
		{
			return array();
		}
		else
		{
			$conflicted_files = array();
			$lines = explode("\n", $message);
			foreach ($lines as $line)
			{
				if (preg_match('/^CONFLICT \\(content\\): Merge conflict in (.+)$/', $line, $death))
				{
					$conflicted_files[] = $death;
				}
			}
			return $conflicted_files;
		}
	}

	/**
	 * Checkout the entire repository to a revision
	 */
	private function checkoutRepo($revision)
	{
		$s_revision = escapeshellarg($revision);
		$this->gitExecute(true, "checkout $s_revision");
	}

	public function checkoutFile($file, $revision=null)
	{
		$s_path = escapeshellarg($file);
		if ($revision == null)
		{
			$this->gitExecute(true, "checkout $s_path");
		}
		else
		{
			$s_rev = escapeshellarg($revision);
			$this->gitExecute(true, "checkout $s_rev -- $s_path");
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
	 * Stashes any current changes so you can do other things to the tree
	 * without messing up any uncommitted changes.
	 * Returns whether or not the stash was necessary.
	 */
	private function stash($id)
	{
		$s_id = escapeshellarg($id);
		$res = $this->gitExecute(true, 'stash save '.$s_id);
		$res = trim($res);
		return $res != 'No local changes to save';
	}

	/**
	 * Un-stashes a specified stash by save name
	 */
	private function stashPop($id)
	{
		$key = $this->gitExecute(true, 'stash list | grep '
			.escapeshellarg('stash@{[[:digit:]]*}: On master: '.$id.'$')
			.' | grep -o "stash@{[[:digit:]]*}"');
		$key = trim($key);
		$s_key = escapeshellarg($key);
		$this->gitExecute(true, 'stash pop '.$s_key);
	}

	/**
	 * Returns a list of files with un-staged changes.
	 * @param {indexed_only} Whether or not to constrict the list only to files that are in the index.
	 */
	public function unstagedChanges($indexed_only = FALSE)
	{
		if ($indexed_only)
		{
			return explode("\n", $this->gitExecute(true, 'diff --name-only'));
		}

		$files = array();
		$status = $this->gitExecute(true, 'status -z --porcelain');

		$all_files = explode("\0", $status);
	//	var_dump($all_files);
		foreach ($all_files as $file)
		{
			$mod = substr($file, 1, 1);
			// the file's been modified
			if ($mod !== FALSE && $mod != ' ')
			{
				$files[] = substr($file, 3);
			}
		}
		return $files;
	}

	/**
	 * Returns a list of folders in the repo's file tree.
	 */
	public function listFolders()
	{
		$s_path = escapeshellarg($this->working_path);
		$folders = trim(shell_exec("cd $s_path && find . -type d | grep -v '\.git'"));
		$folders = explode("\n", $folders);
		return $folders;
	}

	/**
	 * Commits the currently staged changes into the git tree.
	 * @returns (boolean) whether or not the commit succeeded.
	 */
	public function commit($message, $name, $email)
	{
		if ($message == '')
			$message = ' ';
		$tmp = tempnam('/tmp', 'ide-');
		file_put_contents($tmp, $message);
		$s_tmp = escapeshellarg($tmp);
		// environment variables are safe anyway.
		$s_committerEnv = array('GIT_AUTHOR_NAME'    => $name,
		                      'GIT_AUTHOR_EMAIL'   => $email,
		                      'GIT_COMMITER_NAME'  => $name,
		                      'GIT_COMMITER_EMAIL' => $email);
		list($result, $out) = $this->gitExecute(true, "commit -F $s_tmp", $s_committerEnv, true);
		unlink($tmp);
		return $result;
	}

	/**
	 * Gets the file tree for the git repository
	 */
	public function fileTreeCompat($base,$subpath = '.',$hash=null)
	{
		if ($hash != null)
		{
			$stash_id = 'Stashing before fileTreeCompat of '.$hash;
			$needs_unstash = $this->stash($stash_id);
			$this->checkoutRepo($hash);
		}

		$result = array();
		$unstagedChanges = $this->unstagedChanges();
		for ($iterator = new FilesystemIterator($this->working_path . "/$subpath");
		     $iterator->valid();
		     $iterator->next())
		{
			$raw_path = $iterator->key();
			$realpath = substr($raw_path, strlen($this->working_path . '/'));
			$realpath = str_replace('./', '', $realpath);
			$filename = basename($realpath);
			$fileinfo = $iterator->current();

			if ($filename    == '' ||
			    $filename[0] == '.')
			{
				continue;
			}

			if ($fileinfo->isFile())
			{
				$autosave = in_array($realpath, $unstagedChanges) ? $iterator->getMTime() : 0;
				$result[] = array('kind'     => 'FILE',
				                  'name'     => $filename,
				                  'path'     => "/$base/$realpath",
				                  'children' => array(),
				                  'autosave' => $autosave);
			}
			elseif ($fileinfo->isDir())
			{
				$result[] = array('kind'     => 'FOLDER',
				                  'name'     => $filename,
				                  'path'     => "/$base/$realpath",
				                  'children' => $this->fileTreeCompat($base, $realpath),
				                 );
			}
		}

		usort($result, function($a, $b) {
			$a = $a['name'];
			$b = $b['name'];
			if ($a < $b)  return -1;
			if ($a > $b)  return 1;
			if ($a == $b) return 0;
		});

		if ($hash != null)
		{
			// reset the tree back to tip
			$this->checkoutRepo('master');
			if ($needs_unstash)
			{
				$this->stashPop($stash_id);
			}
		}

		return $result;
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
		// cope with it being in a subfolder that doesn't exist yet.
		$dir = dirname($path);
		$ret = TRUE;
		if ($dir != '.')
		{
			$ret = $this->gitMKDir($dir);
		}
		return $ret && touch($this->working_path . "/$path");
	}

	/**
	 * Removes a file on the repo
	 */
	public function removeFile($path)
	{
		$s_path = escapeshellarg($path);
		$this->gitExecute(true, "rm -rf $s_path");
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
			$stash_id = 'Stashing before getFile of '.$commit;
			$needs_unstash = $this->stash($stash_id);
			$this->checkoutFile($path, $commit);

			$code = file_get_contents($this->working_path . "/$path");

			// reset the tree back to tip
			$this->checkoutFile($path, 'master');
			if ($needs_unstash)
			{
				$this->stashPop($stash_id);
			}
			return $code;
		}
	}

	/**
	 * Writes content to a file
	 */
	public function putFile($path, $content)
	{
		// ensure that the file exists before writing to it.
		$ret = $this->createFile($path);
		return $ret && file_put_contents($this->working_path . "/$path", $content);
	}

	/**
	 * Stages changes to a file.
	 * If the file exists, it is added, otherwise it is removed.
	 */
	public function stage($path)
	{
		$absPath = $this->working_path . '/' . $path;
		if (file_exists($absPath))
		{
			$s_path = escapeshellarg($path);
			$this->gitExecute(true, "add $s_path");
		}
		else
		{
			$this->removeFile($path);
		}
	}

	/**
	 * Gets a diff:
	 *  that a log entry provides,
	 *  between two commits,
	 *  between two commits for a specified file
	 */
	public function historyDiff($commitOld, $commitNew=null, $file=null)
	{
		$s_commitOld = escapeshellarg($commitOld);
		if ($commitNew === null)
		{
			return $this->gitExecute(false, "log -p -1 $s_commitOld");
		}

		$s_commitNew = escapeshellarg($commitNew);

		$s_command = 'diff -C -M '.$s_commitOld.'..'.$s_commitNew;
		if ($file !== null)
		{
			$s_command .= escapeshellarg($file);
		}

		return $this->gitExecute(false, $s_command);
	}

	/**
	 * Gets the current diff
	 * @param file: optional path or paths to get the diff for
	 * @param staged: whether or not (the default) to return the cached diff
	 */
	public function diff($file=null, $staged=false)
	{
		$s_command = 'diff';
		if($staged)
		{
			$s_command .= ' --cached';
		}

		if ($file !== null)
		{
			$s_command .= ' '.escapeshellarg($file);
		}

		return $this->gitExecute(true, $s_command);
	}

	/**
	 * does a git clone on destination then deletes the .git directory
	 */
	public function archiveSourceZip($dest, $commit = 'HEAD')
	{
		touch($dest);
		$dest = realpath($dest);
		$s_dest = escapeshellarg($dest);
		$s_commit = escapeshellarg($commit);
		$this->gitExecute(true, "archive --format=zip $s_commit -".COMPRESSION_LEVEL." > $s_dest");
	}

	/**
	 * Reverts a specific commit
	 */
	public function revert($commit)
	{
		$s_commit = escapeshellarg($commit);
		$this->gitExecute(false, "revert $s_commit");
	}

}
