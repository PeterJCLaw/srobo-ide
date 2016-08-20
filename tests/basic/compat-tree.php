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

function check_initial($tree)
{
    var_dump($tree);

    test_equal($tree[0]['kind'], 'FILE', 'tested file had incorrect kind');
    test_equal($tree[0]['name'], 'robot.py', 'tested file had incorrect name');
    test_equal($tree[0]['path'], '/cake/robot.py', 'tested file had incorrect path');

    test_false(isset($tree[0]['autosave']), 'Autosave should not be set (file)');

    test_equal(count($tree), 1, 'Should only be a single item in the initial tree');
}

function mkdir_and_holder($repo, $path)
{
    // simulate 'file/mkdir'
    $placeholder = $path.'/.directory';
    $repo->gitMKDir($path);
    $repo->createFile($placeholder);
    return $placeholder;
}

$tree = $repo->fileTreeCompat('cake');
check_initial($tree);

$dir_holders[] = mkdir_and_holder($repo, 'ninjas');
$repo->putFile('ninjas/nuns', "the cake is a lie\n");
$dir_holders[] = mkdir_and_holder($repo, 'ninjas/z-ninjaChildren');
$repo->putFile('ninjas/z-ninjaChildren/pirates', 'Captain Jack');
$dir_holders[] = mkdir_and_holder($repo, 'spacey empty-dir');
$dir_holders[] = mkdir_and_holder($repo, 'z-last');
$repo->putFile('z-last/bacon', 'tasty');

$repo->stage('ninjas/nuns');
$repo->stage('ninjas/z-ninjaChildren/pirates');
$repo->stage('z-last/bacon');
array_map(array($repo, 'stage'), $dir_holders);
$repo->commit('needed since we removed autosave', 'test-user', 'test@example.com');
$repo->push();

$tree = $repo->fileTreeCompat('cake');

var_dump($tree);

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
test_equal($tree[2]['kind'], 'FOLDER', 'tested folder had incorrect kind');
test_equal($tree[2]['name'], 'spacey empty-dir', 'tested folder had incorrect name');
test_equal($tree[2]['path'], '/cake/spacey empty-dir', 'tested folder had incorrect path');
test_equal($tree[2]['children'], array(), 'empty folder should be empty');
test_equal($tree[3]['kind'], 'FOLDER', 'tested folder had incorrect kind');
test_equal($tree[3]['name'], 'z-last', 'tested folder had incorrect name');
test_equal($tree[3]['path'], '/cake/z-last', 'tested folder had incorrect path');
test_equal($tree[3]['children'][0]['kind'], 'FILE', 'tested sub-file had incorrect kind');
test_equal($tree[3]['children'][0]['name'], 'bacon', 'tested sub-file had incorrect name');
test_equal($tree[3]['children'][0]['path'], '/cake/z-last/bacon', 'tested sub-file had incorrect path');

test_false(isset($tree[0]['autosave']), 'Autosave should not be set (folder)');
test_false(isset($tree[0]['children'][0]['autosave']), 'Autosave should not be set (child file)');
test_false(isset($tree[1]['autosave']), 'Autosave should not be set (root file)');

test_equal(count($tree), 4, 'Should be four root items in the main test tree');

$tree = $repo->fileTreeCompat('cake', '.', 'HEAD^');
check_initial($tree);
