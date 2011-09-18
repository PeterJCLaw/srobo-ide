<?php

require_once('modules/auth-required.php');

class TestModule extends AuthRequiredModule
{
	private $bodyRun = false;

	public function __construct()
	{
		$cb = array($this, 'body');
		$this->installCommandAuth('user', $cb);
		$this->installCommandTeam('team', $cb, 3);
		$this->installCommandAdmin('admin', $cb);
	}

	public function body()
	{
		echo "BODY", PHP_EOL;
		$this->bodyRun = true;
		return true;
	}

	public function resetBodyRun()
	{
		$this->bodyRun = false;
	}

	public function didBodyRun()
	{
		return $this->bodyRun;
	}
}

$config = Configuration::getInstance();
$config->override('auth_module', 'single');
$config->override('user.default', 'cake');
$config->override('user.default.teams', array(1, 2));
$config->override('user.default.is_admin', false);

$auth = AuthBackend::getInstance();

$mm = ModuleManager::getInstance();
$mm->addModule('test', new TestModule());
test_true($mm->moduleExists('test'), "test module was not found");
$module = $mm->getModule('test');

function assertAuthFail($module, $name) {
	test_exception(function() use ($module, $name) {
			test_true($module->dispatchCommand($name), "Failed to dispatch the command ($name) under test!");
		}, E_AUTH_REQUIRED, "Should refuse access when not logged in, requesting: $name");
}

function login() {
	AuthBackend::getInstance()->authUser('cake', 'bees');
}

$section = 'user';
section($section);

assertAuthFail($module, $section);

login();

$module->resetBodyRun();
$r = $module->dispatchCommand($section);
test_true($module->didBodyRun(), 'Failed to actually dispatch the call!');


$section = 'team';
section($section);

$auth->deauthUser();

assertAuthFail($module, $section);

login();

test_exception(function() use ($module, $section) {
		$module->dispatchCommand($section);
	}, E_PERM_DENIED, "Should refuse access when not in team!");

$config->override('user.default.teams', array(1, 3));

// refresh the teams by logging in again
login();
var_dump($auth->getCurrentUserTeams());
$module->resetBodyRun();
$module->dispatchCommand($section);
test_true($module->didBodyRun(), 'Failed to actually dispatch the call!');


$section = 'admin';
section($section);

$auth->deauthUser();

assertAuthFail($module, $section);

login();

test_exception(function() use ($module, $section) {
		$module->dispatchCommand($section);
	}, E_PERM_DENIED, "Should refuse access when not an admin!");

$config->override('user.default.is_admin', true);

$module->resetBodyRun();
$module->dispatchCommand($section);
test_true($module->didBodyRun(), 'Failed to actually dispatch the call!');


