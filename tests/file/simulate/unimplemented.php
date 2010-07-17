<?php

$config = Configuration::getInstance();

$config->override('modules', array('file/simulate'));

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists("file/simulate"), "simulator module does not exist");
$mod = $mm->getModule("file/simulate");
test_nonnull($mod, "simulate module was null");

test_exception(function () use ($mod) { $mod->dispatchCommand('begin'); },
               7, "'begin' did not throw");
test_exception(function () use ($mod) { $mod->dispatchCommand('end'); },
               7, "'end' did not throw");
test_exception(function () use ($mod) { $mod->dispatchCommand('status'); },
               7, "'status' did not throw");
