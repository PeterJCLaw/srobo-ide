<?php

//override the configuration
$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array('ABC'));
$config->override("auth_module", "single");
$config->override("modules", array("proj"));

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
$beesRepo->putFile("changed.py", "original content\n");

$beesRepo->stage('committed.py');
$beesRepo->stage('changed.py');
test_true($beesRepo->commit('Commit msg', 'No one', 'nemo@srobo.org'), 'Failed basic commit');

$changed_1 = "This changed file";
$changed_2 = "contains 3 lines of";
$changed_3 = "new content.";
$beesRepo->putFile("changed.py", "$changed_1\n$changed_2\n$changed_3\n");

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
$e_changed = array(
	array('line' => 3, 'text' => $changed_3)
);
search('new', array(
	"/$projName/committed.py" => $e_committed,
	"/$projName/changed.py" => $e_changed
));

// literal dot
$e_committed = array(
	array('line' => 3, 'text' => $committed_3)
);
$e_changed = array(
	array('line' => 3, 'text' => $changed_3)
);
search('.', array(
	"/$projName/committed.py" => $e_committed,
	"/$projName/changed.py" => $e_changed
));

// TODO: Add support for JavaScript-like regex.
return;
section('Regex searches');
$input->setInput("regex", true);

// escaped dot
search('\.', array(
	"/$projName/committed.py" => $e_committed,
	"/$projName/changed.py" => $e_changed
));

$e_changed = array(
	array('line' => 2, 'text' => $changed_2)
);
search('on.*\d', array(
	"/$projName/changed.py" => $e_changed
));
