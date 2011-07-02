<?php

class PyLint extends Lint
{
	private $binary = null;
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
		return $this->lintFiles($working, array($file));
	}

	public function lintFiles($working, $files)
	{
		$s_filesArr = array_map('escapeshellarg', $files);
		$s_files = implode(' ', $s_filesArr);
		$s_pylintBinary = escapeshellarg($this->binary);
		$proc = proc_open("$s_pylintBinary --rcfile=/dev/null --errors-only --output-format=parseable --reports=n $s_files",
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

		// status code zero indicates success, so return empty errors
		if ($status === 0)
		{
			return array();
		}

		// otherwise, process stderr and stdout
		$lines = explode("\n", $stdout);
		$errors = array();
		foreach ($lines as $line)
		{
			if (empty($line))
			{
				continue;
			}
			$lint = self::ConvertToMessage($line);
			if ($lint !== null)
			{
				$errors[] = $lint;
			}
		}

		return $errors;
	}

	/**
	 * Attempts to parse the given line using the format of pylint messages.
	 * If successful, it returns a new LintMessage representing the information.
	 * @param line: The line to try to parse.
	 * @returns: A LintMessage representing the error, or null if the parsing failed.
	 */
	public static function ConvertToMessage($line)
	{
		$pattern = '/([^:]+):(\d+): \[(E|W) ?\d*(, (?P<hint>[^\]]+))?\] (?P<msg>.*)/';
		$matches = array();
		if (preg_match($pattern, $line, $matches))
		{
			$level = ($matches[3] == 'W') ? LintMessage::warning : LintMessage::error;
			$hint = isset($matches['hint']) ? $matches['hint'] : null;
			$lint = new LintMessage($matches[1], $matches[2], $matches['msg'], $hint, $level);
			return $lint;
		}
		return null;
	}
}
