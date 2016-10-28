<?php

class PyLint extends Lint
{
	private $binary = null;
	private $pylintHome = null;
	private $maxDuration = null;

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

		$this->maxDuration = $config->getConfig('pylint.max_duration');
	}

	public function lintFiles($working, $files)
	{
		// Ideally we'd have pylint do the files all in one go, which
		// would probably be faster, but it is known to fall over
		// (http://www.logilab.org/70381) in the case where one of the
		// imports has a syntax error.
		// In such cases we don't get any info at all, which is rubbish.
		// We should probably do something to handle such cases,
		// at the moment we just return an empty array.
		// This loop is a partial workaround for this issue.
		$err = array();
		$valid = False;
		foreach ($files as $file)
		{
			$ret = $this->lintFile($working, $file);
			if ($ret !== False)
			{
				// mark that we've actually got results
				$valid = true;
				$err = array_merge($err, $ret);
			}
		}
		return $valid ? $err : False;
	}

	public function lintFile($s_working, $file)
	{
		$s_file = escapeshellarg($file);
		$s_pylintBinary = escapeshellarg($this->binary);

		// Template chosen to match the output pylint used to provide before deprecating
		// '--output-format=parseable', and to match the below regex which parses it.
		$s_msg_template = '--msg-template="{path}:{line}: [{msg_id}({symbol}), {obj}] {msg}"';
		$s_cmd = "$s_pylintBinary --rcfile=/dev/null --errors-only $s_msg_template --reports=n $s_file";
		$s_env = array('PYLINTHOME' => $this->pylintHome);
		$s_timeout = $this->maxDuration;
		$output = proc_exec($s_cmd, $s_working, null, $s_env, true, $s_timeout);

		$status = $output['exitcode'];
		// status code zero indicates success, so return empty errors
		if ($status === 0)
		{
			return array();
		}

		if ($output['timedout'])
		{
			return False;
		}

		// status code one indicates something went wrong with the linting
		if ($status === 1)
		{
			$stderr = $output['stderr'];
			if (strpos($stderr, 'IndentationError') === False)
			{
				return False;
			}
			// TODO: detect other types of interior fail?
			$inner = 'expected an indented block';
			$lines = explode("\n", $stderr);
			$lineParts = explode(' ', $lines[count($lines)-5]);
			$line = $lineParts[count($lineParts)-1];
			$err = new LintMessage($file, 0, "One of the imports to $file had an error '$inner' on line $line.");
			return array($err);
		}

		// otherwise, process stdout
		$lines = explode("\n", $output['stdout']);
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
		$pattern = '/([^:]+):(\d+): \[(E|W) ?\d*(\((?P<type>[^\)]+)\))?(, (?P<hint>[^\]]+)?)?\] (?P<msg>.*)/';
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
