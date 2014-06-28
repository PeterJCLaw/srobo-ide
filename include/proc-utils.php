<?php

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
		$status = proc_close($proc);
	}
	else
	{
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
