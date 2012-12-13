<?php

class ImportLint extends Lint
{
	private $binary = null;
	private $script = 'lint-imports/getimportsinfo.py';
	private $touched = array();

	public function __construct()
	{
		$config = Configuration::getInstance();

		// get the python binary
		$this->binary = $config->getConfig('python.path');
		if (!$this->binary)
		{
			throw new Exception('python is not installed', E_NOT_IMPL);
		}

		$this->script = realpath($this->script);
		if (!file_exists($this->script))
		{
			throw new Exception('Import checking helper missing', E_NOT_IMPL);
		}
	}

	public function getTouchedFiles()
	{
		return array_unique($this->touched);
	}

	public function lintFiles($working, $files)
	{
		$err = array();
		$touched = array();
		foreach ($files as $file)
		{
			$ret = $this->lintFile($working, $file);
			if ($ret !== False)
			{
				$err = array_merge($err, $ret);
				$touched = array_merge($touched, $this->touched);
			}
		}
		$this->touched = array_unique($touched);
		return $err;
	}

	public function lintFile($working, $file)
	{
		$this->touched = array();
		$s_file = escapeshellarg($file);
		$s_pythonBinary = escapeshellarg($this->binary);
		$s_script = escapeshellarg($this->script);
		$proc = proc_open("$s_pythonBinary $s_script $s_file"
			,array(0 => array("file", "/dev/null", "r")
			      ,1 => array("pipe", "w")
			      ,2 => array("pipe", "w")
			      )
			,$pipes
			,$working
		);

		// get stdout and stderr, then we're done with the process, so close it
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		$status = proc_close($proc);

		// status code non-zero says something went very wrong
		// probably threw an exception - TODO: log this!
		if ($status !== 0)
		{
			return False;
		}

		// otherwise, process stderr and stdout
		$info = json_decode($stdout, true);
		$errors = array();
		foreach ($info as $file => $imports)
		{
			if ($file == '.error')
			{
				$errors[] = new LintMessage($imports['file'], $imports['line'], $imports['msg']);
				continue;
			}
			$this->touched[] = $file;
			foreach ($imports as $import => $line)
			{
				$errors[] = new LintMessage($file, $line, "Could not import '$import'");
			}
		}

		return $errors;
	}
}
