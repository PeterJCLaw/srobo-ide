<?php

$config = Configuration::getInstance();
$config->override("team.status_dir", $testWorkPath.'/status/');
$config->override("team.status_images.dir", $testWorkPath.'/images/');
$config->override("user.default", "bees");
$config->override("user.default.is_admin", false);
$config->override("auth_module", "single");
$config->override('modules.always', array("admin"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

$mm = ModuleManager::getInstance();
$mm->importModules();
test_equal($mm->moduleExists("admin"), true, "admin module does not exist");

$admin = $mm->getModule('admin');

section('reject non-admins');
$commands = array('review-teams-get', 'review-items-get', 'review-item-set');
foreach ($commands as $command)
{
	test_exception(function() use ($admin, $command) {
		$admin->dispatchCommand($command);
	}, E_PERM_DENIED, "Should reject non-admin trying to '$command'.");
}
