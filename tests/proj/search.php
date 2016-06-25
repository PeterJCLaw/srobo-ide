<?php

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array('ABC'));
$config->override("auth_module", "single");
$config->override("keyfile", "$testWorkPath/test.key");
$config->override('modules.always', array("proj"));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");
$projName = 'monkies';

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository('ABC', $projName);
$beesRepo = $projectManager->getUserRepository('ABC', $projName, 'bees');

// Create some content to search
section('Setup: create content to be searched');
$committed_1 = "I'm in";
$committed_2 = "the new place";
$committed_3 = "I should be.";
$beesRepo->putFile("committed.py", "$committed_1\n$committed_2\n$committed_3\n");

$beesRepo->stage('committed.py');
test_true($beesRepo->commit('Commit msg', 'No one', 'nemo@srobo.org'), 'Failed basic commit');
$beesRepo->push();

//setup the required input keys
$output = Output::getInstance();
$input = Input::getInstance();
$input->setInput("team", 'ABC');
$input->setInput("project", "monkies");

$mm = ModuleManager::getInstance();
$mm->importModules();

function search($query, $expectedResults) {
	$mm = ModuleManager::getInstance();
	$proj = $mm->getModule("proj");
	$output = Output::getInstance();
	$input = Input::getInstance();

	$input->setInput("query", $query);
	test_true($proj->dispatchCommand('search'), 'Search failed');
	$results = $output->getOutput('results');
	test_equal($results, $expectedResults, "A search for: $query.");
}

section('Plaintext searches');
$input->setInput("regex", false);

search('nothing', array());

$e_committed = array(
	array('line' => 2, 'text' => $committed_2)
);
search('new', array(
	"/$projName/committed.py" => $e_committed,
));

// literal dot
$e_committed = array(
	array('line' => 3, 'text' => $committed_3)
);
search('.', array(
	"/$projName/committed.py" => $e_committed,
));

// TODO: Add support for JavaScript-like regex.
return;
section('Regex searches');
$input->setInput("regex", true);

// escaped dot
search('\.', array(
	"/$projName/committed.py" => $e_committed,
));

$e_committed = array(
	array('line' => 2, 'text' => $committed_2)
);
search('s.*\d', array(
	"/$projName/committed.py" => $e_committed
));
