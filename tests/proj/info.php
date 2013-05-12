<?php

$repo_clone_url = '{ user: "%1$s", team: "%2$s", project: "%3$s" }';
$user = 'bees';
$team = 'ABC';
$project = 'wasps';
$expected_url = "{ user: \"$user\", team: \"$team\", project: \"$project\" }";

$config = Configuration::getInstance();
$config->override('repo_clone_url', $repo_clone_url);
$config->override('modules', array('proj'));

$config->override("user.default", "death");
$config->override("user.default.teams", array(1, 2, $team));
$config->override("auth_module", "single");

// do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser($user,'face'), "authentication failed");

// setup the required input keys
$input = Input::getInstance();
$input->setInput("team", $team);
$input->setInput("project", $project);

$mm = ModuleManager::getInstance();
$mm->importModules();
$proj = $mm->getModule('proj');

$proj->dispatchCommand('info');

$output = Output::getInstance();
$repoUrl = $output->getOutput('repoUrl');
test_nonnull($repoUrl, 'Failed to get repoUrl from output');

test_equal($repoUrl, $expected_url, "Wrong repo url data output");
