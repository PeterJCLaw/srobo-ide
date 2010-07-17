<?php

error_reporting(E_ALL | E_STRICT);

ob_start();

// includes
require_once('include/main.php');

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
	$input->addInput($key, $value);
}

// install all modules
$mm = ModuleManager::getInstance();
$mm->importModules();

try
{
	$modname = $input->getRequestModule();
	if (!$mm->moduleExists($modname))
	{
		throw new Exception("module $modname not found", E_MALFORMED_REQUEST);
	}
	$mod = $mm->getModule($modname);
	$mod->dispatchCommand($input->getRequestCommand());
}
catch (Exception $e)
{
	$output->setOutput('error', array($e->getCode(), $e->getMessage()));
}

$data = $output->encodeOutput();

header('Content-type: text/json');
header('Content-length: ' . strlen($data));

echo $data;
