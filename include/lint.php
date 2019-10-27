<?php

class LintMessage //extends JsonSerializable
{
	private $level;	// one of $levels
	private $message;
	private $lineNumber;
	private $locationHint;
	private $file;

	private static $levels = array(self::error, self::warning);

	const error = 'error';
	const warning = 'warning';

	public function __construct($file, $line, $message, $locationHint = null, $level = self::error)
	{
		$this->file = $file;
		$this->lineNumber = $line;
		$this->locationHint = $locationHint;
		$this->message = $message;

		if (in_array($level, self::$levels))
		{
			$this->level = $level;
		}
		else
		{
			$this->level = self::error;
		}
	}

	public function __get($name)
	{
		if (isset($this->$name))
		{
			return $this->$name;
		}
		return null;
	}

	public function __isset($name)
	{
		if (in_array($name, $this->jsonSerialize()))
		{
			return isset($this->$name);
		}
		return FALSE;
	}

	public function __set($name, $value)
	{
	}

	public function __toString()
	{
		return "$this->file:$this->lineNumber: $this->level: $this->message";
	}

	public function toJSONable()
	{
		$parts = $this->jsonSerialize();
		$out = new StdClass();
		foreach ($parts as $part)
		{
			$out->$part = @$this->$part;
		}
		return $out;
	}

	public function jsonSerialize()
	{
		return array('level', 'message', 'lineNumber', 'file');
	}
}

abstract class Lint
{
	/**
	 * Runs code linting on a given file, in a given folder.
	 * @param working: The path to the folder to work in.
	 * @param file: The path (possibly relative to the given path) to the file to be linted.
	 * @returns: An array of LintMessages representing the issues found, or False if something went wrong.
	 */
	abstract function lintFile($working, $file);

	/**
	 * Runs code linting on a selection of files, in a given folder.
	 * @param working: The path to the folder to work in.
	 * @param files: An array of paths (possibly relative to the given path) to the files to be linted.
	 * @returns: An array of LintMessages representing the issues found, or False if something went wrong.
	 */
	abstract function lintFiles($working, $files);

	/**
	 * Returns the directory which contains the configured lint reference.
	 */
	public static function getReferenceDirectory()
	{
		$config = Configuration::getInstance();
		$referenceDir = $config->getConfig('pylint.reference_dir');

		if (!file_exists($referenceDir))
		{
			throw new Exception('Could not find dummy pyenv', E_NOT_IMPL);
		}

		return realpath($referenceDir);
	}
}

class LintHelper
{
	private $projectName;
	private $sourceRepoPath;

	public function __construct($sourceRepoPath, $projectName)
	{
		$this->projectName = $projectName;
		$this->sourceRepoPath = $sourceRepoPath;
	}

	public function lintFile($path, $revision=null, $newCode=null)
	{
		// While we could in theory use the persistent per-user working
		// directory for this, the linting can take a while so it's better
		// to have an isolated copy which avoids the need to lock the
		// per-user clone for a long time.
		$tmpDir = tmpdir();

		try
		{
			$working = $this->makeWorkingDir($tmpDir, $path, $revision, $newCode);
			$errors = $this->doLint($working, $path);
			return $errors;
		}
		finally
		{
			delete_recursive($tmpDir);
		}
	}

	private function makeWorkingDir($tmpDir, $path, $revision, $newCode)
	{
		$working = $tmpDir . '/' . $this->projectName;

		$repo = GitRepository::cloneRepository($this->sourceRepoPath, $working);

		// TODO: there might be performance advantage in checking this
		// against the master repo before the above clone, but it seems
		// an unlikely error to actually occur.
		if (!file_exists("$working/$path"))
		{
			throw new Exception('file does not exist', E_MALFORMED_REQUEST);
		}

		// fixed revision
		if ($revision !== null)
		{
			$repo->checkoutRepo($revision);
		}

		if ($newCode !== null)
		{
			$repo->putFile($path, $newCode);
		}

		return $working;
	}

	private function doLint($working, $path)
	{
		$errors = array();

		$pylint = new PyLint();
		$importlint = new ImportLint();

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
				// Code linting failed, so fail overall.
				return False;
			}
		}

		return array_unique($errors);
	}
}
