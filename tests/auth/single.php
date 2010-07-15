<?php

$config = Configuration::getInstance();
$config->override('auth_module', 'single');
$config->override('user.default', 'cake');

// get the module
$auth = AuthBackend::getInstance();
test_nonnull($auth, "failed to get auth backend");
test_class($auth, 'SingleAuth', "auth backend was of the wrong class");

test_null($auth->getCurrentUser(), "auth falsely returned a user");
$auth->authUser(array('user'     => 'cake',
                      'password' => 'bees'));
test_equal($auth->getCurrentUser(), 'cake', 'auth returned incorrect user');
$token = $auth->getNextAuthToken();
$auth->deauthUser($token);
test_null($auth->getCurrentUser(), 'auth did not deauthenticate user');
      
