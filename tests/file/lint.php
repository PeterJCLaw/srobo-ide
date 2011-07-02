<?php

//delete any existing repos
if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
exec("mkdir -p /tmp/test-repos");

$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("modules", array("file"));

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

$goodData = 'from sr import *

def main():
	yield query.io[1].input[1].d
	#beans
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
$file->dispatchCommand('lint');
$expectedError = new StdClass;
$expectedError->file = 'robot.py';
$expectedError->lineNumber = 1;
$expectedError->message = "Undefined variable 'bananas'";
$expectedError->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError), 'Failed to report errors correctly');

section("other bad file, committed");
$output->setOutput('errors', null);
$input->setInput('data', str_replace('query', 'qeury', $goodData));
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$file->dispatchCommand('lint');
$expectedError = new StdClass;
$expectedError->file = 'robot.py';
$expectedError->lineNumber = 4;
$expectedError->message = "Undefined variable 'qeury'";
$expectedError->level = 'error';
test_equal($output->getOutput('errors'), array($expectedError), 'Failed to report errors correctly');

section("non-existent file");
$output->setOutput('error', null);
$input->setInput('path', 'face.py');
$file->dispatchCommand('lint');
test_equal($output->getOutput('error'), 'file does not exist', 'Failed to report missing file');

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
