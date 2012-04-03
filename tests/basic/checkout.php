<?php

$config = Configuration::getInstance();

$test_repos_path = $testWorkPath.'test-repos';
cleanCreate($test_repos_path);

$test_zip_path = $testWorkPath.'ide-test-zip';
cleanCreate($test_zip_path);

// Create a repo with a couple of commits in it.
section('Setup');

subsection('Setup: User Code');

$projectName = 'team-repo';

$teamRepo = GitRepository::createRepository($test_repos_path.'/'.$projectName);
$filePath = $teamRepo->workingPath().'/robot.py';

$robotData1 = "print 'I am a robot.'";
file_put_contents($filePath, $robotData1);

$teamRepo->stage('robot.py');
$teamRepo->commit('beans', 'John Smith', 'JS@bees.net');
$hash1 = $teamRepo->getCurrentRevision();

$robotData2 = "print 'I am not a robot.'";
file_put_contents($filePath, $robotData2);

$teamRepo->stage('robot.py');
$teamRepo->commit('beans 2', 'John Smith', 'JS@bees.net');
$hash2 = $teamRepo->getCurrentRevision();

subsection('Setup: LibRobot');

$libRobotPath = $test_repos_path.'/libRobot';
cleanCreate($libRobotPath);

$config->override('lib_robot.dir', $libRobotPath);
$config->override('lib_robot.archive_script', 'make-zip');

$s_libRobotPath = escapeshellarg($libRobotPath);
shell_exec("cd $s_libRobotPath && git init");
$libRobotRepo = GitRepository::GetOrCreate($libRobotPath);
$filePath = $libRobotPath.'/make-zip';

// our local dummy zip maker -- makes this test not reliant on pyenv.
// usage: $script USER_CODE_DIR OUTPUT_ARCHIVE
$makeZipData = <<<MAKEZIP
#!/bin/sh
echo $0 $1 $2
cd `dirname $0`     # TODO: remove this requirement -- we should run from the right folder
cp -r $1 user
zip -r $2 *
MAKEZIP;
file_put_contents($filePath, $makeZipData.PHP_EOL);
chmod($filePath, 0755);

$libRobotRepo->stage('make-zip');
$libRobotRepo->touchFile('a-file', time());
$libRobotRepo->stage('a-file');
$libRobotRepo->commit('Add make-zip', 'John Smith', 'JS@bacon.net');
$oldLibRobotHash = $libRobotRepo->getCurrentRevision();

$libRobotRepo->touchFile('b-file', time());
$libRobotRepo->stage('b-file');
$libRobotRepo->commit('Add another file', 'John Smith', 'JS@bacon.net');
$newLibRobotHash = $libRobotRepo->getCurrentRevision();

// Some useful functions

function assertZippedFileContent($zip, $filePath, $content, $message)
{
	test_true($zip->locateName($filePath) !== false, "Failed to find file '$filePath' in the archive.");

	$actualContent = $zip->getFromName($filePath);
	test_equal($actualContent, $content, "File '$filePath' contained wrong content: $message.");
}

function validateZip($zipPath, $project, $hash, $robotData)
{
	test_existent($zipPath, "Zip should exist at target location after creating it.");

	$zip = new ZipArchive;
	test_true($zip->open($zipPath), "Failed to open the created zip.");

	assertZippedFileContent($zip, 'user/.user-rev', $project.' @ '.$hash, 'user code revision');
	assertZippedFileContent($zip, 'user/robot.py', $robotData, 'user code');

	return $zip;
}

section('basic test');

$config->override('lib_robot.team', array());

$helper = new CheckoutHelper($teamRepo, 'ABC');

$zipPath = $test_zip_path.'/robot.zip';
$helper->buildZipFile($zipPath, $hash1);
validateZip($zipPath, $projectName, $hash1, $robotData1);

$zipPath2 = $test_zip_path.'/robot2.zip';
$helper->buildZipFile($zipPath2, $hash2);
validateZip($zipPath2, $projectName, $hash2, $robotData2);

section('per-team libRobot testing');

cleanCreate($test_zip_path);

subsection('old revision');
$config->override('lib_robot.team', array('ABC' => $oldLibRobotHash));

$helper = new CheckoutHelper($teamRepo, 'ABC');

$zipPath = $test_zip_path.'/per-team-libRobot-test.zip';
$helper->buildZipFile($zipPath, $hash1);

$zip = validateZip($zipPath, $projectName, $hash1, $robotData1);
// validate that this the old revision by ensuring that the second file isn't there.
test_true($zip->locateName('b-file') === false, "Should not find second file 'b-file' in the archive when it's based on the older libRobot.");

subsection('non-existent revision');

$config->override('lib_robot.team', array('ABC' => 'bacon'));

$helper = new CheckoutHelper($teamRepo, 'ABC');

$zipPath = $test_zip_path.'/per-team-libRobot-bad-rev.zip';
$helper->buildZipFile($zipPath, $hash1);

$zip = validateZip($zipPath, $projectName, $hash1, $robotData1);
// validate that this the old revision by ensuring that the second file isn't there.
test_true($zip->locateName('b-file') !== false, "Failed to find file 'b-file' in the archive -- bad revisions should fall back to the default.");
