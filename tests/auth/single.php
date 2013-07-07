<?php

$config = Configuration::getInstance();
$config->override('auth_module', 'single');
$config->override('user.default', 'cake');
$config->override('user.default.teams', array(1, 2));

// get the module
$auth = AuthBackend::getInstance();
test_nonnull($auth, "failed to get auth backend");
test_class($auth, 'SingleAuth', "auth backend was of the wrong class");

test_null($auth->getCurrentUserName(), "auth falsely returned a user");
test_true($auth->authUser('cake', 'bees'), 'authentication failed');
test_equal($auth->getCurrentUserName(), 'cake', 'auth returned incorrect user');
test_equal($auth->getCurrentUserTeams(), array(1, 2), "user not in 2 teams as per config file");
$token = $auth->getNextAuthToken();
$auth->deauthUser($token);
test_null($auth->getCurrentUserName(), 'auth did not deauthenticate user');
