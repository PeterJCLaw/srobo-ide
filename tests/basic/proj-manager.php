<?php

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
exec("mkdir -p /tmp/test-repos");

$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("modules", array("file"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

$projectManager = ProjectManager::getInstance();

section('slashes in name: create');
$projName = 'cake/face';
$repopath = $config->getConfig("repopath") . "/1/master/" . $projName . ".git";
$ret = $projectManager->createRepository(1, $projName);

test_false($ret, 'did not block creation of a project with / in the name');
test_false(is_dir($repopath), 'created repo with / in the name!');


section('slashes in name: copy');
$repopath2 = $config->getConfig("repopath") . "/1/master/" . 'cake' . ".git";
$projectManager->createRepository(1, 'cake');
test_true(is_dir($repopath2), 'Failed to create repo to copy');

$ret = $projectManager->copyRepository('cake', $projName);

test_false($ret, 'did not block copying of a project with / in the name');
test_false(is_dir($repopath), 'copied repo with / in the name!');

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
