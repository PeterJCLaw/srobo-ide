<?php

$config = Configuration::getInstance();
$config->override('modules.always', array('multi'));
$config->override('modules.lazy', array('fake'));

$mm = ModuleManager::getInstance();
$mm->importModules();

// Hack the import path so that we can use our fake module.
$dir = dirname(__file__);
chdir($dir);

$multi = $mm->getModule('multi');
$fake = $mm->getModule('fake');

$input = Input::getInstance();
$output = Output::getInstance();

$fake->setHandler(function($cmd) use ($input, $output, $wasCalled) {
	var_dump($cmd);
	$a = $input->getInput('a', true);
	$b = $input->getInput('b', true);
	$c = $input->getInput('c', true);

	$out = $output->encodeOutput();
	test_equal($out, "{}", "Output should be empty to start with");

	$output->setOutput('my-cmd', $cmd);

	subsection('Check request module & command in the Input');
	$rqMod = $input->getRequestModule();
	test_equal($rqMod, 'multi:fake', 'Requested module');
	$rqCmd = $input->getRequestCommand();
	test_equal($rqCmd, $cmd, 'Requested module');

	subsection('Check command-specific Inputs');
	switch($cmd)
	{
		case 'first':
		{
			test_true($a, "Input 'a' in the $cmd command");
			test_false($b, "Input 'b' in the $cmd command");
			test_null($c, "Input 'c' in the $cmd command");
			$output->setOutput('bees', 'something');
			break;
		}
		case 'no-op':
		{
			test_null($a, "Input 'a' in the $cmd command");
			test_null($b, "Input 'b' in the $cmd command");
			test_null($c, "Input 'c' in the $cmd command");
			break;
		}
		case 'second':
		{
			test_null($a, "Input 'a' in the $cmd command");
			test_true($b, "Input 'b' in the $cmd command");
			test_false($c, "Input 'c' in the $cmd command");
			$output->setOutput('cheese', 'something-else');
			break;
		}
		default:
		{
			test_unreachable("Unexpected command '$cmd' dispatched");
		}
	}
});

$input->setInput('a', 'a');
$input->setInput('b', 'b');
$input->setInput('c', 'c');

$input->setInput('commands', array(
	array('cmd' => 'fake/first',
	      'data' => array('a' => true, 'b' => false)),
	array('cmd' => 'fake/no-op'),
	array('cmd' => 'fake/second',
	      'data' => array('b' => true, 'c' => false))
));

section('Dispatch Command');
test_true($multi->dispatchCommand('independent'), "Failed to dispatch command multi/independent");

section('Check commands were executed');
$subCommandsDispatched = $fake->getCommands();
$expectedCommands = array('first', 'no-op', 'second');
test_equal($subCommandsDispatched, $expectedCommands, "Wrong sub-commands dispatched");

section('Check overall output');
function checkFakeOutput($cmd, $expected = array()) {
	$output = Output::getInstance();
	$actual = $output->getOutput("fake/$cmd");
	$expected['my-cmd'] = $cmd;
	test_equal($expected, $actual, "Wrong output for $cmd command");
}

checkFakeOutput('first', array('bees' => 'something'));
checkFakeOutput('no-op');
checkFakeOutput('second', array('cheese' => 'something-else'));
