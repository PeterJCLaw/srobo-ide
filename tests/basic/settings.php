<?php

$settings = Settings::getInstance();
test_nonnull($settings, 'failed to get Settings singleton');

$settings->setSettings('_test_user', array('bees' => 'inmyeyes'));
$data = $settings->getSettings('_test_user');
test_equal($data, array('bees' => 'inmyeyes'), "bees not found in eyes");

$settings->clearSettings('_test_user');
$data = $settings->getSettings('_test_user');
test_equal($data, array(), 'unexpected bees in eyes');
