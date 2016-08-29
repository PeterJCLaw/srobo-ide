<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("file"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

$projectManager = ProjectManager::getInstance();

section('slashes in name: create');
$projName = 'cake/face';
$repopath = $config->getConfig("repopath") . "/1/master/" . $projName . ".git";
test_exception(function () use ($projectManager, $projName) {
    $projectManager->createRepository(1, $projName);
}, E_MALFORMED_REQUEST, "Should reject project name containting slash: $projName.");

test_false(is_dir($repopath), 'created repo with / in the name!');


section('slashes in name: copy');
$srcName = 'the-src';
$repopath2 = $config->getConfig("repopath") . "/1/master/" . $srcName . ".git";
$projectManager->createRepository(1, $srcName);
test_true(is_dir($repopath2), 'Failed to create repo to copy');

$ret = $projectManager->copyRepository(1, $srcName, $projName);

test_false($ret, 'did not block copying of a project with / in the name');
test_false(is_dir($repopath), 'copied repo with / in the name!');

section('Check updateRepository merges local changes with upstream');

subsection('prepare repo');
$projName = 'face';
$repopath = $config->getConfig("repopath") . "/1/master/" . $projName . ".git";
$projectManager->createRepository(1, $projName);
test_existent($repopath, 'Failed to create project');

$repo = $projectManager->getUserRepository(1, $projName, 'jim');
test_nonnull($repo, "Failed to get repo: 1/$projName/jim");

$repo->putFile('committed', 'some committed content');
$repo->putFile('committed-autosave', 'some other committed content');

$repo->stage('committed');
$repo->stage('committed-autosave');

$repo->commit('commit message', 'jim', 'jim@someplace.com');
$repo->push();

subsection('prepare upstream commit');
$otherRepo = $projectManager->getUserRepository(1, $projName, 'dave');
test_nonnull($otherRepo, "Failed to get repo: 1/$projName/dave");

$otherRepo->putFile('other-file', $otherContent = 'some other content');
$otherRepo->stage('other-file');

$otherRepo->commit('other message', 'Dave', 'dave@someplace.com');
$otherRepo->push();
unset($otherRepo);

subsection('make local changes');
// we've now got a repo with a couple of committed files.
// so, we modify the state of the checkout, as autosaves do

$repo->gitMKDir('some-folder');
$repo->putFile('committed-autosave', $committedContent = 'some autosaved content in a committed file');
$repo->putFile('autosave', $autosaveContent = 'some autosaved content');

$folder = $repo->workingPath().'/some-folder';

$autosavedFile = $repo->workingPath().'/autosave';
$otherFile = $repo->workingPath().'/other-file';

subsection('validate behaviour');
$projectManager->updateRepository($repo, 'jim');

// test the result
test_existent($autosavedFile, 'Autosaved (uncommitted) files should remain after an update');
test_existent($folder, 'Empty folders should remain after an update');

test_existent($otherFile, 'File added upstream should now be present');

test_equal($repo->getFile('autosave'), $autosaveContent, 'Content of autosaved (uncommitted) file should be preserved');
test_equal($repo->getFile('committed-autosave'), $committedContent, 'Content of committed file plus an autosave should be preserved');
test_equal($repo->getFile('other-file'), $otherContent, 'Content of upstream file');
