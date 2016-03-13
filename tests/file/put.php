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

function getRepoPath()
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();
	return $config->getConfig("repopath") . "/" . $input->getInput("team") . "/users/bees/" . $input->getInput("project");
}

$repopath = getRepoPath();

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository($input->getInput("team"), $input->getInput("project"));
$repo = $projectManager->getUserRepository($input->getInput("team"), $input->getInput("project"), 'bees');
test_true(is_dir($repopath), "created repo did not exist");

function createAndAssertFile($path)
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();

	section("Testing setting of content of file '$path' for team " . $input->getInput('team') . ', project ' . $input->getInput('project'));
	$input->setInput('path', $path);
	$repopath = getRepoPath();

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists('file'), 'file module does not exist');
	$file = $mm->getModule('file');
	test_nonnull($file, 'file module does not exist');

	subsection('assert that content is written to it');
	$input->setInput('path', $path);
	$content = 'deathcakes'.$path;
	$input->setInput('data', $content);
	$file->dispatchCommand('put');
	$abspath = "$repopath/$path";
	test_equal(file_get_contents($abspath), $content, "Wrong content in file '$path'.");
}

createAndAssertFile('simple-file-name');
createAndAssertFile('spacey path');
createAndAssertFile('subdir/file');
createAndAssertFile('subdir/spacey path');
createAndAssertFile('spacey subdir/spacey path');
createAndAssertFile('variable $file name');
createAndAssertFile('--hyphenated-file-name');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndAssertFile('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndAssertFile('char \''.$char.'\'.');
}
