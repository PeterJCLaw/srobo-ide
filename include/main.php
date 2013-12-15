<?php

if (!defined('IN_TESTS'))
	define('IN_TESTS', 0);

require_once('include/config.php');
require_once('include/input.php');
require_once('include/logger.php');

function ide_log_exception($e, $message = null)
{
	ide_log(LOG_ERR, "$message Exception: ".$e->getCode().': '.$e->getMessage().PHP_EOL.$e->getTraceAsString());
}

function ide_log($level, $message)
{
	if (IN_TESTS)
		echo "$level|$message\n";
	else
		Logger::log($level, $message);
}

function parts_for_output($exception)
{
	return array($exception->getCode(), $exception->getMessage(), $exception->getTraceAsString());
}

setlocale(LC_CTYPE, 'en_GB.UTF-8');

if (!IN_TESTS)
	set_error_handler(function ($errno, $error, $errfile, $errline) {
		if ($errno <= error_reporting())
			ide_log(LOG_ERR, "PHP error: $error (line $errline in $errfile)");
	});

require_once('include/errors.php');
require_once('include/auth/tokenstrategy/cookiestrategy.php');
require_once('include/auth/tokenstrategy/iostrategy.php');
require_once('include/auth/tokenstrategy/tokenstrategy.php');
require_once('include/file-utils.php');
require_once('include/proc-utils.php');
require_once('include/str-utils.php');
require_once('include/announce.php');
require_once('include/auth/auth.php');
require_once('include/case-transform.php');
require_once('include/checkout-helper.php');
require_once('include/git.php');
require_once('include/lint.php');
require_once('include/lint/pylint.php');
require_once('include/lint/importlint.php');
require_once('include/lock-handler.php');
require_once('include/module.php');
require_once('include/output.php');
require_once('include/project-manager.php');
require_once('include/user.php');
require_once('include/notifications.php');
require_once('include/resizable-image.php');
require_once('include/team-status.php');
require_once('include/settings.php');
