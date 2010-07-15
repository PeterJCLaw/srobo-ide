<?php

require_once('include/main.php');

function __test($cond, $message)
{
	if ($cond)
		return;
	$bt = debug_backtrace(false);
	array_shift($bt);
	$frame = array_shift($bt);
	$line = $frame['line'];
	echo "Test failed on line $line: $message\n";
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
	__test($a == $b, $message . " (expected $b, got $a)");
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
