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

$status13 = new TeamStatus(13);
$status13->setDraft($property, $propertyValue);
$status13->setReviewState($property, $propertyValue, true);
$status13->save('test');
var_dump($status13);

section('single team to review');
$admin->dispatchCommand('review-teams-get');
$teams = $output->getOutput('teams');
test_true(is_array($teams), 'The list of teams needs to be an array.');
test_equal($teams, array('ABC'), 'The only member of the teams list should be "ABC"');

section('post review');
$statusABC->setReviewState($property, $propertyValue, true);
$statusABC->save('test');

$admin->dispatchCommand('review-teams-get');
$teams = $output->getOutput('teams');
test_true(is_array($teams), 'The list of teams needs to be an array.');
test_equal($teams, array(), 'After review should be an empty list that need review');
