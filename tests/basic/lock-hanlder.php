<?php

$lh = LockHandler::getInstance();

$file = $testWorkPath.'/bacon';

section('locking');
$lock1 = $lh->lock($file);
test_nonempty($lock1, 'Failed to get first lock on file!');
$lock2 = $lh->lock($file);
test_nonempty($lock2, 'Failed to get second lock on file!');

test_true($lock1 === $lock2, 'Simultaneous locks on the same file should be the same resource');
test_equal($lh->handleCount(), 1, 'Two locks on the same file should be stored as a single handle');

$lh->unlock($lock2);

test_equal($lh->handleCount(), 1, 'Releasing one lock on the file should not remove the lock completely');

subsection('locking shared when locked exclusive');
$lock1shared = $lh->lock($file, false);

test_true($lock1 === $lock1shared, 'Simultaneous locks on the same file should be the same resource, even when the later one is less restrictive');
test_equal($lh->handleCount(), 1, 'Two locks on the same file should be stored as a single handle');

subsection('unlocking');
$lh->unlock($lock1);

test_equal($lh->handleCount(), 1, "Releasing even the 'exclusive' lock on a file shouldn't unlock it when there's still a shared request outstanding");

$lh->unlock($file);

test_equal($lh->handleCount(), 0, 'Releasing the remaining lock on the file should remove the lock completely');

section('locking after unlocking');
$lock3 = $lh->lock($file);

test_nonempty($lock3, 'Failed to get lock on file after closing all previous handles');
test_equal($lh->handleCount(), 1, 'Getting another lock on the file should be stored as a single handle');

$lh->unlock($file);

test_equal($lh->handleCount(), 0, 'Releasing third lock on the file should remove the lock completely');

section('unlock when not locked');
test_exception(function() use($lh, $file) {
    $lh->unlock($file);
}, E_INTERNAL_ERROR, "Should error about trying to unlock file (by name) not already locked");

test_exception(function() use($lh, $lock3) {
    $lh->unlock($lock3);
}, E_INTERNAL_ERROR, "Should error about trying to unlock file (by resource) not already locked");

section('lock exclusive when already locked shared');
$lock4shared = $lh->lock($file, false);

// check that we can get another shared lock on the item, it will except on its own if it fails
$tmp = file_lock($file, false);
file_unlock($tmp);

test_exception(function() use($lh, $file) {
    $lh->lock($file);
}, E_INTERNAL_ERROR, "Should error about trying to get an exclusive lock on a file only locked for sharing");

// release lock
$lh->unlock($file);
