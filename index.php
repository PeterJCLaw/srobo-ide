<?php

if (version_compare(PHP_VERSION, '5.3.0') < 0)
{
	header('HTTP/1.1 501 Not Implemented');
	header('Content-type: text/plain');
	echo 'This server does not have PHP 5.3.0 or later installed.' . "\n";
	exit();
}

error_reporting(E_ALL | E_STRICT);

/*
 * Whether or not the user's browser is partially compatible with the IDE.
 * Any version of IE
 */
function partially_compatible_browser()
{
	return true;
	//TODO: check that this actually picks up IEs
	return stripos($_SERVER['HTTP_USER_AGENT'], 'internet explorer') !== FALSE;
}

/*
 * Whether or not the user's browser is incompatible with the IDE.
 * Basically just IE6
 */
function incompatible_browser()
{
	//TODO: make this just pick up IE6
	return stripos($_SERVER['HTTP_USER_AGENT'], 'internet explorer') !== FALSE;
}

/*
 * Uses a template to show the user a message
 */
function show_message($msg, $query = null)
{
	$page = file_get_contents('web/message.html');
	$page = str_replace('MESSAGE_TEXT', $msg, $page);
	if ($query != null)
	{
		$page = str_replace('<!-- QUERY', '', $page);
		$page = str_replace('QUERY -->', '', $page);
		$page = str_replace('BUTTON_TEXT', $query, $page);
	}
	echo $page;
}

// check for incompatiable browsers trying anyway
if ( incompatible_browser() && !empty($_POST['try-anyway']) )
{
	show_message('Sorry, your browser isn\'t compatible with the IDE.
	             You really should update or <a href="http://getfirefox.com">upgrade</a>!');
	exit();
}

// check for crap browsers, first time round
if ( partially_compatible_browser() && empty($_POST['try-anyway']) )
{
	show_message('Your browser isn\'t fully compatible with the IDE.
	              Why don\'t you update or <a href="http://getfirefox.com">upgrade</a>?',
	             'Try anyway');
	exit();
}

// includes
require_once('include/main.php');
try {
	$config = Configuration::getInstance();

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
		getDefaultTokenStrategy()->removeAuthToken();
		readfile('web/login.html');
	}
} catch (Exception $e) {
	echo 'The ide exploded, please tell the srobo team immediately';
}
