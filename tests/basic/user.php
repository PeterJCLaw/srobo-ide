<?php

$config = Configuration::getInstance();
$config->override('auth_module', 'single');
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('user.default', 'cake');
$config->override('user.default.teams', array(1, 2));

//we have to do an auth before we can start using ui
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees', 'face'), 'authentication failed');

$ui = UserInfo::getInstance();
test_nonnull($ui, "failed to get UserInfo instance");

try
{
	$ui->getInfo('bees');
	test_unreachable('UserInfo->getInfo returned after access of undefined key');
}
catch (Exception $e)
{
	test_equal($e->getCode(), E_INTERNAL_ERROR, 'UserInfo->getInfo threw the wrong exception');
}

test_equal($ui->getInfo('teams'), $auth->getCurrentUserTeams(), "the user was not in the teams the authbackend returned");
