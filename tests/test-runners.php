<?php

interface ITestRunner
{
	function run($inFile);
}

abstract class BaseRunner implements ITestRunner
{
	protected $name = null;

	/**
	 * Runs a given command.
	 * Note: this method is a dumb (ish) wrapper around proc_open,
	 *  so you need to do your own shell escaping.
	 * @returns: An array containing:
	 *    0: the return code
	 *    1: the combined StdOut and StdErr
	 */
	protected function runCommand($s_command, $s_inFile)
	{
//		echo 'About to run: '; var_dump($s_command);
		$process = proc_open("$s_command 2>&1", array(0 => array('file', $s_inFile, 'r'),
	                                                  1 => array('pipe', 'w'),
	                                                  2 => array('file', '/dev/null', 'w')),
	                                            $pipes);
		$output = trim(stream_get_contents($pipes[1]));
		$rc = proc_close($process);
		return array($rc, $output);
	}

	public function __construct($name)
	{
		$this->name = $name;
	}

	static $runners = array('php' => 'PHPRunner', 'py' => 'PythonRunner');

	protected static function realpath($name)
	{
		return realpath('tests/'.$name);
	}

	public static function createRunner($name)
	{
		foreach (self::$runners as $ext => $runnerClass)
		{
			if (self::realpath($name.'.'.$ext))
			{
				return new $runnerClass($name);
			}
		}
		return new FailRunner($name);
	}
}

class PHPRunner extends BaseRunner
{
	public function run($inFile)
	{
		$helper = realpath('tests/test-helper.php');
		$name = parent::realpath($this->name.'.php');
		return parent::runCommand("php $helper $name", $inFile);
	}
}

class PythonRunner extends BaseRunner
{
	public function run($inFile)
	{
		$name = str_replace('/', '.', $this->name);
		return parent::runCommand("python -m unittest --buffer tests.$name", $inFile);
	}
}

class FailRunner extends BaseRunner
{
	public function run($inFile)
	{
		return array(-1, "No suitable runner found for $this->name");
	}
}
