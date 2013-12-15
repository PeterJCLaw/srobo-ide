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
$projectManager->createRepository(1, 'cake');
$repo = $projectManager->getUserRepository(1, 'cake', 'bees');

$repo->gitMKDir('ninjas');
$repo->putFile('ninjas/nuns', "the cake is a lie\n");
$repo->gitMKDir('ninjas/z-ninjaChildren');
$repo->putFile('ninjas/z-ninjaChildren/pirates', 'Captain Jack');
$tree = $repo->fileTreeCompat("cake");

test_equal($tree[0]['kind'], 'FOLDER', 'tested folder had incorrect kind');
test_equal($tree[0]['name'], 'ninjas', 'tested folder had incorrect name');
test_equal($tree[0]['path'], '/cake/ninjas', 'tested folder had incorrect path');
test_equal($tree[0]['children'][0]['kind'], 'FILE', 'tested sub-file had incorrect kind');
test_equal($tree[0]['children'][0]['name'], 'nuns', 'tested sub-file had incorrect name');
test_equal($tree[0]['children'][0]['path'], '/cake/ninjas/nuns', 'tested sub-file had incorrect path');
test_equal($tree[0]['children'][1]['kind'], 'FOLDER', 'tested sub-folder had incorrect kind');
test_equal($tree[0]['children'][1]['name'], 'z-ninjaChildren', 'tested sub-folder had incorrect name');
test_equal($tree[0]['children'][1]['path'], '/cake/ninjas/z-ninjaChildren', 'tested sub-folder had incorrect path');
test_equal($tree[0]['children'][1]['children'][0]['kind'], 'FILE', 'tested sub-sub-file had incorrect kind');
test_equal($tree[0]['children'][1]['children'][0]['name'], 'pirates', 'tested sub-sub-file had incorrect name');
test_equal($tree[0]['children'][1]['children'][0]['path'], '/cake/ninjas/z-ninjaChildren/pirates', 'tested sub-sub-file had incorrect path');
test_equal($tree[1]['kind'], 'FILE', 'tested file had incorrect kind');
test_equal($tree[1]['name'], 'robot.py', 'tested file had incorrect name');
test_equal($tree[1]['path'], '/cake/robot.py', 'tested file had incorrect path');
test_true(empty($tree[0]['autosave']), 'Folders shuoldn\'t have autosave values');
test_true(isset($tree[0]['children'][0]['autosave']), 'Autosave not set on folder child file');
test_true(isset($tree[1]['autosave']), 'Autosave not set on file');
