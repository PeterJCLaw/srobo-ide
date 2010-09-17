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
        $this->installCommand("diff", array($this, 'diff'));
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
		$repo = $pm->getRepository($this->team, $this->projectName);
		return $repo;
	}

	public function getFileTreeCompat()
	{
		$output = Output::getInstance();
		$output->setOutput('tree', $this->repository()->fileTreeCompat($this->projectName));
		return true;
	}

	public function listFiles()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$output->setOutput('files', $this->repository()->listFiles($path));
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
		$path   = $input->getInput('path');
		$this->repository()->removeFile($path);
		return true;
	}

	public function copyFile()
	{
		$input   = Input::getInstance();
		$output  = Output::getInstance();
		$oldPath = $input->getInput('old-path');
		$newPath = $input->getInput('new-path');
		$this->repository()->copyFile($oldPath, $newPath);
		return true;
	}

	public function moveFile()
	{
		$input   = Input::getInstance();
		$output  = Output::getInstance();
		$oldPath = $input->getInput('old-path');
		$newPath = $input->getInput('new-path');
		$this->repository()->moveFile($oldPath, $newPath);
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

    public function diff()
    {
        $output = Output::getInstance();
        $input = Input::getInstance();
        $path = $input->getInput("path");
        $r_start = $input->getInput("start");
        $r_end = $input->getInput("end");
        $diff = $this->repository()->diff($r_start,$r_end,$path);
        $output->setOutput("diff", $diff);
    }

	public function lintFile()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$path   = $input->getInput('path');
		$output->setOutput('errors', array());
		$output->setOutput('warnings', array('lint not implemented'));
		return true;
	}
}
