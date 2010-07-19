<?php

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}

exec("mkdir -p /tmp/test-repos/1");

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

$repoPath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/" . $input->getInput("project");
//beat git into oblivion
$repo = GitRepository::createRepository($repoPath);
$repo->putFile("ponies.py", "print 'cows'\n");
$repo->commit("message", "test-name", "test@email.tld");
$repo->putFile("ponies.py", "print 'spoons'\n");
$repo->commit("message2", "test-name", "test@email.tld");

//get a project instance
$mm = ModuleManager::getInstance();
$mm->importModules();
$proj = $mm->getModule("proj");
$proj->dispatchCommand("log");

//check that the log has two keys
$log = Output::getInstance()->getOutput("log");
test_nonnull($log, "the log in output was null");
test_equal(count($log), 2, "the log did not contain exactly two commits");

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
