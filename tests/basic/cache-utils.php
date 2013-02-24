<?php

// only used on the index page, so not generally included
require_once('include/cache-utils.php');

$inputs[] = $testWorkPath.'/input1';
$inputs[] = $testWorkPath.'/input2';
$inputs[] = $testWorkPath.'/input3';
$inputs[] = $testWorkPath.'/input4';

$outputs[] = $testWorkPath.'/output1';
$outputs[] = $testWorkPath.'/output2';

function touch_all($files, $when)
{
	foreach ($files as $file)
	{
		echo "touch($file, $when)\n";
		touch($file, $when);
	}
}

define('HISTORIC_TIME', 0);
define('RECENT_TIME', strtotime('yesterday'));
define('FUTURE_TIME', strtotime('tomorrow'));

echo 'HISTORIC_TIME: ', HISTORIC_TIME, PHP_EOL;
echo 'RECENT_TIME: ', RECENT_TIME, PHP_EOL;
echo 'FUTURE_TIME: ', FUTURE_TIME, PHP_EOL;

function reset_times()
{
	global $inputs, $outputs;
	touch_all($inputs, HISTORIC_TIME);
	touch_all($outputs, HISTORIC_TIME);
}

// Create all the inputs.
touch_all($inputs, HISTORIC_TIME);

section('outputs missing');
$up_to_date = up_to_date($inputs, $outputs);
test_false($up_to_date, "Should not be up to date when outputs missing");

section('outputs up to date');
touch_all($outputs, RECENT_TIME);
$up_to_date = up_to_date($inputs, $outputs);
test_true($up_to_date, "Should be up to date after outputs created");

reset_times();

section('outputs outdated');
subsection('all inputs modified');
touch_all($inputs, RECENT_TIME);
$up_to_date = up_to_date($inputs, $outputs);
test_false($up_to_date, "Should not be up to date after all inputs modified");

reset_times();

subsection('only one input modified');
touch_all($outputs, RECENT_TIME);
touch($inputs[0], FUTURE_TIME);
$up_to_date = up_to_date($inputs, $outputs);
test_false($up_to_date, "Should not be up to date after one input modified again");
