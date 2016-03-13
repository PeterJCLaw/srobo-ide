<?php

//override the configuration
$config = Configuration::getInstance();
$config->override('repopath', $testWorkPath);
$config->override('user.default', 'death');
$config->override('user.default.teams', array('ABC'));
// the read-only teams will be a collection of all teams.
$config->override('user.default.read_only_teams', array('ROT', 'ABC'));
$config->override('auth_module', 'single');
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('team.status_dir', "$testWorkPath/status");
$config->override('modules.always', array('user', 'file', 'proj', 'team'));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees', 'face'), 'authentication failed');

section('AuthBackend');
test_equal($auth->getCurrentUserTeams(), array('ABC', 'ROT'), "Wrong collection of read-only teams");

test_true($auth->canCurrentUserWriteTeam('ABC'), "Must be able to write to ordinary team.");
test_false($auth->canCurrentUserWriteTeam('ROT'), "Must not be able to write to read-only team.");

subsection('Write access helper');
AuthBackend::ensureWrite('ABC');

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

section('User Module');
$userModule = getModule('user');

test_true($userModule->dispatchCommand('info'), 'Failed to get user info');
test_nonnull($userTeams = $output->getOutput('teams'), 'Failed to get user team info');

test_equal($userTeams, $expectedUserTeams, "Wrong collection of read-only teams");

function checkEndpointDenied($moduleName, $endpoint) {
	$module = getModule($moduleName);
	$call = function() use ($module, $endpoint) {
		$module->dispatchCommand($endpoint);
	};
	test_exception($call, E_PERM_DENIED, "Must not be able to write information into '$moduleName/$endpoint'.");
}

section('Team Module');
$input->setInput('team', 'ROT');
checkEndpointDenied('team', 'status-put');
checkEndpointDenied('team', 'status-put-image');

section('Proj Module');
$input->setInput('project', "doesn't exist");
checkEndpointDenied('proj', 'new');
checkEndpointDenied('proj', 'del');
checkEndpointDenied('proj', 'commit');
checkEndpointDenied('proj', "copy");

section('File Module');
checkEndpointDenied('file', 'put');
checkEndpointDenied('file', 'del');
checkEndpointDenied('file', 'cp');
checkEndpointDenied('file', 'mv');
// checkEndpointDenied('file', 'diff'); // doesn't make sense, but not actually a write action.
checkEndpointDenied('file', 'mkdir');
