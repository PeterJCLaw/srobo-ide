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
$input->setInput("project", "monkies");

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("proj"), true, "proj module does not exist");

$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/master/" . $input->getInput("project") . ".git";
$proj = $mm->getModule("proj");
$proj->dispatchCommand("new");

test_equal(is_dir($repopath), TRUE, "created repo did not exist");

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
