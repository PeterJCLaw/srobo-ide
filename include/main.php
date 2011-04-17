<?php

if (!defined('IN_TESTS'))
	define('IN_TESTS', 0);

require_once('include/input.php');

function ide_log($message)
{
	if (IN_TESTS)
		echo "$message\n";
	else
	{
		static $file = null;
		if ($file === null)
			$file = fopen('/tmp/ide-log', 'a');
		$input = Input::getInstance();
		$rq = $input->getRequestCommand();
		if (!$rq)
			$rq = 'none';
		fwrite($file, "[RQ = $rq] $message\n");
		fflush($file);
	}
}

setlocale(LC_CTYPE, 'en_GB.UTF-8');

if (!IN_TESTS)
	set_error_handler(function ($errno, $error) {
		if ($errno <= error_reporting())
			ide_log("PHP error: $error");
	});

require_once('include/feeds.php');
require_once('include/errors.php');
require_once('include/auth/tokenstrategy/cookiestrategy.php');
require_once('include/auth/tokenstrategy/iostrategy.php');
require_once('include/auth/tokenstrategy/tokenstrategy.php');
require_once('include/file-utils.php');
require_once('include/auth/auth.php');
require_once('include/case-transform.php');
require_once('include/config.php');
require_once('include/git.php');
require_once('include/module.php');
require_once('include/output.php');
require_once('include/project-manager.php');
require_once('include/user.php');
require_once('include/notifications.php');
require_once('include/teamnames.php');
require_once('include/settings.php');
