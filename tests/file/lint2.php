<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("file"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 1);
$input->setInput("project", "monkies");

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("file"), true, "file module does not exist");

$file = $mm->getModule('file');

$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/users/bees/" . $input->getInput("project");

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository($input->getInput("team"), $input->getInput("project"));
$repo = $projectManager->getUserRepository(1, 'monkies', 'bees');
test_true(is_dir($repopath), "created repo did not exist");

$robotData = 'import sr

import other
';

function commitData($repo, $file, $data)
{
	$input = Input::getInstance();
	$fileModule = ModuleManager::getInstance()->getModule('file');
	$input->setInput('path', $file);
	$input->setInput('data', $data);
	$fileModule->dispatchCommand('put');
	$repo->stage($input->getInput('path'));
	$repo->commit('message', 'test-name', 'test@email.tld');
}

section("robot.py with missing import");
commitData($repo, 'robot.py', $robotData);
$output->setOutput('errors', null);
$file->dispatchCommand('lint');
$expectedError1 = new StdClass;
$expectedError1->file = 'robot.py';
$expectedError1->lineNumber = 3;
$expectedError1->message = "Could not import 'other'";
$expectedError1->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError1), 'Failed to report errors correctly');

section("robot.py with import");
$output->setOutput('errors', null);
commitData($repo, 'other.py', '#Nothing here');
$file->dispatchCommand('lint');
test_equal($output->getOutput('errors'), array(), 'Reported false errors');

section("robot.py with bad indent");
$output->setOutput('errors', null);
commitData($repo, 'other.py', "if False:\nprint False");
$file->dispatchCommand('lint');
$expectedError2 = new StdClass;
$expectedError2->file = 'other.py';
$expectedError2->lineNumber = 2;
$expectedError2->message = "expected an indented block";
$expectedError2->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError2), 'Failed to report errors correctly');
