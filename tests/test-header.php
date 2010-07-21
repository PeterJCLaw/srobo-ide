<?php

require_once('include/main.php');

function __test_value($stuff)
{
	if (is_bool($stuff))
		return $stuff ? 'true' : 'false';
	if (is_integer($stuff) ||
	    is_float($stuff))
		return $stuff;
	if (is_string($stuff))
		return "'$stuff'";
	ob_start();
	var_dump($stuff);
	$stuff = ob_get_contents();
	ob_end_clean();
	return trim($stuff);
}

function __test($cond, $message, $frame = 1, $file = false)
{
	if ($cond)
		return;
	$bt = debug_backtrace(false);
	$frame = $bt[$frame];
	$line = $frame['line'];
	if ($file)
	{
		echo "Test failed in " . $frame['file'] . " on line $line: $message\n";
	}
	else
	{
		echo "Test failed on line $line: $message\n";
	}
	exit(1);
}

function test_abort_exception($exception)
{
	echo "Exception '" . $exception->getMessage() . "' (" . $exception->getCode() . ")\n";
	echo "\tthrown in " . $exception->getFile() . " line " . $exception->getLine() . "\n";
	echo $exception->getTraceAsString();
	exit(1);
}

function test_unreachable($message)
{
	__test(false, $message);
}

function test_true($cond, $message)
{
	__test($cond, $message);
}

function test_false($cond, $message)
{
	__test(!$cond, $message);
}

function test_empty($a, $message)
{
	__test(empty($a), $message);
}

function test_nonempty($a, $message)
{
	__test(!empty($a), $message);
}

function test_null($a, $message)
{
	__test($a === null, $message);
}

function test_nonnull($a, $message)
{
	__test($a !== null, $message);
}

function test_equal($a, $b, $message)
{
	__test($a == $b, $message . " (expected " . __test_value($b) . ", got " . __test_value($a) . ")");
}

function test_nonequal($a, $b, $message)
{
	__test($a != $b, $message);
}

function test_type($a, $t, $message)
{
	$type = gettype($a);
	__test($type == $t, $message . " (expected $t, got $type)");
}

function test_class($a, $c, $message)
{
	$class = get_class($a);
	__test($class == $c, $message . " (expected $c, got $class)");
}

function test_exception($callback, $code, $message)
{
	try
	{
		call_user_func($callback);
		__test(false, $message . " (did not throw)");
	}
	catch (Exception $e)
	{
		$rcode = $e->getCode();
		__test($rcode == $code, $message . " (wrong code, expected $code, got $rcode)");
	}
}

error_reporting(E_ALL | E_STRICT);

function __error_handler($errno, $errstr)
{
	__test($errno != 0, "PHP error: $errstr", 2, true);
}

set_error_handler('__error_handler');

function skip_test()
{
	echo "___SKIP_TEST";
	exit(0);
}
