<?php

require_once('tests/test-header.php');
try
{
	$testWorkPath = getenv('IDE_TEST_WORK_PATH');
	require_once($argv[1]);
}
catch (Exception $e)
{
	test_abort_exception($e);
}
