<?php

require_once('tests/test-header.php');
try
{
	$testWorkPath = getenv('ide-test-work-path');
	require_once($argv[1]);
}
catch (Exception $e)
{
	test_abort_exception($e);
}
