<?php
//delete any already existing test repos
if (is_dir("/tmp/test-repos"))
{
    unlink("/tmp/test-repos");
}

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "death");
$config->override("user.default.groups", array("beedogs", "failcakes"));
$config->override("auth_module", "single");
$config->override("modules", array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('', ''), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", "beedogs");
$input->setInput("project", "testing-project");

//get a project instance
$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("proj"), true, "proj module does not exist");
$proj = $mm->getModule("proj");
test_nonnull($proj, "recieved proj module was null");
//list the emmtpy project
$list = Output::getInstance()->getOutput("files");
test_equal(count($list), 0, "the project wasn't empty");

// delete the created repos
if (is_dir("/tmp/test-repos"))
{
    unlink("/tmp/test-repos");
}

