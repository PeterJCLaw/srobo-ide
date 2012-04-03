<?php

class FileModule extends Module
{
	private $team;
	private $projectName;

	public function __construct()
	{
		$auth = AuthBackend::getInstance();

		// bail if we aren't authenticated
		if ($auth->getCurrentUser() == null)
		{
			// module does nothing if no authentication
			return;
		}

		try
		{
			$this->projectManager = ProjectManager::getInstance();
		}
		catch (Exception $e)
		{
			if ($e->getCode() == E_INTERNAL_ERROR)
			{
				// repo dir not set up
				// this may be valid for auth tests, so just don't init
				return;
			}
			else
			{
				// other error, rethrow
				throw $e;
			}
		}

		$input = Input::getInstance();
		$this->team = $input->getInput('team', true);

		// check that the project exists and is a git repo otherwise construct
		// the project directory and git init it
		$project = $input->getInput('project', true);

		$this->projectName = $project;

		$this->installCommand('compat-tree', array($this, 'getFileTreeCompat'));
		$this->installCommand('list', array($this, 'listFiles'));
		$this->installCommand('get', array($this, 'getFile'));
		$this->installCommand('put', array($this, 'putFile'));
		$this->installCommand('new', array($this, 'newFile'));
		$this->installCommand('del', array($this, 'deleteFile'));
		$this->installCommand('cp', array($this, 'copyFile'));
		$this->installCommand('mv', array($this, 'moveFile'));
		$this->installCommand('log', array($this, 'fileLog'));
		$this->installCommand('lint', array($this, 'lintFile'));
		$this->installCommand('diff', array($this, 'diff'));
		$this->installCommand('mkdir', array($this, 'makeDirectory'));
		$this->installCommand('co', array($this, 'checkoutFile'));
	}

	/**
	 * Ensures that the user is in the team they claim to be
	 */
	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserTeams()))
		{
			throw new Exception('proj attempted on team you aren\'t in', E_PERM_DENIED);
		}
	}

	/**
	 * Gets a handle on the repository for the current project
	 */
	private function repository()
	{
		$pm = ProjectManager::getInstance();
		$this->verifyTeam();
		$user = AuthBackend::getInstance()->getCurrentUser();
		$pm->updateRepository($this->team, $this->projectName, $user);
		$repo = $pm->getUserRepository($this->team, $this->projectName, $user);
		return $repo;
	}

	/**
	 * Makes a directory in the repository
	 */
    public function makeDirectory()
    {
        $input = Input::getInstance();
        $output = Output::getInstance();
        $path = $input->getInput("path");
        $success = $this->repository()->gitMKDir($path);
        $output->setOutput("success", $success ? 1 : 0);
        $text = $success ? 'Successfully created' : 'Failed to create';
        $output->setOutput("feedback", "$text folder '$path'");
        return $success;
    }

	/**
	 * Gets a recursive file tree, optionally at a specific revision
	 */
	public function getFileTreeCompat()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();

		$revision = $input->getInput('rev', true);
		// a specific revision is requested
		if($revision != null && $revision != 'HEAD')
		{
			var_dump($this->projectName, $hash);
			$uncleanOut = $this->repository()->fileTreeCompat($this->projectName, '.', $revision);
		}
		else
		{
			$uncleanOut = $this->repository()->fileTreeCompat($this->projectName);
		}
		$results = $this->sanitiseFileList($uncleanOut);
		$output->setOutput('tree', $results);
		return true;
	}

	/**
	 * Removes unwanted files from the given array.
	 * Previously, this was used to hide __init__.py, but this file is now shown.
	 */
	private function sanitiseFileList($unclean)
	{
		return array_values($unclean);
	}

	/**
	 * Check out a particular revision of a file.
	 * Also used to revert a file to its unmodified state.
	 */
	public function checkoutFile()
	{
		$input = Input::getInstance();
		$output = Output::getInstance();
		$paths = $input->getInput("files");
		$revision = $input->getInput("revision");
		//latest
		$output->setOutput("rev", $revision);
		if ($revision === 0 || $revision === "HEAD")
		{
			foreach ($paths as $file)
			{
				$this->repository()->checkoutFile($file);
			}

		}
		else
		{
			$output->setOutput("revision reverting","");
			foreach ($paths as $file)
			{
				$this->repository()->checkoutFile($file,$revision);
			}
		}

		$output->setOutput("success",true);
		return true;
	}

	/**
	 * Get a flat list of files in a specific folder
	 */
	public function listFiles()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$uncleanOut = $this->repository()->listFiles($path);
		$results = $this->sanitiseFileList($uncleanOut);
		$output->setOutput('files', $results);
		return true;
	}

	/**
	 * Get the contents of a given file in the repository
	 */
	public function getFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$revision = $input->getInput('rev');

		// The data the repo has stored
		$original = $this->repository()->getFile($path, $revision);

		// only bother specifying the autosave data if HEAD
		$autosaved = null;
		if($revision == 'HEAD' && in_array($path, $this->repository()->unstagedChanges()))
		{
			$autosaved = $this->repository()->getFile($path);
		}

		$output->setOutput('autosaved', $autosaved);
		$output->setOutput('original', $original);
		return true;
	}

	/**
	 * Save a file, without committing it
	 */
	public function putFile()
	{
		$input  = Input::getInstance();
		$path   = $input->getInput('path');
		$data   = $input->getInput('data');
		return $this->repository()->putFile($path, $data);
	}

	/**
	 * Make a new file in the repository
	 */
	public function newFile()
	{
		$input  = Input::getInstance();
		$path   = $input->getInput('path');
		return $this->repository()->createFile($path);
	}

	/**
	 * Delete a given file in the repository
	 */
	public function deleteFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$files = $input->getInput("files");

		foreach ($files as $file)
		{
			$this->repository()->removeFile($file);
		}
		return true;
	}

	/**
	 * Copy a given file in the repository
	 */
	public function copyFile()
	{
		$input   = Input::getInstance();
		$output  = Output::getInstance();
		$oldPath = $input->getInput('old-path');
		$newPath = $input->getInput('new-path');
		$this->repository()->copyFile($oldPath, $newPath);
		$output->setOutput('status', 0);
		$output->setOutput('message', $oldPath.' to '.$newPath);
		return true;
	}

	/**
	 * Move a given file in the repository
	 */
	public function moveFile()
	{
		$input   = Input::getInstance();
		$output  = Output::getInstance();
		$oldPath = $input->getInput('old-path');
		$newPath = $input->getInput('new-path');
		$this->repository()->moveFile($oldPath, $newPath);
		$output->setOutput('status', 0);
		$output->setOutput('message', $oldPath.' to '.$newPath);
		return true;
	}

	/**
	 * Get the log for a file.
	 * It expects a file to restrict the log to, and, optionally, an offset to start from and the number of entries wanted.
	 * It returns the requested entries and a list of authors that have committed to the file.
	 */
	public function fileLog()
	{
		$output = Output::getInstance();
		$input = Input::getInstance();
		$path = $input->getInput('path');

		$repo = $this->repository();

		$number = $input->getInput('number', true);
		$offset = $input->getInput('offset', true);

		$number = ($number != null ? $number : 10);
		$offset = ($offset != null ? $offset * $number : 0);

		$log = $repo->log(null, null, $path);

		// if user has been passed we need to filter by author
		$user = $input->getInput("user", true);
		print $user;

		//take a backup of the log so we can list all the authors
		$originalLog = $log;

		//check if we've got a user and filter
		if ($user != null)
		{
			$filteredRevs = array();
			foreach ($log as $rev)
			{
				if ($rev["author"] == $user) $filteredRevs[] = $rev;
			}

			$log = $filteredRevs;
		}

		$output->setOutput('log', array_slice($log, $offset, $number));
		$output->setOutput('pages', ceil(count($log) / $number));

		$authors = array();
		foreach($originalLog as $rev)
		{
			$authors[] = $rev['author'];
		}

		$output->setOutput('authors', array_values(array_unique($authors)));

		return true;
	}

	/**
	 * Gets the diff of:
	 *  A log change
	 *  The current state of a file against the tree
	 */
	public function diff()
	{
		$output = Output::getInstance();
		$input = Input::getInstance();

		$hash = $input->getInput('hash');
		$path = $input->getInput('path');
		$newCode = $input->getInput('code', true);

		// patch from log
		if ($newCode === null)
		{
			$diff = $this->repository()->historyDiff($hash);
		}
		// diff against changed file
		else
		{
			$this->repository()->putFile($path, $newCode);
			$diff = $this->repository()->diff($path);
		}

		$output->setOutput("diff", $diff);
		return true;
	}

	/**
	 * Checks a given file for errors
	 */
	public function lintFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$config = Configuration::getInstance();
		$path   = $input->getInput('path');

		//base dir might need changing with alistair's new git situation
		$base = $this->repository()->workingPath();

		//this occurs because someone decided it would be a good idea to split
		//these up here instead of javascript, makes this function hideous
		$splitPath = pathinfo($path);
		$dirName = $splitPath["dirname"];
		$fileName = $splitPath["filename"] . "." . $splitPath["extension"];

		// check for the reference file
		$dummy = $config->getConfig('pylint.referenceFile');
		if (!file_exists($dummy))
		{
			throw new Exception('Could not find dummy pyenv', E_NOT_IMPL);
		}

		//if the file exists, lint it otherwise return a dictionary explaining
		//that the file doesn't exist, shouldn't happen when users interface
		//with software because check syntax button always points at an existing file
		if (file_exists("$base/$path"))
		{
			$pylint = new PyLint();
			$importlint = new ImportLint();

			// copy the reference file in
			$useAutosave = $input->getInput('autosave', true);
			$contents = null;

			// if we want the commited version do this
			if (!$useAutosave) {
				$contents = $this->repository()->getFile($path);
				$this->repository()->checkoutFile($path);
			}

			// Grab a temp folder that we can work in. We'll remove it later.
			$tmpDir = tmpdir();
			echo "base, path, tmp\n";
			var_dump($base, $path, $tmpDir);

			// Copy the user's files to the temp folder
			copy_recursive($base, $tmpDir);

			// Copy the reference file to the tenp folder
			$dummy_copy = $tmpDir.'/'.basename($base).'/'.basename($dummy);
			echo "dummy copy\n";
			var_dump($dummy_copy);
			copy($dummy, $dummy_copy);

			$working = $tmpDir.'/'.basename($base);
			$errors = array();

			$importErrors = $importlint->lintFile($working, $path);
			if ($importErrors === False)
			{
				$pyErrors = $pylint->lintFile($working, $path);
				if ($pyErrors !== False)
				{
					$errors = $pyErrors;
				}
				else
				{
					// remove the temporary folder
					delete_recursive($tmpDir);

					// Both sets of linting failed, so fail overall.
					return False;
				}
			}
			else
			{
				$errors = $importErrors;
				$more_files = $importlint->getTouchedFiles();

				$pyErrors = $pylint->lintFiles($working, $more_files);
				if ($pyErrors !== False)
				{
					$errors = array_merge($errors, $pyErrors);
				}
				else
				{
					// remove the temporary folder
					delete_recursive($tmpDir);

					// Both sets of linting failed, so fail overall.
					return False;
				}
			}

			// remove the temporary folder
			delete_recursive($tmpDir);

			// restore the autosaved version
			if (!$useAutosave) {
				$this->repository()->putFile($path, $contents);
			}

			// Sort & convert to jsonables if needed.
			// This (latter) step necessary currently since JSONSerializeable doesn't exist yet.
			if (count($errors) > 0)
			{
				usort($errors, function($a, $b) {
						if ($a->lineNumber == $b->lineNumber) return 0;
						return $a->lineNumber > $b->lineNumber ? 1 : -1;
					});
				$errors = array_map(function($lm) { return $lm->toJSONable(); }, $errors);
			}

			$output->setOutput("errors", $errors);
			return true;
		}
		else
		{
			$output->setOutput('error', 'file does not exist');
			return false;
		}
	}
}
