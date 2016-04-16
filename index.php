<?php

if (version_compare(PHP_VERSION, '5.3.3') < 0)
{
	header('HTTP/1.1 501 Not Implemented');
	header('Content-type: text/plain');
	echo 'This server does not have PHP 5.3.3 or later installed.' . "\n";
	exit();
}

error_reporting(E_ALL | E_STRICT);

// includes
require_once('include/main.php');
try
{
	$config = Configuration::getInstance();

	if ($config->getConfig('require_ssl') && empty($_SERVER['HTTPS']))
	{
		header('HTTP/1.1 403 Forbidden');
		header('Content-type: text/plain');
		echo "Unencrypted HTTP not allowed, please use HTTPS.\n";
		exit();
	}

	// block external or inline loading of most things,
	// allow data: urls for teams-status images
	// allow inline styles for ACE
	// allow JSON2, eval, jQuery and our inline piwik hook
	header("Content-Security-Policy: default-src 'self';" .
	                                "img-src 'self' data:;" .
	                                "style-src 'self' 'unsafe-inline';" .
	                                "script-src 'self' 'unsafe-eval' ajax.googleapis.com/ajax/libs/ sha256-cGG4us2djtknhpitJBrPLUVJnGysVgkKIyL/tBN5Dls=;");

	// If the user is logged in give them the main index page
	// Else give them the login page
	$auth = AuthBackend::getInstance();
	$token = getDefaultTokenStrategy()->getAuthToken();
	if ($auth->validateAuthToken($token))
	{
		include('web/index.html.php');
	}
	else
	{
		getDefaultTokenStrategy()->removeAuthToken();
		include('web/login.html.php');
	}
}
catch (Exception $e)
{
	header('HTTP/1.1 500 Internal Server Error');
	header('Content-type: text/plain');
	echo "The ide exploded, please tell the srobo team immediately.\n";
	echo "Debugging information:\n";
	echo $e->getMessage() . "\n";
	echo $e->getTraceAsString() . "\n";
	ide_log_exception($e);
}
