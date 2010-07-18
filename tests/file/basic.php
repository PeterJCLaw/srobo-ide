<?php

//delete any existing repos
if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
exec("mkdir -p /tmp/test-repos");

$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "death");
$config->override("user.default.groups", array("beedogs", "failcakes"));
$config->override("auth_module", "single");
$config->override("modules", array("file"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('',''), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", "beedogs");
$input->setInput("project", "monkies");

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("file"), true, "file module does not exist");

$file = $mm->getModule('file');

$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/" . $input->getInput("project");

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository($input->getInput("team"), $input->getInput("project"));
test_true(is_dir($repopath), "created repo did not exist");

$input->setInput('path', 'wut');
$file->dispatchCommand('new');
test_true(file_exists("$repopath/wut"), 'failed to create file');
$input->setInput('data', 'deathcakes');
$file->dispatchCommand('put');
test_equal(file_get_contents("$repopath/wut"), 'deathcakes', 'wrong content in file');
$file->dispatchCommand('get');
test_equal($output->getOutput('data'), 'deathcakes', 'read file incorrectly');
$input->setInput('old-path', 'wut');
$input->setInput('new-path', 'huh');
$file->dispatchCommand('cp');
test_true(file_exists("$repopath/wut"), 'old file was deleted during cp');
test_true(file_exists("$repopath/huh"), 'new file not created during cp');
test_equal(file_get_contents("$repopath/huh"), 'deathcakes', 'new file had wrong content after cp');
$file->dispatchCommand('del');
test_false(file_exists("$repopath/wut"), 'file not deleted during del');
$input->setInput('old-path', 'huh');
$input->setInput('new-path', 'wut');
$file->dispatchCommand('mv');
test_true(file_exists("$repopath/wut"), 'target did not exist after move');
test_false(file_exists("$repopath/huh"), 'old file still exists after move');
$input->setInput('path', '.');
$file->dispatchCommand('list');
test_equal($output->getOutput('files'), array('wut'), 'incorrect file list');

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
