<?php

$input = Input::getInstance();
$config = Configuration::getInstance();

cleanCreate($testWorkPath.'wd');
cleanCreate($testWorkPath.'test-repos');
$test_zip_path = $testWorkPath.'ide-test-zip';
cleanCreate($test_zip_path);

// remove the folder so that we can test the failure mode
$zipPathBase = $testWorkPath.'ide-zips';
test_nonexistent($zipPathBase, "before failure mode testing");

$config->override('repopath', $testWorkPath.'test-repos');

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
$input->setInput('rev', 'HEAD');

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists('proj'), 'proj module does not exist');
$proj = $mm->getModule('proj');

function createAndExportProject($name)
{
	section("Test exporting a project called '$name'");
	$output = Output::getInstance();
	$input = Input::getInstance();
	$input->setInput('project', $name);

	// need to do this each time since the modules are single-shot
	// that is, they only get one Input per instance, and may cache what it has to say.
	$mm = ModuleManager::getInstance();
	$mm->importModules();

	subsection('create');
	$proj = $mm->getModule('proj');
	test_true($proj->dispatchCommand('new'), "Failed to create project '$name'");

	subsection('export');
	test_true($proj->dispatchCommand('co'), "Failed to export project '$name'");
	$zip_path = $output->getOutput('url');
	var_dump($zip_path);
	test_false(strpos($zip_path, '?'), 'Zip path must not contain \'?\' to be exported successfully');
	$parsed = parse_url($zip_path);
	$path = rawurldecode($parsed['path']);
	test_existent($path, "Zip path must survive being parsed by a webserver, and then exist. Original: '$zip_path', Parsed: '$path'.");
	// TODO: actually try http-GET-ing the file?
}

// samples based on issue #1002
$names = array('beans?', 'bacon?spam&jam', 'spam&jam');
foreach ($names as $name)
{
	createAndExportProject($name);
}

// copied from proj/create. TODO: make this a common list
$chars = '$%@~{}][()';
for($i=0; $i < strlen($chars); $i++)
{
	createAndExportProject('char \''.$chars[$i].'\'.');
}

// copied from proj/create. TODO: make this a common list
$unicodes = array('£', '❝', '♞');
foreach($unicodes as $char)
{
	createAndExportProject('char \''.$char.'\'.');
}
