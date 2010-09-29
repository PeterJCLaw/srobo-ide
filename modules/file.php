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
        $this->installCommand("co", array($this, "checkoutFile"));
	}

	private function verifyTeam()
	{
		$auth = AuthBackend::getInstance();
		if (!in_array($this->team, $auth->getCurrentUserTeams()))
		{
			throw new Exception('proj attempted on team you aren\'t in', E_PERM_DENIED);
		}
	}

	private function repository()
	{
		$pm = ProjectManager::getInstance();
		$this->verifyTeam();
		$repo = $pm->getUserRepository($this->team, $this->projectName, AuthBackend::getInstance()->getCurrentUser());
		return $repo;
	}

    public function makeDirectory() {
        $input = Input::getInstance();
        $output = Output::getInstance();
        $path = $input->getInput("path");
        $this->repository()->gitMKDir($path);
        $output->setOutput("success",1);
        $output->setOutput("feedback", "successfully created folder $path");
        return true;
    }

	public function getFileTreeCompat()
	{
		$output = Output::getInstance();
        $uncleanOut = $this->repository()->fileTreeCompat($this->projectName);
        $cleanOut = array_filter($uncleanOut, function($var) {return $var["name"] != "__init__.py";});
        $results = array();
        foreach ($cleanOut as $item) {
            $results[] = $item;
        }
		$output->setOutput('tree', $results);
        $output->setOutput("ponies", $uncleanOut);
		$output->setOutput('latest-hash', $this->repository()->getCurrentRevision());
		return true;
	}

    public function checkoutFile() {
        $input = Input::getInstance();
        $output = Output::getInstance();
        $paths = $input->getInput("files");
        $revision = $input->getInput("revision");
        //latest
        $output->setOutput("rev", $revision);
        if ($revision === 0 || $revision === "HEAD") {
            foreach ($paths as $file) {
                $this->repository()->checkoutFile($file);
            }

        } else {
            $output->setOutput("revision reverting","");
            foreach ($paths as $file) {
                $output->setOutput("ponies","ponies");
                $this->repository()->checkoutFile($file,$revision);
            }
        }

        $output->setOutput("success",true);
        return true;
    }


	public function listFiles()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
        $uncleanOut = $this->repository()->listFiles($path);
        $cleanOut = array_filter($uncleanOut, function($var) {return $var["name"] != "__init__.py";});
        $results = array();
        foreach ($cleanOut as $item) {
            $results[] = $item;
        }
		$output->setOutput('files', $results);
		return true;
	}

	public function getFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$output->setOutput('data', $this->repository()->getFile($path));
		return true;
	}

	public function putFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$data   = $input->getInput('data');
		$this->repository()->putFile($path, $data);
		return true;
	}

	public function newFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$this->repository()->createFile($path);
		return true;
	}

	public function deleteFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
        $files = $input->getInput("files");

        foreach ($files as $file) {
            $output->setOutput("loop","loop");
    		$this->repository()->removeFile($file);
        }
		return true;
	}

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

	/*
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

		$currRev = $repo->getCurrentRevision();
		$firstRev = $repo->getFirstRevision();

		$number = $input->getInput('number', true);
		$offset = $input->getInput('offset', true);

		$number = ($number != null ? $number : 10);
		$offset = ($offset != null ? $offset : 0);

		$log = $repo->log($firstRev, $currRev);

		var_dump($log);

		$output->setOutput('log', array_slice($log, $offset, $number));

		$authors = array();
		foreach($log as $rev) {
			$authors[] = $rev['author'];
		}

		$output->setOutput('authors', array_unique($authors));

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
		if ($newCode == null)
		{
			$diff = $this->repository()->historyDiff($hash);
		}
		// diff against changed file
		else
		{
			$this->repository()->putFile($path, $newCode);
			$diff = $this->repository()->cachedDiff($path);
		}

		$output->setOutput("diff", $diff);
	}

	public function lintFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$config = Configuration::getInstance();
		$auth = AuthBackend :: getInstance();
		$path   = $input->getInput('path');

		//base dir might need changing with alistair's new git situation
		$base = $this->repository()->getPath();

		//this occurs because someone decided it would be a good idea to split
		//these up here instead of javascript, makes this function hideous
		$splitPath = pathinfo($path);
		$dirName = $splitPath["dirname"];
		$fileName = $splitPath["filename"] . "." . $splitPath["extension"];

		//get the pylint binary
		$binary = $config->getConfig('pylint_path');
		if (!$binary)
		{
			$output->setOutput("errors", array());
			$output->setOutput("messages", array('pylint is not installed'));
			$output->setOutput('path', $dirName);
			$output->setOutput('file', $fileName);
			return true;
		}

		//if the file exists, lint it otherwise return a dictionary explaining
		//that the file doesn't exist, shouldn't happen when users interface
		//with software because check syntax button always points at an existing file
		if (file_exists("$base/$path"))
		{
			//setup linting process
			$proc = proc_open("$binary -e -f parseable --reports=n $path",
				array(0 => array("file", "/dev/null", "r"),
				      1 => array("pipe", "w"),
				      2 => array("pipe", "w")),
				$pipes,
				$base
			);

			//get stdout and stderr, then we're done with the process, so close it
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			$status = proc_close($proc);

			//status code zero indicates success, so return empty errors
			if ($status == 0)
			{
				$output->setOutput("errors", array());
				$output->setOutput("messages", array());
				$output->setOutput("path", $dirName);
				$output->setOutput("file", $fileName);
				//$output->setOutput("errors", 0);
				return true;

			//otherwise, process stderr and stdout, then forward to the user
			}
			else
			{
				$lines = explode("\n", $stderr);
				$errors = array();
				foreach ($lines as $line)
				{
					if (array_search($line, array("","\n","Warnings...")) === False)
					{
						$errors[] = $line;
					}
				}

				$lines = explode("\n", $stdout);
				$warnings = array();
				foreach ($lines as $line)
				{
					if (array_search($line, array("","\n","Warnings...")) === False)
					{
						$warnings[] = $line;
					}
				}

				$output->setOutput("errors", $errors);
				$output->setOutput("messages", $warnings);
				$output->setOutput("path", $dirName);
				$output->setOutput("file", $fileName);
				//$output->setOutput("errors", 1);
				return true;
			}
		}
		else
		{
			$output->setOutput('errors', array("file does not exist"));
			$output->setOutput("warnings", array());
			$output->setOutput("path", $dirName);
			$output->setOutput("file", $fileName);
			//$output->setOutput("errors", 1);
		}
		return true;
	}
}
