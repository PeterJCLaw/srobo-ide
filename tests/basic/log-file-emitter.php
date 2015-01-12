<?php

function check($emitter, $dest)
{
	$msg = "bacon";
	$emitter->emit(LOG_WARNING, $msg);

	test_existent($dest, "Should create file after first line is emitted");

	$content = file_get_contents($dest);
	test_equal($content, $msg . PHP_EOL, "Wrong content was output");

	$msg2 = "cheese";
	$emitter->emit(LOG_WARNING, $msg2);

	$content = file_get_contents($dest);
	test_equal($content, $msg . PHP_EOL . $msg2 . PHP_EOL, "Wrong second content was output");
}

$dest = $testWorkPath . "out.log";
section("Creates File");
$emitter = new FileEmitter($dest);
test_nonexistent($dest, "Should not create file up front");
check($emitter, $dest);

$dest2 = $testWorkPath . "out2.log";
touch($dest2);
section("Uses Existing File");
$emitter = new FileEmitter($dest2);
check($emitter, $dest2);
