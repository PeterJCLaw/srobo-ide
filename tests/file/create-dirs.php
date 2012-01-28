<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
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

function createAndAssertFolder($path)
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();

	subsection("Testing creatin of folder '$path' for team ".$input->getInput('team').', project '.$input->getInput('project'));
	$input->setInput('path', $path);
	$repopath = getRepoPath();

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists('file'), 'file module does not exist');
	$file = $mm->getModule('file');
	test_nonnull($file, 'file module does not exist');

	// create the folder
	$input->setInput('path', $path);
	$ret = $file->dispatchCommand('mkdir');
	$abspath = "$repopath/$path";
	test_true(file_exists($abspath), "Failed to create folder '$abspath'");
	test_true(is_dir($abspath), "'$abspath' is was not a folder");
	test_true($ret, "Creation of folder '$abspath' claims to have failed");

	// check that it still exists after a compat-tree call
	$file->dispatchCommand('compat-tree');
	test_true(file_exists($abspath), "Folder '$abspath' no longer present after a compat-tree");
	test_true(is_dir($abspath), "'$abspath' is was not a folder");
}

createAndAssertFolder('simple-folder-name');
createAndAssertFolder('spacey path');
createAndAssertFolder('subdir/folder');
createAndAssertFolder('subdir/spacey path');
createAndAssertFolder('spacey subdir/spacey path');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndAssertFolder('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndAssertFolder('char \''.$char.'\'.');
}
