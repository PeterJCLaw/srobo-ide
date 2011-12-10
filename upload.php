<?php

if (version_compare(PHP_VERSION, '5.3.0') < 0)
{
	header('HTTP/1.1 501 Not Implemented');
	header('Content-type: text/plain');
	echo 'This server does not have PHP 5.3.0 or later installed.' . "\n";
	exit();
}

error_reporting(E_ALL | E_STRICT);

ob_start();

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

	// If the user is not logged in bail
	$auth = AuthBackend::getInstance();
	$token = getDefaultTokenStrategy()->getAuthToken();
	if (!$auth->validateAuthToken($token))
	{
		header('HTTP/1.1 403 Forbidden');
		header('Content-type: text/plain');
		echo "You need to be logged in.\n";
		exit();
	}

	$input  = Input::getInstance();
	$output = Output::getInstance();

	if (!isset($_POST['_command']))
	{
		throw new Exception(E_MALFORMED_REQUEST, "No request type specified");
	}

	$input->setRequest($_POST['_command']);
	unset($_POST['_command']);
	foreach ($_POST as $key => $value)
	{
		$input->setInput($key, $value);
	}

	$mm = ModuleManager::getInstance();
	$mm->importModules();

	$modname = $input->getRequestModule();
	if (!$mm->moduleExists($modname))
	{
		throw new Exception("module $modname not found", E_MALFORMED_REQUEST);
	}
	$mod = $mm->getModule($modname);
	$result = $mod->dispatchCommand($input->getRequestCommand());
	if ($result === false)
	{
		throw new Exception('command dispatch failed', 1);
	}
}
catch (Exception $e)
{
	$output->setOutput('error', array($e->getCode(), $e->getMessage(), $e->getTraceAsString()));
}

if ($config->getConfig('debug'))
{
	$output->setOutput('debug', explode("\n", ob_get_contents()));
}
ob_end_clean();

$data = $output->encodeOutput();

header('Content-type: text/plain');
header('Content-length: ' . strlen($data));

echo $data;
