<?php

section('Monitor');

function run($s_timeout) {
	$s_cmd = <<<CMD
import signal
import time

def nope(*args):
    print "got sigterm"
    pass

signal.signal(signal.SIGTERM, nope)

while True:
    time.sleep(0.2)
CMD;
	$start = microtime(true);
	$ret = proc_exec("python -c '$s_cmd'", null, null, array(), true, $s_timeout);
	$dur = microtime(true) - $start;
	$dur = round($dur, 1);
	return array($dur, $ret);
}

function get_child_pids() {
	$s_mypid = getmypid();
	var_dump("pgrep -P '$s_mypid'");
	$children = shell_exec("pgrep -P '$s_mypid'");
	$pids = explode("\n", trim($children));
	var_dump("Child pids: ", $pids);
	return $pids;
}

$child_pids = get_child_pids();
test_equal(count($child_pids), 1, "Should initially only have one child process -- " .
                                  "the one telling us the child processes; got " .
                                  implode(', ', $child_pids) . ".");

subsection('self validation');
$ret = run(1);
var_dump($ret);

$child_pids = get_child_pids();
test_equal(count($child_pids), 2, "Should only have two child processes -- " .
                                  "the bad one and the one telling us the child processes;" .
                                  "  got " . implode(', ', $child_pids) . ".");

$monitor = ProcUtilsMonitor::getInstance();

$monitor->kill();

$child_pids = get_child_pids();
test_equal(count($child_pids), 1, "Should finally only have one child process -- " .
                                  "the one telling us the child processes; got " .
                                  implode(', ', $child_pids) . ".");
