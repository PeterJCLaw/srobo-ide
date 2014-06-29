<?php

/**
 * Execute a command with the specified environment variables.
 * @param base: The path to use as the current directory for the command.
 * @param s_command: The command to run, already escaped for the command line.
 * @parm input: A file to pipe to stdin of the process.
 * @parm env: An array with the environment variables for the command that will be run.
 * @parm catchResult: Whether or not to catch the result in the event of failure.
 * @returns: If not catching failures (see catchResult) then either the process's stdout if the call succeeds or False otherwise.
 *           If catching failures then a keyed array of:
 *            * exitcode: the exit code of the process,
 *            * stdout: the stdout from the process,
 *            * stderr: the stderr from the process,
 *            * success: a boolean success indicator
 */
function proc_exec($s_command, $base = null, $input = null, $env = array(), $catchResult = false)
{
	ide_log(LOG_DEBUG, "$s_command [cwd = $base]");
	$s_input = ($input === null) ? '/dev/null' : $input;
	$proc = proc_open($s_command, array(0 => array('file', $s_input, 'r'),
	                                    1 => array('pipe', 'w'),
	                                    2 => array('pipe', 'w')),
	                              $pipes,
	                              $base,
	                              $env);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	$status = proc_close($proc);
	ide_log(LOG_DEBUG, "$s_command result: status: $status, stdout: $stdout, stderr: $stderr.");
	if ($catchResult)
	{
		// build result dictionary
		$resultDict = array('exitcode' => $status,
		                    'stdout'   => $stdout,
		                    'stderr'   => $stderr,
		                    'success'  => $status == 0,
		                   );
	}
	if ($status != 0)
	{
		ide_log(LOG_ERR, "$s_command [cwd = $base]");
		ide_log(LOG_ERR, "\tfailed miserably with exit code $status!");
		ide_log(LOG_ERR, "-- LOG --");
		ide_log(LOG_ERR, "$stderr");
		ide_log(LOG_ERR, "-- END LOG --");
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
