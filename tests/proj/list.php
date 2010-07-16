<?php
//delete any already existing test repos
if (is_dir(getcwd()."/test-repos"))
{
    unlink(getcwd()."/test-repos");
}

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", "ROOT/test-repos");
$config->override("user.default", "death");
$config->override("user.default.groups", array("beedogs", "failcakes"));
$config->override("auth_module", "single");
$config->override("modules", array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
$next_auth_token = $auth->authUser(array("user" => "", "password" => ""));
test_equal($next_auth_token, 1, "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", "beedogs");
$input->setInput("project", "testing-project");

//get a project instance
$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("proj"), TRUE, "proj module does not exist");
$proj = $mm->getModule("proj");
test_nonnull($proj, "recieved proj module was null");
//list the emtpy project
$list = $proj->dispatchCommand("list");
print_r($list);
test_equal(count($list), 0, "the project wasn't empty");

// delete the created repos
if (is_dir(getcwd()."/test-repos"))
{
    unlink(getcwd()."/test-repos");
}

