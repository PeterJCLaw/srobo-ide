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
$projectManager->createRepository(1, 'cake');
$repo = $projectManager->getUserRepository(1, 'cake', 'bees');

$repo->gitMKDir('ninjas');
$repo->putFile('ninjas/nuns', "the cake is a lie\n");
$repo->gitMKDir('ninjas/z-ninjaChildren');
$repo->putFile('ninjas/z-ninjaChildren/pirates', 'Captain Jack');

$rev = $repo->getCurrentRevision();
test_true(preg_match('/^[a-f0-9]{7,40}$/', $rev),
          "revision contained extraneous characters ($rev)");

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
