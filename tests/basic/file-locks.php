<?php

$file = $testWorkPath.'/bacon';

$lock1 = file_lock($file);
test_nonempty($lock1, 'Failed to get first lock on file!');

test_exception(function () use($file)
	{
		return file_lock($file);
	},
	E_INTERNAL_ERROR,
	'Attempting to get a file lock on an already locked file should except.'
);

test_true(file_unlock($lock1), 'Releasing the lock should succeed.');

$lock2 = file_lock($file);
test_nonempty($lock2, 'Having unlocked, should be able to get a further lock on the same file.');

test_true(file_unlock($lock2), 'Releasing the second lock should succeed.');

$lock3 = file_lock($file, false);
test_nonempty($lock3, 'Failed to get shared lock on file!');

$lock4 = file_lock($file, false);
test_nonempty($lock4, 'Failed to get shared lock on shared-locked file!');

test_exception(function () use($file)
	{
		return file_lock($file);
	},
	E_INTERNAL_ERROR,
	'Attempting to get an exclusive file lock on an already shared-locked file should throw.'
);

test_true(file_unlock($lock3), 'Releasing the first shared lock should succeed.');
test_true(file_unlock($lock4), 'Releasing the second shared lock should succeed.');
