<?php

section('Timout');

function run($s_timeout) {
	$s_cmd = <<<CMD
import sys
import time
for i in range(5):
    print i
    sys.stdout.flush()
    time.sleep(1)
CMD;
	$start = microtime(true);
	$ret = proc_exec("python -c '$s_cmd'", null, null, array(), true, $s_timeout);
	$dur = microtime(true) - $start;
	$dur = round($dur, 1);
	return array($dur, $ret);
}

$expected_output = "0\n1\n2\n3\n4\n";

subsection('No timeout, should run to completion');
$expected_ret = array(
	'exitcode' => 0,
	'stdout'   => $expected_output,
	'stderr'   => '',
	'success'  => true,
	'timedout' => false,
);
list($dur, $ret) = run(null);
test_equal($ret, $expected_ret, "Wrong return value when no timeout applied.");

subsection('timeout greater than expected duration, should run to completion');
$expected_ret = array(
	'exitcode' => 0,
	'stdout'   => $expected_output,
	'stderr'   => '',
	'success'  => true,
	'timedout' => false,
);
list($dur, $ret) = run(10);
test_between($dur, 4.5, 6, "Should have returned immediately after process completed.");
test_equal($ret, $expected_ret, "Wrong return value when timeout much greater than expected run-time.");

subsection('2s timeout, should be killed');
$expected_ret = array(
	'exitcode' => null,
	'stdout'   => "0\n1\n2\n",
	'stderr'   => '',
	'success'  => false,
	'timedout' => true,
);
list($dur, $ret) = run(2.5);
test_equal($dur, 2.5, "Should have returned after expected timeout.");
test_equal($ret, $expected_ret, "Wrong return value when timeout reached.");
