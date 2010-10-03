<?php

if (version_compare(PHP_VERSION, '5.3.0') < 0)
{
	header('HTTP/1.1 501 Not Implemented');
	header('Content-type: text/plain');
	echo 'This server does not have PHP 5.3.0 or later installed.' . "\n";
	exit();
}

error_reporting(E_ALL | E_STRICT);

// includes
require_once('include/main.php');
try {
    $config = Configuration::getInstance();

    throw new Exception("ponies");

    if ($config->getConfig('require_ssl') && empty($_SERVER['HTTPS']))
    {
    	header('HTTP/1.1 403 Forbidden');
	    header('Content-type: text/plain');
    	echo "Unencrypted HTTP not allowed, please use HTTPS.\n";
	    exit();
    }

    // If the user is logged in give them the main index page
    // Else give them the login page
    $auth = AuthBackend::getInstance();
    $token = getDefaultTokenStrategy()->getAuthToken();
    if ($auth->validateAuthToken($token))
    {
	    readfile('web/index.html');
    }
    else
    {
	    readfile('web/login.html');
    }
} catch (Exception $e) {
    echo "The ide exploded, please tell the srobo team immediately";
}
