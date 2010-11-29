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

$config = Configuration::getInstance();
if ($config->getConfig('require_ssl') && empty($_SERVER['HTTPS']))
{
	header('HTTP/1.1 403 Forbidden');
	header('Content-type: text/plain');
	echo "Unencrypted HTTP not allowed, please use HTTPS.\n";
	exit();
}

// decode input
$request = substr($_SERVER['PATH_INFO'], 1);
$data = json_decode(file_get_contents('php://input'));
if (empty($data))
	$data = array();

$input  = Input::getInstance();
$output = Output::getInstance();

$input->setRequest($request);
foreach ($data as $key => $value)
{
	$input->setInput($key, $value);
}

// install all modules
try
{
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

header('Content-type: text/json');
header('Content-length: ' . strlen($data));

echo $data;
