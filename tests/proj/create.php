<?php

//delete any existing repos
if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
exec("mkdir -p /tmp/test-repos");

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "death");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("modules", array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 1);

function createAndAssertProject($projname)
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();

	echo "Testing creatin of project '$projname' for team ", $input->getInput("team"), ".\n";
	$input->setInput('project', $projname);

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists("proj"), "proj module does not exist");


	$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/master/" . $input->getInput("project") . ".git";
	$proj = $mm->getModule("proj");
	$proj->dispatchCommand("new");

	test_true(is_dir($repopath), "repo for proj '$projname' did not exist");
}

createAndAssertProject('monkies');
createAndAssertProject('spacey path');

$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndAssertProject('char \''.$chars[$i].'\'.');
}

$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndAssertProject('char \''.$char.'\'.');
}

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
