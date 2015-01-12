<?php

/**
 * An emitter for the logger class which outputs to a file.
 */
class FileEmitter
{
	private $file;
	private $location;

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
