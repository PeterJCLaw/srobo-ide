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
$tree = $repo->fileTreeCompat("cake");

test_equal($tree[0]['kind'], 'FOLDER', 'tested folder had incorrect kind');
test_equal($tree[0]['name'], 'ninjas', 'tested folder had incorrect name');
test_equal($tree[0]['path'], '/cake/ninjas', 'tested folder had incorrect path');
test_equal($tree[0]['children'][0]['kind'], 'FILE', 'tested sub-file had incorrect kind');
test_equal($tree[0]['children'][0]['name'], 'nuns', 'tested sub-file had incorrect name');
test_equal($tree[0]['children'][0]['path'], '/cake/ninjas/nuns', 'tested sub-file had incorrect path');
test_equal($tree[1]['kind'], 'FILE', 'tested file had incorrect kind');
test_equal($tree[1]['name'], 'robot.py', 'tested file had incorrect name');
test_equal($tree[1]['path'], '/cake/robot.py', 'tested file had incorrect path');
test_true(isset($tree[0]['autosave']), 'autosave not set');
