<?php

$input = Input::getInstance();
$output = Output::getInstance();
$config = Configuration::getInstance();

function cleanCreate($path) {
	delete_recursive($path);
	mkdir_full($path);
}

section('setup');
cleanCreate('/tmp/proj-export/wd');
cleanCreate('/tmp/proj-export/test-repos');
$test_zip_path = '/tmp/proj-export/ide-test-zip';
cleanCreate($test_zip_path);

// remove the folder so that we can test the failure mode
$zipPathBase = '/tmp/proj-export/ide-zips';
delete_recursive($zipPathBase);

$config->override('repopath', '/tmp/proj-export/test-repos');

$config->override('zippath', $zipPathBase);
$config->override('zipurl', $zipPathBase);
$config->override('user.default', 'death');
$config->override('user.default.teams', array(1, 2));
$config->override('auth_module', 'single');
$config->override('modules', array('proj', 'file'));

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
test_false($proj->dispatchCommand('co'), 'export command should have failed when export folder missing');
// remove our get-in-the-way file
unlink($zipPathBase);
test_false(file_exists($zipPathBase), "$zipPathBase Must not exist after failure mode testing complete.");

section('Success testing');
test_true($proj->dispatchCommand('co'), 'export command should succeed');

$zip_path = $output->getOutput('url');
var_dump($zip_path);
test_true(file_exists($zip_path), "Zip doesn't exist at '$zip_path'.");
test_true(rename($zip_path, '/tmp/proj-export/wd/foo.zip'), "Failed to rename the zip from '$zip_path'.");
shell_exec('cd /tmp/proj-export/wd/ && unzip foo.zip');

$python_ret = shell_exec('cd /tmp/proj-export/wd/ && python user/robot.py');
test_equal($python_ret, $robot_print."\n", 'Running the robot code produced the wrong output.');
