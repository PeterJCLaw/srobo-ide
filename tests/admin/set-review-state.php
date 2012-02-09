<?php

$config = Configuration::getInstance();
$config->override("team.status_dir", $testWorkPath.'/status/');
$config->override("team.status_images.dir", $testWorkPath.'/images/');
$config->override("user.default", "bees");
$config->override("user.default.is_admin", true);
$config->override("auth_module", "single");
$config->override("modules", array("admin"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 'ABC');

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("admin"), true, "admin module does not exist");

function setValue() {
	$admin = ModuleManager::getInstance()->getModule('admin');
	return $admin->dispatchCommand('review-item-set');
}

$property = 'bacon';
$propertyValue = 'jam';

$input->setInput('item', $property);
$input->setInput('value', $propertyValue);

$statusABC = new TeamStatus('ABC');

section('set true');
$statusABC->setDraft($property, $propertyValue);
$statusABC->save();

$input->setInput('valid', true);
test_true(setValue(), 'Command should succeed.');

section('set false');
// NB: this currently relies on us overwriting the value already in the file from the previous section.
$statusABC->setDraft($property, $propertyValue);
$statusABC->save();

$input->setInput('valid', false);
test_true(setValue(), 'Command should succeed.');

section('value mismatch');
$input->setInput('value', 'cheese'.$propertyValue);
test_exception('setValue', E_MALFORMED_REQUEST, 'Should not allow review of mismatched draft');

section('non-existent field');
$input->setInput('item', 'cheese');
test_exception('setValue', E_MALFORMED_REQUEST, 'Should not allow review of non-existent field');

section('images not reviewed through IDE');
$statusABC->setDraft('image', $propertyValue);
$statusABC->save();

$input->setInput('item', 'image');
$input->setInput('value', $propertyValue);
test_exception('setValue', E_MALFORMED_REQUEST, 'Should not allow review of images');
