<?php

$config = Configuration::getInstance();

$config->override('modules', array('file/simulate'));

$mm = ModuleManager::getInstance();
$mm->importModules();
test_true($mm->moduleExists("file/simulate"), "simulator module does not exist");
$mod = $mm->getModule("file/simulate");
test_nonnull($mod, "simulate module was null");

test_exception(function () use ($mod) { $mod->dispatchCommand('begin'); },
               E_NOT_IMPL, "'begin' did not throw");
test_exception(function () use ($mod) { $mod->dispatchCommand('end'); },
               E_NOT_IMPL, "'end' did not throw");
test_exception(function () use ($mod) { $mod->dispatchCommand('status'); },
               E_NOT_IMPL, "'status' did not throw");
