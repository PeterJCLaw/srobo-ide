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

$input->setInput('path', 'wut');
$file->dispatchCommand('new');
test_true(file_exists("$repopath/wut"), 'failed to create file');
$input->setInput('data', 'deathcakes');
$file->dispatchCommand('put');
test_equal(file_get_contents("$repopath/wut"), 'deathcakes', 'wrong content in file');

// Commit and test the result
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$input->setInput('rev', 'HEAD');
$file->dispatchCommand('get');
test_equal($output->getOutput('original'), 'deathcakes', 'read unchanged original file incorrectly');
test_equal($output->getOutput('autosaved'), null, 'read unchanged autosaved file incorrectly');

// Autosave and test the result
$input->setInput('data', 'bananas');
$file->dispatchCommand('put');
$file->dispatchCommand('get');
test_equal($output->getOutput('original'), 'deathcakes', 'read changed original file incorrectly');
test_equal($output->getOutput('autosaved'), 'bananas', 'read changed autosaved file incorrectly');

// Clear the autosave and test the result
$input->setInput('files', array($input->getInput('path')));
$input->setInput('revision', 0);
$file->dispatchCommand('co');
$file->dispatchCommand('get');
test_equal($output->getOutput('original'), 'deathcakes', 'read checkouted original file incorrectly');
test_equal($output->getOutput('autosaved'), null, 'read checkouted autosaved file incorrectly');

// Move the file and test the result
$input->setInput('old-path', 'wut');
$input->setInput('new-path', 'huh');
$file->dispatchCommand('cp');
test_true(file_exists("$repopath/wut"), 'old file was deleted during cp');
test_true(file_exists("$repopath/huh"), 'new file not created during cp');
test_equal(file_get_contents("$repopath/huh"), 'deathcakes', 'new file had wrong content after cp');
$input->setInput("files", array("wut"));
$repo->stage("wut");
$file->dispatchCommand('del');
test_false(file_exists("$repopath/wut"), 'file not deleted during del');
$input->setInput('old-path', 'huh');
$input->setInput('new-path', 'wut');
$repo->stage("huh");
$file->dispatchCommand('mv');

$repo->commit("bees","bees","bees@example.com");
test_true(file_exists("$repopath/wut"), 'target did not exist after move');
test_false(file_exists("$repopath/huh"), 'old file still exists after move');
$input->setInput('path', '.');
$file->dispatchCommand('list');
test_equal($output->getOutput('files'), array('__init__.py', 'robot.py', 'wut'), 'incorrect file list');
$file->dispatchCommand('compat-tree');
test_equal($output->getOutput('files'), array('__init__.py', 'robot.py', 'wut'), 'incorrect file tree');

// Test linting
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

// good file, committed
$input->setInput('autosave', null);
$file->dispatchCommand('lint');
test_equal($output->getOutput('file'), 'robot.py', 'Reported wrong file');
test_equal($output->getOutput('path'), '.', 'Reported wrong path');
test_equal($output->getOutput('errors'), array(), 'Reported false errors');
test_equal($output->getOutput('messages'), array(), 'Reported extra messages');

// really bad file, committed
$input->setInput('data', 'bananas');
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$file->dispatchCommand('lint');
test_equal($output->getOutput('file'), 'robot.py', 'Reported wrong file');
test_equal($output->getOutput('path'), '.', 'Reported wrong path');
test_equal($output->getOutput('errors'), array("robot.py:1: [E] Undefined variable 'bananas'"), 'Failed to report errors correctly');
test_equal($output->getOutput('messages'), array("robot.py:1: [E] Undefined variable 'bananas'"), 'Failed to report messages correctly');

// other bad file, committed
$input->setInput('data', str_replace('query', 'qeury', $goodData));
$file->dispatchCommand('put');
$repo->stage($input->getInput('path'));
$repo->commit('message', 'test-name', 'test@email.tld');
$file->dispatchCommand('lint');
test_equal($output->getOutput('file'), 'robot.py', 'Reported wrong file');
test_equal($output->getOutput('path'), '.', 'Reported wrong path');
test_equal($output->getOutput('errors'), array("robot.py:4: [E, main] Undefined variable 'qeury'"), 'Failed to report errors correctly');
test_equal($output->getOutput('messages'), array("robot.py:4: [E, main] Undefined variable 'qeury'"), 'Failed to report messages correctly');


if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
