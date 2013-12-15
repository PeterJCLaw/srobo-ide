<?php

$config = Configuration::getInstance();
$config->override("team.status_dir", $testWorkPath.'/status/');
$config->override("team.status_images.dir", $testWorkPath.'/images/');
$config->override("user.default", "bees");
$config->override("user.default.is_admin", true);
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("admin"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 'ABC');

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("admin"), true, "admin module does not exist");

$admin = $mm->getModule('admin');

$property = 'bacon';
$propertyValue = 'jam';

$statusABC = new TeamStatus('ABC');
$statusABC->setDraft($property, $propertyValue);
$statusABC->save('test');
var_dump($statusABC);

section('single team to review');
$admin->dispatchCommand('review-items-get');
$items = $output->getOutput('items');
test_true(is_array($items), 'The list of items needs to be an array.');
test_equal($items, array($property => $propertyValue), 'Should contain the correct item and content for review');

section('post review');
$statusABC->setReviewState($property, $propertyValue, true);
$statusABC->save('test');

$admin->dispatchCommand('review-items-get');
$items = $output->getOutput('items');
test_true(is_array($items), 'The list of items needs to be an array.');
test_equal($items, array(), 'After review should be an empty list that need review');

section('images not reviewed through IDE');
$statusABC->setDraft('image', $propertyValue);
$statusABC->save('test');

$admin->dispatchCommand('review-items-get');
$items = $output->getOutput('items');
test_true(is_array($items), 'The list of items needs to be an array.');
test_equal($items, array(), 'After adding an image, should still be an empty list that need review');
