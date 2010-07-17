<?php

$config = Configuration::getInstance();
$config->override('auth_module', 'single');
$config->override('user.default', 'cake');
$config->override('user.default.groups', array('team1', 'team2'));

//we have to do an auth before we can start using ui
$auth = AuthBackend::getInstance();
test_true($auth->authUser('', ''), 'authentication failed');

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

test_equal($ui->getInfo('groups'), $auth->getCurrentUserGroups(), "the user was not in the groups the authbackend returned");
