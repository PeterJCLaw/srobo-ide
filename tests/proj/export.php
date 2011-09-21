<?php

$input = Input::getInstance();
$output = Output::getInstance();
$config = Configuration::getInstance();

function cleanCreate($path) {
	delete_recursive($path);
	mkdir_full($path);
}

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
$config->override('pyenv_zip', "$test_zip_path/pyenv.zip");

$pyenv_bees_contents = "test string\n";
file_put_contents($test_zip_path . '/bees', $pyenv_bees_contents);
shell_exec("cd $test_zip_path && zip pyenv.zip *");

$auth = AuthBackend::getInstance();
test_true($auth->authUser('death','face'), 'authentication failed');

$input->setInput('team', 1);
$input->setInput('project', 'ponies');

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists('proj'), 'proj module does not exist');
$proj = $mm->getModule('proj');
test_true($proj->dispatchCommand('new'), 'failed to create project');


// put
$robot_print = 'llama';
$robot_data = "def main():\n	print '$robot_print'\n";
$input->setInput('path', 'robot.py');
$input->setInput('data', $robot_data);
test_true($mm->moduleExists('file'), 'file module does not exist');
$file = $mm->getModule('file');
test_true($file->dispatchCommand('put'), 'put command failed');

// commit
$input->setInput('message', 'give robot some data');
$input->setInput('paths', array('robot.py'));
test_true($proj->dispatchCommand('commit'), 'commit command failed');

// co
$input->setInput('rev', 'HEAD');
// create a file where it's going to try to put a folder.
// we can't actually create the situation where the webserver doesn't have write access,
// since we're running as ourselves during the tests.
touch($zipPathBase);
test_false($proj->dispatchCommand('co'), 'export command should have failed when export folder missing');
// remove our get-in-the-way file
unlink($zipPathBase);
test_false(file_exists($zipPathBase), "$zipPathBase Must not exist after failure mode testing complete.");

test_true($proj->dispatchCommand('co'), 'export command should succeed');

$zip_path = $output->getOutput('url');
var_dump($zip_path);
test_true(file_exists($zip_path), "Zip doesn't exist at '$zip_path'.");
test_true(rename($zip_path, '/tmp/proj-export/wd/foo.zip'), "Failed to rename the zip from '$zip_path'.");
shell_exec('cd /tmp/proj-export/wd/ && unzip foo.zip');
$beesPath = '/tmp/proj-export/wd/bees';
test_true(file_exists($beesPath), "Bees ('pyenv' file) doesn't exist at '$beesPath' after unzip.");
$s = file_get_contents($beesPath);
test_equal($s, $pyenv_bees_contents, 'File from outer (pyenv) zip did not match expected file.');

$python = <<<PYTHON
import os, sys
if os.path.exists( "robot.zip" ):
	# robot.zip exists, everyone's happy
	sys.path.insert(0, os.path.join(os.curdir, "robot.zip"))
else:
	raise Exception( "No robot code found." )
import robot
robot.main()
PYTHON;
$python_ret = shell_exec('cd /tmp/proj-export/wd/ && python -c '.escapeshellarg($python));
test_equal($robot_print."\n", $python_ret, 'Running the robot code produced the wrong output.');