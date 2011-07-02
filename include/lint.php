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
	 * @returns: An array of LintMessages representing the issues found.
	 */
	abstract function lintFile($working, $file);

	/**
	 * Runs code linting on a selection of files, in a given folder.
	 * @param working: The path to the folder to work in.
	 * @param files: An array of paths (possibly relative to the given path) to the files to be linted.
	 * @returns: An array of LintMessages representing the issues found.
	 */
	abstract function lintFiles($working, $files);
}
