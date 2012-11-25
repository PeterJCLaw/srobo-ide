<?php

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "death");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
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
test_equal($mm->moduleExists("proj"), true, "proj module does not exist");

$repopath = $config->getConfig("repopath") . "/" . $input->getInput("team") . "/master/" . $input->getInput("project") . ".git";

$proj = $mm->getModule("proj");
$proj->dispatchCommand("del");

test_false(is_dir($repopath), "deleted repo existed");
