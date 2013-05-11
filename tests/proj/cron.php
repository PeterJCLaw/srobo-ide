<?php

$config = Configuration::getInstance();
$config->override('modules', array('proj'));
$zipRoot = $testWorkPath . 'zips';
$config->override('zipurl', $zipRoot);
$config->override('zips.max_age', 20);

$team = 'ABC';
$project = 'that-project';
$projDir = $zipRoot . "/$team/$project";
mkdir_full($projDir);

$old_file = "$projDir/old-file.zip";
$new_file = "$projDir/new-file.zip";

function init()
{
	global $old_file, $new_file;
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
$result = $proj->cron();

test_existent($new_file, 'New file should still exist after proj cron');
test_nonexistent($old_file, 'Old file should not exist after proj cron');
