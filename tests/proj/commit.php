<?php

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}

exec("mkdir -p /tmp/test-repos/1");

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", "/tmp/test-repos");
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("modules", array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$output = Output::getInstance();
$input = Input::getInstance();
$input->setInput("team", 1);
$input->setInput("project", "monkies");
$input->setInput("message", "faces");

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository(1, 'monkies');
$beesRepo = $projectManager->getUserRepository(1, 'monkies', 'bees');

$mm = ModuleManager::getInstance();
$mm->importModules();
$proj = $mm->getModule("proj");

// create some files we can try to commit
section('Simple create & commit');
$beesRepo->putFile("ponies.py", "print 'cows'\n");

$currRev = $beesRepo->getCurrentRevision();

$input->setInput('paths', array('ponies.py'));
test_true($proj->dispatchCommand('commit'), 'Failed simple commit');;
$newRev = $output->getOutput('commit');
test_nonequal($currRev, $newRev, 'Failed to create a new commit');

// someone else makes a commit
section('Commit on top of a remote commit');
subsection('Make the remote commit');
$otherRepo = $projectManager->getUserRepository(1, 'monkies', 'other');
$otherRepo->putFile("bees.py", "print 'sparrows'\n");
$otherRepo->stage("bees.py");
$otherRepo->commit('bees', 'face', 'f@ce');
$otherRepo->push();
$currRev = $otherRepo->getCurrentRevision();

// the first user tries to commit again.
// This should work - it should be automagically merged.
subsection('Make the user\'s commit');
$beesRepo->putFile("ponies.py", "print 'ponies'\n");
test_true($proj->dispatchCommand('commit'), 'Failed commit on top of remote commit');;
$newRev = $output->getOutput('commit');
test_nonequal($currRev, $newRev, 'Failed to create a new commit');

subsection('Empty commit');
test_false($proj->dispatchCommand('commit'), 'Empty commits should fail');;

$log = $beesRepo->log();
var_dump($log);
test_equal(count($log), 5, 'Wrong number of commits in the log');

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
