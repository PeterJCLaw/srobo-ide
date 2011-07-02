<?php

class PyLint extends Lint
{
	private $binary = null;
	private $dummy = null;
	private $pylintHome = null;

	public function __construct()
	{
		$config = Configuration::getInstance();

		// get the pylint binary
		$this->binary = $config->getConfig('pylint.path');
		if (!$this->binary)
		{
			throw new Exception('pylint is not installed', E_NOT_IMPL);
		}

		// ensure pylint's home exists
		$this->pylintHome = $config->getConfig('pylint.dir');
		if (!is_dir($this->pylintHome))
		{
			mkdir($this->pylintHome);
		}
	}

	public function lintFile($working, $file)
	{
		$s_file = escapeshellarg($file);
		$s_pylintBinary = escapeshellarg($this->binary);
		$proc = proc_open("$s_pylintBinary --rcfile=/dev/null --errors-only --output-format=parseable --reports=n $s_file",
			array(0 => array("file", "/dev/null", "r"),
			      1 => array("pipe", "w"),
			      2 => array("pipe", "w")),
			$pipes,
			$working,
			array('PYLINTHOME' => $this->pylintHome)
		);

		// get stdout and stderr, then we're done with the process, so close it
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		$status = proc_close($proc);

		echo 'err:';
		var_dump($stderr);
		echo 'out:';
		var_dump($stdout);

		// status code zero indicates success, so return empty errors
		if ($status === 0)
		{
			return array();
		}

		// otherwise, process stderr and stdout, then forward to the user
		$lines = explode("\n", $stdout);
		$errors = array();
		foreach ($lines as $line)
		{
			if (empty($line))
			{
				continue;
			}
			// TODO this conversion should probably be in this class..
			$lint = LintMessage::FromPyLintLine($line);
			if ($lint !== null)
			{
				$errors[] = $lint;
			}
		}

		return $errors;
	}

}

