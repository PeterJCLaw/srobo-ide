<?php

class ProcUtilsMonitor
{
	/**
	 * List process handles and information about them.
	 */
	private $processes = array();

	public static function getInstance()
	{
		static $instance;
		if ($instance === null)
		{
			$c = __CLASS__;
			$instance = new $c();
		}
		return $instance;
	}

	private function __construct()
	{
	}

	function __destruct()
	{
		$this->kill();
	}

	public static function closePipes($pipes)
	{
		foreach ($pipes as $p)
		{
			fclose($p);
		}
	}

	public function kill()
	{
		foreach ($this->processes as $info)
		{
			list($proc, $cmd, $base, $pipes) = $info;
			$desc = "$cmd [cwd = $base]";

			// Close all the pipes (PHP docs say we need to do this
			// before calling proc_close).
			self::closePipes($pipes);

			$status = proc_get_status($proc);
			$running = $status['running'];
			if ($running)
			{
				ide_log(LOG_WARNING, "Process still running at end of script! $desc");

				// still running, but we're being closed -- force kill it
				proc_terminate($proc, SIGKILL);

				// Wait for the process to end properly
				$ret = proc_close($proc);
				if ($ret < 0)
				{
					ide_log(LOG_ERR, "Failed to close process: $desc");
				}
				elseif ($ret > 0)
				{
					ide_log(LOG_INFO, "Process exited with $ret: $desc");
				}
				else
				{
					ide_log(LOG_DEBUG, "Successfully closed process: $desc");
				}
			}
			else
			{
				// It's already closed, nothing to do. If we call
				// proc_close here we get back an error (-1), so just log.
				ide_log(LOG_DEBUG, "Process already closed: $desc");
			}
		}
		$this->processes = array();
	}

	public function register($process, $command, $base, $pipes)
	{
		$this->processes[] = array($process, $command, $base, $pipes);
	}
}


/**
 * Execute a command with the specified environment variables.
 * @param base: The path to use as the current directory for the command.
 * @param s_command: The command to run, already escaped for the command line.
 * @parm input: A file to pipe to stdin of the process.
 * @parm env: An array with the environment variables for the command that will be run.
 * @parm catchResult: Whether or not to catch the result in the event of failure.
 * @parm timeoutSeconds: The maximum duration to allow the process to run. null (the default) means no limit.
 * @returns: If not catching failures (see catchResult) then either the process's stdout if the call succeeds or False otherwise.
 *           If catching failures then a keyed array of:
 *            * exitcode: the exit code of the process (value undefined in case of timeout),
 *            * stdout: the stdout from the process,
 *            * stderr: the stderr from the process,
 *            * success: a boolean success indicator,
 *            * timedout: a boolean indicator of whether the process reached its time limit
 */
function proc_exec($s_command, $base = null, $input = null, $env = array(), $catchResult = false, $timeoutSeconds = null)
{
	ide_log(LOG_DEBUG, "$s_command [cwd = $base]");
	$s_input = ($input === null) ? '/dev/null' : $input;
	$proc = proc_open($s_command, array(0 => array('file', $s_input, 'r'),
	                                    1 => array('pipe', 'w'),
	                                    2 => array('pipe', 'w')),
	                              $pipes,
	                              $base,
	                              $env);

	$timedOut = false;
	if ($timeoutSeconds === null)
	{
		// No limit
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		ProcUtilsMonitor::closePipes($pipes);
		$status = proc_close($proc);
	}
	else
	{
		ProcUtilsMonitor::getInstance()->register($proc, $s_command, $base, $pipes);

		$end = microtime(true) + $timeoutSeconds;
		stream_set_blocking($pipes[1], 0);
		stream_set_blocking($pipes[2], 0);
		$e = array();
		$stdout = '';
		$stderr = '';
		$proc_status = array();
		while (($left = $end - microtime(true)) > 0)
		{
			$proc_status = proc_get_status($proc);
			if (!$proc_status['running'])
			{
				break;
			}
			$streams = array_values($pipes);
			stream_select($streams, $e, $e, $left);
			$stdout .= fread($pipes[1], 4096);
			$stderr .= fread($pipes[2], 4096);
		}
		// if still running, then timed out
		if ($proc_status['running'])
		{
			var_dump('terminating');
			proc_terminate($proc);
			$status = null;
			$timedOut = true;
		}
		else
		{
			$status = $proc_status['exitcode'];
		}
	}
	ide_log(LOG_DEBUG, "$s_command result: status: $status, stdout: $stdout, stderr: $stderr.");
	$success = !$timedOut && $status == 0;
	if ($catchResult)
	{
		// build result dictionary
		$resultDict = array('exitcode' => $status,
		                    'stdout'   => $stdout,
		                    'stderr'   => $stderr,
		                    'success'  => $success,
		                    'timedout' => $timedOut,
		                   );
	}
	if (!$success)
	{
		ide_log(LOG_ERR, "$s_command [cwd = $base]");
		if ($timedOut)
		{
			ide_log(LOG_ERR, "\ttimed out after $timeoutSeconds seconds!");
			// also emit the stdout so we can see where the process got to
			ide_log(LOG_ERR, "-- STDOUT --");
			ide_log(LOG_ERR, "$stdout");
			ide_log(LOG_ERR, "-- END STDOUT --");
		}
		else
		{
			ide_log(LOG_ERR, "\tfailed miserably with exit code $status!");
		}
		ide_log(LOG_ERR, "-- STDERR --");
		ide_log(LOG_ERR, "$stderr");
		ide_log(LOG_ERR, "-- END STDERR --");
		if ($catchResult)
			return $resultDict;
		else
			return false;
	}
	else
	{
		if ($catchResult)
			return $resultDict;
		else
			return $stdout;
	}
}
