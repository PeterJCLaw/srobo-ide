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
$input = Input::getInstance();
$input->setInput("team", 1);
$input->setInput("project", "monkies");

$commitUser = 'test-user';
$commitEmail = 'test@email.tld';
$expectedCommitter = "$commitUser <$commitEmail>";
$firstMessage = 'message';
$secondMessage = 'mess;age;23';

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository(1, 'monkies');
$repo = $projectManager->getUserRepository(1, 'monkies', 'bees');
$repo->putFile("ponies.py", "print 'cows'\n");
$repo->stage("ponies.py");
$repo->commit($firstMessage, $commitUser, $commitEmail);
$repo->putFile("ponies.py", "print 'spoons'\n");
$repo->stage("ponies.py");
$repo->commit($secondMessage, $commitUser, $commitEmail);
$projectManager->updateRepository(1, 'monkies', 'bees');

//get a project instance
$mm = ModuleManager::getInstance();
$mm->importModules();
$proj = $mm->getModule("proj");
$proj->dispatchCommand("log");

//check that the log has two keys
$log = Output::getInstance()->getOutput("log");
test_nonnull($log, "the log in output was null");
test_equal(count($log), 3, "the log did not contain exactly three commits");

//check that the log was parsed properly
function assertRev($log, $i, $expectedCommitter, $expectedMessage) {
	test_equal($log[$i]['author'], $expectedCommitter, "Log contained wrong ${i}th committer");
	test_equal($log[$i]['message'], $expectedMessage, "Log contained wrong ${i}th message");
}

assertRev($log, 1, $expectedCommitter, $firstMessage);
assertRev($log, 0, $expectedCommitter, $secondMessage);

if (is_dir("/tmp/test-repos"))
{
	exec("rm -rf /tmp/test-repos");
}
