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
$input->setInput("project", "ponies");

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists("proj"), "proj module does not exist");

$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/master/" . $input->getInput("project") . ".git";

GitRepository::createRepository($repopath, true);
test_existent($repopath, "must have created repo to be deleted");

$proj = $mm->getModule("proj");
$proj->dispatchCommand("del");

test_nonexistent($repopath, "deleted repo existed");
