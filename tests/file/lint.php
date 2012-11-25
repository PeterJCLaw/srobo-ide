<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
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

$goodData = 'import sr

R = sr.Robot()
print R.usbkey
print R.startfifo
print R.mode
print R.zone
';

$input->setInput('path', 'robot.py');
$input->setInput('data', $goodData);
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');

section("good file, committed");
$output->setOutput('errors', null);
$input->setInput('autosave', null);
$file->dispatchCommand('lint');
test_equal($output->getOutput('errors'), array(), 'Reported false errors');

section("really bad file, committed");
$output->setOutput('errors', null);
$input->setInput('data', 'bananas');
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$firstBadCommit = $repo->getCurrentRevision();
$file->dispatchCommand('lint');
$expectedError1 = new StdClass;
$expectedError1->file = 'robot.py';
$expectedError1->lineNumber = 1;
$expectedError1->message = "Undefined variable 'bananas'";
$expectedError1->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError1), 'Failed to report errors correctly');

section("other bad file, committed");
$output->setOutput('errors', null);
$input->setInput('data', str_replace('zone', 'zoen', $goodData));
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$file->dispatchCommand('lint');
$expectedError2 = new StdClass;
$expectedError2->file = 'robot.py';
$expectedError2->lineNumber = 7;
$expectedError2->message = "Instance of 'Robot' has no 'zoen' member";
$expectedError2->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError2), 'Failed to report errors correctly');

section("non-existent file");
$output->setOutput('error', null);
$input->setInput('path', 'face.py');
$file->dispatchCommand('lint');
test_equal($output->getOutput('error'), 'file does not exist', 'Failed to report missing file');

section("historic check");
$output->setOutput('errors', null);
$input->setInput('path', 'robot.py');
$input->setInput('rev', $firstBadCommit);
$file->dispatchCommand('lint');
test_equal($output->getOutput('errors'), array($expectedError1), 'Failed to report historic errors correctly');
