<?php

$config = Configuration::getInstance();
$config->override('modules.always', array('ping'));

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists('ping'), "ping module was not found");

$module = $mm->getModule('ping');

$input = Input::getInstance();
$output = Output::getInstance();

$module->dispatchCommand('ping');

$input->setInput('data', 'bees');
$module->dispatchCommand('ping');
test_equal($output->getOutput('data'), 'bees', 'ping response was incorrect');
