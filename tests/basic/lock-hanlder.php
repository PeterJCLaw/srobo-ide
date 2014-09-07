<?php

$lh = LockHandler::getInstance();

$file = $testWorkPath.'/bacon';

$lock1 = $lh->lock($file);
test_nonempty($lock1, 'Failed to get first lock on file!');
$lock2 = $lh->lock($file);
test_nonempty($lock2, 'Failed to get second lock on file!');

test_true($lock1 === $lock2, 'Simultaneous locks on the same file should be the same resource');
test_equal($lh->handleCount(), 1, 'Two locks on the same file should be stored as a single handle');

$lh->unlock($lock2);

test_equal($lh->handleCount(), 1, 'Releasing one lock on the file should not remove the lock completely');

$lh->unlock($file);

test_equal($lh->handleCount(), 0, 'Releasing the remaining lock on the file should remove the lock completely');

$lock3 = $lh->lock($file);

test_nonempty($lock3, 'Failed to get lock on file after closing all previous handles');
test_equal($lh->handleCount(), 1, 'Getting another lock on the file should be stored as a single handle');

$lh->unlock($file);

test_equal($lh->handleCount(), 0, 'Releasing third lock on the file should remove the lock completely');

test_exception(function() use($lh, $file) {
    $lh->unlock($file);
}, E_INTERNAL_ERROR, "Should error about trying to unlock file (by name) not already locked");

test_exception(function() use($lh, $lock3) {
    $lh->unlock($lock3);
}, E_INTERNAL_ERROR, "Should error about trying to unlock file (by resource) not already locked");
