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

$mm = ModuleManager::getInstance();
$mm->importModules();

function createAndAssertFileMoved($newPath)
{
	static $oldPath = 'robot.py';	// guarunteed to exist at the start, from then on we just use the same file.
	$input = Input::getInstance();
	$output = Output::getInstance();

	$moveMsg = "move file '$oldPath' to '$newPath'";
	section('Testing: '.$moveMsg);
	echo 'team: ', $input->getInput('team'), ', project: ', $input->getInput('project'), ".\n";
	$repopath = getRepoPath();

	// get the modules
	$mm = ModuleManager::getInstance();
	test_true($mm->moduleExists('file'), 'file module does not exist');
	test_true($mm->moduleExists('proj'), 'proj module does not exist');
	$file = $mm->getModule('file');
	$proj = $mm->getModule('proj');
	test_nonnull($file, 'file module does not exist');
	test_nonnull($proj, 'proj module does not exist');

	// assert the original file exists to start with, and that the new one doesn't
	$absOldPath = "$repopath/$oldPath";
	test_existent($absOldPath, "Original file before move");
	$absNewPath = "$repopath/$newPath";
	test_nonexistent($absNewPath, "New file before move");

	// move the file
	subsection('Move');
	$input->setInput('old-path', $oldPath);
	$input->setInput('new-path', $newPath);
	test_true($file->dispatchCommand('mv'), "Failed to $moveMsg.");
	test_nonexistent($absOldPath, "Original file after move");
	test_existent($absNewPath, "New file after move");
	// commit it
	subsection('Commit');
	$input->setInput('paths', array($oldPath, $newPath));
	$input->setInput('message', $moveMsg);
	test_true($proj->dispatchCommand('commit'), "Failed to commit: $moveMsg.");
	test_nonexistent($absOldPath, "Original file after commit");
	test_existent($absNewPath, "New file after commit");

	// get the file-list to check that it's really moved
	subsection('Assert listing');
	$subDir = dirname($newPath);
	$input->setInput('path', $subDir);
	test_true($file->dispatchCommand('list'), "Failed to get file list after: $moveMsg.");
	$list = $output->getOutput('files');
	var_dump($list);
	$oldBasename = basename($oldPath);
	$newBasename = basename($newPath);
	test_false(in_array($oldBasename, $list), "File '$oldBasename' listed after: $moveMsg.");
	test_true(in_array($newBasename, $list), "File '$newBasename' not listed after: $moveMsg.");

	// assign the newPath to the oldPath so that things work the next time around
	$oldPath = $newPath;
}

createAndAssertFileMoved('simple-file-name');
createAndAssertFileMoved('spacey path');
mkdir_full("$repopath/subdir");
createAndAssertFileMoved('subdir/file');
createAndAssertFileMoved('subdir/spacey path');
mkdir_full("$repopath/spacey subdir");
createAndAssertFileMoved('spacey subdir/other spacey path');
createAndAssertFileMoved('variable $file name');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndAssertFileMoved('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndAssertFileMoved('char \''.$char.'\'.');
}
