<?php

$config = Configuration::getInstance();
$config->override('modules.always', array('cron'));

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();

$cron = $mm->getModule('cron');

section('basic test');
$cron->subscribe('test', function() {
	Output::getInstance()->setOutput('some-value', 'test');
	return true;
});

$result = $cron->dispatchCommand('cron');

$some_value = $output->getOutput('some-value');
test_equal($some_value, 'test', "Should have called the registered callback");
test_true($result, "Cron dispatch should have been successful");
$output_result = $output->getOutput('summary');
test_true($output_result, "Cron should have been successful overall");

// remove the handler
$cron->remove('test');

section('result handling');
$cron->subscribe('test-exception', function() {
	throw new Exception('You are not logged in', E_PERM_DENIED);
});

$cron->subscribe('test-false', function() {
	return false;
});

$cron->subscribe('test-true', function() {
	return true;
});

$result = $cron->dispatchCommand('cron');
test_true($result, "Cron dispatch should always succeed");
$output_result = $output->getOutput('summary');
test_false($output_result, "Cron should have failed overall");

$detail = $output->getOutput('detail');
$expected = array(
	'test-false' => array('result' => false),
	'test-true' => array('result' => true)
);
test_equal($detail['test-false'], $expected['test-false'], "Should have recorded correct info about test-false in the detail output");
test_equal($detail['test-true'], $expected['test-true'], "Should have recorded correct info about test-true in the detail output");

$exception_detail = $detail['test-exception'];
var_dump($exception_detail);
test_false($exception_detail['result'], "Should have recorded correct info about test-exception:result in the detail output");
$error_detail = $exception_detail['error'];
test_equal($error_detail[0], E_PERM_DENIED, "Should have recorded correct info about test-exception:error:code in the detail output");
test_equal($error_detail[1], 'You are not logged in', "Should have recorded correct info about test-exception:error:message in the detail output");
test_nonempty($error_detail[2], "Should have recorded some info about test-exception:error:stack-trace in the detail output");
