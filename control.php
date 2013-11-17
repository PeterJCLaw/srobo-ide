<?php

if (version_compare(PHP_VERSION, '5.3.3') < 0)
{
	header('HTTP/1.1 501 Not Implemented');
	header('Content-type: text/plain');
	echo 'This server does not have PHP 5.3.3 or later installed.' . "\n";
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

// A dummy delay when debugging so that you can see things happening in the UI
if (($delay = $config->getConfig('debug.delay')))
{
	usleep($delay * 1000);
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

try
{
	$mm = ModuleManager::getInstance();
	$mm->importModules(false);	// core modules only

	$modName = $input->getRequestModule();
	$commandName = $input->getRequestCommand();
	$mm->dispatchCommand($modName, $commandName);
}
catch (Exception $e)
{
	ide_log_exception($e);
	$output->setOutput('error', parts_for_output($e));
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
