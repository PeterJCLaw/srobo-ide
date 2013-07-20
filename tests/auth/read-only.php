<?php

//override the configuration
$config = Configuration::getInstance();
$config->override('repopath', $testWorkPath);
$config->override('user.default', 'death');
$config->override('user.default.teams', array('ABC'));
// the read-only teams will be a collection of all teams.
$config->override('user.default.read_only_teams', array('ROT', 'ABC'));
$config->override('auth_module', 'single');
$config->override('modules', array('user', 'file', 'proj'));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees', 'face'), 'authentication failed');

test_equal($auth->getCurrentUserTeams(), array('ABC', 'ROT'), "Wrong collection of read-only teams");

test_true($auth->canCurrentUserWriteTeam('ABC'), "Must be able to write to ordinary team.");
test_false($auth->canCurrentUserWriteTeam('ROT'), "Must not be able to write to read-only team.");

$expectedUserTeams = array(
	array('id' => 'ABC', 'name' => '', 'readOnly' => false),
	array('id' => 'ROT', 'name' => '', 'readOnly' => true)
);

function getModule($modName) {
	$mm = ModuleManager::getInstance();
	$mm->importModules();
	test_true($mm->moduleExists($modName), "'$modName' module does not exist");
	$mod = $mm->getModule($modName);
	test_nonnull($mod, "Failed to get '$modName' module.");
	return $mod;
}

$input = Input::getInstance();
$output = Output::getInstance();

$userModule = getModule('user');

test_true($userModule->dispatchCommand('info'), 'Failed to get user info');
test_nonnull($userTeams = $output->getOutput('teams'), 'Failed to get user team info');

test_equal($userTeams, $expectedUserTeams, "Wrong collection of read-only teams");
