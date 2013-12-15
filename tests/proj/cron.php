<?php

$config = Configuration::getInstance();
$config->override('modules.always', array('proj'));
// while we don't do an auth, the proj module always checks for one
$config->override("keyfile", "$testWorkPath/test.key");
$zipRoot = $testWorkPath . 'zips';
$config->override('zipurl', $zipRoot);
$config->override('zips.max_age', 20);

$team = 'ABC';
$project = 'that-project';
$teamDir = $zipRoot . "/$team";
$projDir = $teamDir . "/$project";

$old_file = "$projDir/old-file.zip";
$new_file = "$projDir/new-file.zip";

function init()
{
	global $projDir, $old_file, $new_file;
	mkdir_full($projDir);
	var_dump($old_file, $new_file);
	$ago = strtotime('-12 hours');
	touch($old_file, $ago, $ago);
	touch($new_file);
}

test_nonexistent($new_file, 'File should not exist before test start');
test_nonexistent($old_file, 'File should not exist before test start');

init();

test_existent($new_file, 'File should exist after test setup');
test_existent($old_file, 'File should exist after test setup');

$output = Output::getInstance();
$input = Input::getInstance();
$input->setInput('team', $team);
$input->setInput('project', $project);

$mm = ModuleManager::getInstance();
$mm->importModules();
$proj = $mm->getModule('proj');

section('basic test');
test_true($proj->cron(), 'Cron call failed');

test_existent($new_file, 'New file should still exist after proj cron');
test_nonexistent($old_file, 'Old file should not exist after proj cron');

section('remove empty folders');
unlink($new_file);
test_true($proj->cron(), 'Cron call failed');

test_nonexistent($projDir, 'Empty project folder should not exist after proj cron');

section('remove old files _and_ then empty folders');
init();
unlink($new_file);
test_true($proj->cron(), 'Cron call failed');

test_nonexistent($old_file, 'Old file should not exist after proj cron 2');
test_nonexistent($projDir, 'Empty project folder should not exist after proj cron 2');
test_nonexistent($teamDir, 'Empty team folder should not exist after proj cron 2');
test_existent($zipRoot, 'Zip root folder should not be removed during proj cron 2');
