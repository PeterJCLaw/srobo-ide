<?php
$config = Configuration::getInstance();
$config->override('auth_module', 'ldap');
$config->override('ldap.host', 'localhost');

$input = Input::getInstance();
$input->setInput("user", "your_ldap_auth_user_here");
$input->setInput("password", "your_ldap_auth_pass_here");

$auth = AuthBackend::getInstance();
test_nonnull($auth, "failed to get the auth backend");
test_class($auth, "LDAPAuth", "auth backend was not the ldap auth backend");

test_null($auth->getCurrentUser(), "without authentication the user was not null");
test_true($auth->authUser($input->getInput("user"), $input->getInput("password")), "failed to auth user");
test_equal($auth->getCurrentUser(), $input->getInput("user"), "the authed user was not the user passed to the auth module");

//TODO: check the users teams versus ldap

$token = $auth->getNextAuthToken();
$auth->deauthUser($token);
test_null($auth->getCurrentUser(), "after deauth, the user was not null");
