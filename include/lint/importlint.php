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

	public function lintFile($s_working, $file)
	{
		$this->touched = array();
		$s_file = escapeshellarg($file);
		$s_pythonBinary = escapeshellarg($this->binary);
		$s_script = escapeshellarg($this->script);
		$s_cmd = "$s_pythonBinary $s_script $s_file";
		$output = proc_exec($s_cmd, $s_working, null, array(), true);

		// status code non-zero says something went very wrong
		// probably threw an exception (will have been logged by proc_exec)
		if ($output['exitcode'] !== 0)
		{
			return False;
		}

		// otherwise, process stderr and stdout
		$info = json_decode($output['stdout'], true);
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
