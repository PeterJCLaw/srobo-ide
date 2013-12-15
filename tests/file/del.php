<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("file", 'proj'));

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

function createAndAssertFileDeleted($path)
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();
	$output = Output::getInstance();

	echo "Testing deletion of file '$path' for team ", $input->getInput('team'), ', project ', $input->getInput('project'), ".\n";
	$repopath = getRepoPath();

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists('file'), 'file module does not exist');
	$file = $mm->getModule('file');
	$proj = $mm->getModule('proj');
	test_nonnull($file, 'file module does not exist');
	test_nonnull($proj, 'proj module does not exist');

	// create the file
	$input->setInput('path', $path);
	$content = 'deathcakes'.$path;
	$input->setInput('data', $content);
	test_true($file->dispatchCommand('put'), "Failed to add content to the file '$path' to be removed.");
	$abspath = "$repopath/$path";
	test_true(file_exists($abspath), "failed to create file '$abspath'");
	// commit it
	$input->setInput('paths', array($path));
	$input->setInput('message', "Create '$path'.");
	test_true($proj->dispatchCommand('commit'), "Failed to commit file '$path' to be removed.");

	// delete the file
	$input->setInput('files', array($path));
	$file->dispatchCommand('del');
	$abspath = "$repopath/$path";
	test_false(file_exists($abspath), "failed to delete file '$abspath'");
	// commit
	$input->setInput('message', "Delete '$path'.");
	test_true($proj->dispatchCommand('commit'), "Failed to commit removal of file '$path'.");
	test_false(file_exists($abspath), "File '$abspath' exists after committing its removal.");

	// get the file-list to check that it's really gone
	$input->setInput('path', '.');
	test_true($file->dispatchCommand('list'), "Failed to get file list after removing '$path'.");
	$list = $output->getOutput('files');
	test_false(in_array($path, $list), "File '$abspath' listed after committing its removal.");
	test_false(file_exists($abspath), "File '$abspath' exists after getting file list after committing its removal.");
}

createAndAssertFileDeleted('simple-file-name');
createAndAssertFileDeleted('spacey path');
createAndAssertFileDeleted('subdir/file');
createAndAssertFileDeleted('subdir/spacey path');
createAndAssertFileDeleted('spacey subdir/spacey path');
createAndAssertFileDeleted('variable $file name');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndAssertFileDeleted('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndAssertFileDeleted('char \''.$char.'\'.');
}
