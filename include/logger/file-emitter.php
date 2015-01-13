<?php

/**
 * An emitter for the logger class which outputs to a file.
 */
class FileEmitter
{
	private $file;
	private $location;

	/**
	 * Create a new instance.
	 * @param location: The path to a writable file to output the log
	 *                  lines into. Each emit() call will be separated
	 *                  by a 'PHP_EOL' character.
	 */
	public function __construct($location)
	{
		$this->location = $location;
	}

	public function emit($level, $message)
	{
		if ($this->file === null)
		{
			$this->file = fopen($this->location, 'a');
		}
		fwrite($this->file, $message . PHP_EOL);
		fflush($this->file);
	}
}
