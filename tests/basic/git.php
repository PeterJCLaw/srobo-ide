<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
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

test_true($repo->commitExists($rev), "Commit given as the current HEAD ($rev) should exist!");
test_false($repo->commitExists('bacon'), "Commit 'bacon' should not exist!");
test_false($repo->commitExists('ninjas/nuns'), "Commit whose name matches a file (ninjas/nuns) should not exist!");

section('repo name');

test_equal($repo->repoName(), 'cake', "Wrong repo name for user (ie, non-bare) repo");

$repo = $projectManager->getMasterRepository(1, 'cake');

test_equal($repo->repoName(), 'cake', "Wrong repo name for master (ie bare) repo");
