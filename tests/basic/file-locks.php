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
