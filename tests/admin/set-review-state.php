<?php

$config = Configuration::getInstance();
$config->override("team.status_dir", $testWorkPath.'/status/');
$imagesDir = $testWorkPath.'/images/';
$config->override("team.status_images.dir", $imagesDir);
$liveImagesDir = $testWorkPath.'/live-images/';
$config->override("team.status_images.live_dir", $liveImagesDir);
$config->override("user.default", "bees");
$config->override("user.default.is_admin", true);
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("admin"));

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

section('Image handling');
function grab_image($dest) {
	// copy 'uploaded' image into place
	$source = dirname(__file__).'/../../web/images/static.png';
	$source = realpath($source);
	test_existent($source, "Need a source image to 'upload'");
	mkdir_full(dirname($dest));
	test_true(copy($source, $dest), "Failed to copy '$source' to '$dest'.");
}

$dest = $imagesDir.'ABC.png';
grab_image($dest);
$md5 = md5_file($dest);

$statusABC->setDraft('image', $md5);
$statusABC->save();

$input->setInput('item', 'image');

subsection('image not moved if invalid');
$input->setInput('valid', false);

grab_image($dest);
$md5 = md5_file($dest);
$input->setInput('value', $md5);

test_true(setValue(), 'Command should succeed.');

$live_file = $liveImagesDir.'ABC.png';
$live_thumb = $liveImagesDir.'ABC_thumb.png';
test_nonexistent($live_file, "Should not have been copied invalid image to the live folder");
test_nonexistent($live_thumb, "Should not have created thumbnail of invalid image in the live folder");

subsection('image changed underneath');

grab_image($dest);
$input->setInput('value', 'not-an-md5');

test_exception('setValue', E_MALFORMED_REQUEST, 'Should not allow review of changed image');

test_nonexistent($live_file, "The image should not have been copied to the live folder as the md5sums don't match");
test_nonexistent($live_thumb, "A thumbnail of the image should not have been created in the live folder as the md5sums don't match");

subsection('vaid image moved');
$input->setInput('valid', true);

grab_image($dest);
$md5 = md5_file($dest);
$input->setInput('value', $md5);

test_true(setValue(), 'Command should succeed.');

test_existent($live_file, "The image should have been copied to the live folder");
test_existent($live_thumb, "A thumbnail of the image should have been created in the live folder");
