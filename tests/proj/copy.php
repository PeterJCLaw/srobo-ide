<?php

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "death");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 1);

$input->setInput('project', 'reference-project');
$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists("proj"), "proj module does not exist");

$proj = $mm->getModule("proj");
test_true($proj->dispatchCommand("new"), "failed to create initial project");

function copyAndAssertProject($projname)
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();
	$team = $input->getInput("team");

	section("Testing copying project to '$projname' for team '$team'");
	$input->setInput('new-name', $projname);

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists("proj"), "proj module does not exist");

	$repopath = $config->getConfig("repopath") . "/$team/master/$projname.git";
	$proj = $mm->getModule("proj");
	test_true($proj->dispatchCommand("copy"), "Failed to copy project");

	test_is_dir($repopath, "repo for proj '$projname' did not exist");
}

copyAndAssertProject('monkies');
copyAndAssertProject('spacey path');
copyAndAssertProject('--hyphenated-proj-name');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	copyAndAssertProject('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	copyAndAssertProject('char \''.$char.'\'.');
}
