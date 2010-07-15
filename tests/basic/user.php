<?php

//we have to do an auth before we can start using ui
$auth = AuthBackend::getInstance();
$next_auth_token = $auth->authUser(array("user"=>"", "password"=>""));
test_equal($next_auth_token, 1, "auth token was not 1");

$ui = UserInfo::getInstance();
test_nonnull($ui, "failed to get UserInfo instance");

try
{
	$ui->getInfo('bees');
	test_unreachable('UserInfo->getInfo returned after access of undefined key');
}
catch (Exception $e)
{
	test_equal($e->getCode(), 4, 'UserInfo->getInfo threw the wrong exception');
}
