<?php

function path_walk($callback, $path, $reverse = false)
{
	$parts = explode('/', $path);
	if ($parts[0] == '')
	{
		$start = 1;
		$prefix = '';
	}
	else
	{
		$start = 0;
		$prefix = '.';
	}
	$paths = array();
	for ($i = $start; $i <= count($parts); ++$i)
	{
		$paths[] = $prefix . '/' . implode('/', array_slice($parts, $start, $i - $start));
	}
	if ($reverse)
		$paths = array_reverse($paths);
	foreach ($paths as $path)
		$callback($path);
}

function delete_recursive($path)
{
	if (is_dir($path))
	{
		$contents = scandir($path);
		$contents = array_filter($contents, function($x) { return $x != '.' &&
		                                                          $x != '..'; });
		$contents = array_map(function($x) use ($path) { return "$path/$x"; }, $contents);
		array_map('delete_recursive', $contents);
		rmdir($path);
	}
	else
	{
		unlink($path);
	}
}

function mkdir_full($path, $mode = 0755)
{
	return mkdir($path, $mode, true);
}

function tmpdir($dir = null, $prefix = 'ide')
{
	$dir = $dir != null ? $dir : sys_get_temp_dir();
	$file = tempnam($dir, $prefix);
	unlink($file);
	mkdir($file);
	return $file;
}

function file_lock($lockfile)
{
	ide_log(LOG_INFO, "Creating a lock on '$lockfile'.");
	$resource = fopen( $lockfile, "w" );
	$ret = true;

	$maxWait = Configuration::getInstance()->getConfig('lock.max_wait');
	$maxWait /= 1000; // convert milliseconds to seconds

	// microtime(true) returns a float in seconds
	$end = microtime(true) + $maxWait;

	// loop until we get a lock or maxWait time has passed
	do
	{
		$ret = flock( $resource, LOCK_EX | LOCK_NB );
		ide_log(LOG_DEBUG, "flock(LOCK_EX | LOCK_NB) returned: $ret.");
		usleep(10);
	}
	while ( microtime(true) < $end && !$ret );

	if ($ret !== true)
	{
		ide_log(LOG_ERR, "flock(LOCK_EX) failed to get lock on '$resource'.");
		throw new Exception("Failed to get a lock on '$lockfile'.", E_INTERNAL_ERROR);
	}

	$ret = fwrite( $resource, getmypid() );
	ide_log(LOG_DEBUG, "fwrite(pid) returned: $ret.");

	ide_log(LOG_INFO, "Got a lock on '$lockfile': '$resource'.");
	return $resource;
}

function file_unlock($resource)
{
	/* Free our lock on the file - manually since PHP 5.3.2 */
	ide_log(LOG_INFO, "Dropping lock on '$resource'.");

	$ret = flock($resource, LOCK_UN);
	ide_log(LOG_DEBUG, "flock(LOCK_UN) returned: $ret.");
	if ($ret !== true)
	{
		ide_log(LOG_ERR, "flock(LOCK_UN) failed to release lock on '$resource'.");
	}

	$ret = fclose( $resource );
	ide_log(LOG_DEBUG, "fclose returned: $ret.");

	ide_log(LOG_INFO, "Closed '$resource'.");
	return $ret;
}
