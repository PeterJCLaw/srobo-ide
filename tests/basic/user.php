<?php

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
