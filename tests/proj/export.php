<?php

$input = Input::getInstance();
$output = Output::getInstance();
$config = Configuration::getInstance();

cleanCreate($testWorkPath.'/wd');
$test_repos_path = $testWorkPath.'/test-repos';
cleanCreate($test_repos_path);
$test_zip_path = $testWorkPath.'/ide-test-zip';
cleanCreate($test_zip_path);

// ensure the folder is missing so that we can test the failure mode
$zipPathBase = $testWorkPath.'/ide-zips';
test_nonexistent($zipPathBase, "before failure mode testing");

$config->override('repopath', $testWorkPath.'/test-repos');

$config->override('zippath', $zipPathBase);
$config->override('zipurl', $zipPathBase);
$config->override('user.default', 'death');
$config->override('user.default.teams', array(1, 2));
$config->override('auth_module', 'single');
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array('proj', 'file'));

$auth = AuthBackend::getInstance();
test_true($auth->authUser('death','face'), 'authentication failed');

$input->setInput('team', 1);
$input->setInput('project', 'ponies');

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists('proj'), 'proj module does not exist');
$proj = $mm->getModule('proj');
test_true($proj->dispatchCommand('new'), 'failed to create project');

subsection('put');
$robot_print = 'llama';
$robot_data = "print '$robot_print'\n";
$input->setInput('path', 'robot.py');
$input->setInput('data', $robot_data);
test_true($mm->moduleExists('file'), 'file module does not exist');
$file = $mm->getModule('file');
test_true($file->dispatchCommand('put'), 'put command failed');

subsection('commit');
$input->setInput('message', 'give robot some data');
$input->setInput('paths', array('robot.py'));
test_true($proj->dispatchCommand('commit'), 'commit command failed');

section('Failure mode');
$input->setInput('rev', 'HEAD');
// create a file where it's going to try to put a folder.
// we can't actually create the situation where the webserver doesn't have write access,
// since we're running as ourselves during the tests.
touch($zipPathBase);
// kill off the tests-failing error handler, because we know that this will error
set_error_handler(function($a, $b) {});
test_false($proj->dispatchCommand('co'), 'export command should have failed when export folder missing');
// restore the previous handler
restore_error_handler();
// remove our get-in-the-way file
unlink($zipPathBase);
test_nonexistent($zipPathBase, "removed after failure mode testing complete.");

section('Success testing');
test_true($proj->dispatchCommand('co'), 'export command should succeed');

$rev = $output->getOutput('rev');
test_nonempty($rev, 'Revision should not be empty - the user wants to know which version they\'re being served');

$zip_path = $output->getOutput('url');
echo 'zip_path: '; var_dump($zip_path);
test_true(file_exists($zip_path), "Zip doesn't exist at '$zip_path'.");
test_true(rename($zip_path, $testWorkPath.'wd/foo.zip'), "Failed to rename the zip from '$zip_path'.");
$s_wd = escapeshellarg($testWorkPath.'wd');
shell_exec("cd $s_wd && unzip foo.zip");

test_existent($testWorkPath.'wd/user/robot.py', 'Zip failed to contain user code!');

$python_ret = shell_exec("cd $s_wd && python user/robot.py");
test_equal($python_ret, $robot_print."\n", 'Running the robot code produced the wrong output.');

section('zip creation failure');

// setup a failing zip maker
$libRobotPath = $test_repos_path.'/libRobot';
cleanCreate($libRobotPath);
$s_libRobotPath = escapeshellarg($libRobotPath);
shell_exec("cd $s_libRobotPath && git init");
$libRobotRepo = GitRepository::GetOrCreate($libRobotPath);
$filePath = $libRobotPath.'/make-zip';
file_put_contents($filePath, "#!/bin/false");
$libRobotRepo->stage('make-zip');
$libRobotRepo->commit('Make make-zip fail', 'John Smith', 'JS@bacon.net');
$failingLibRobotHash = $libRobotRepo->getCurrentRevision();
$config->override('lib_robot.dir', $libRobotPath);
$config->override('lib_robot.archive_script', 'make-zip');
$config->override('lib_robot.team', array(1 => $failingLibRobotHash));

test_false($proj->dispatchCommand('co'), 'export command should fail when zip fails to be created');
