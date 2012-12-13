<?php

$config = Configuration::getInstance();

$modules = $config->getConfig('modules');
var_dump($modules);
test_nonnull($modules, "Failed to get list of modules.");

$config->override('modules', 42);
$modules = $config->getConfig('modules');
var_dump($modules);
test_equal($modules, 42, "Got wrong result after override.");
