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

/**
 * Does a recursive copy of a folder.
 * TODO: implement in PHP rather than using cp?
 */
function copy_recursive($source, $dest)
{
	$s_source = escapeshellarg($source);
	$s_dest = escapeshellarg($dest);
	echo "cp -r $s_source $s_dest\n";
	$res = shell_exec('cp -r '.$s_source.' '.$s_dest);
	var_dump($res);
	return $res;
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

function tmpdir($dir = null, $prefix = 'ide-tmp-')
{
	$dir = $dir != null ? $dir : sys_get_temp_dir();
	$file = tempnam($dir, $prefix);
	unlink($file);
	mkdir($file);
	return $file;
}

function path_get_extension($path)
{
	if (($pos = strrpos($path, '.')) !== FALSE)
	{
		$ext = substr($path, $pos + 1);
		return $ext;
	}
	return null;
}

function path_change_extension($path, $ext)
{
	if (($curExt = path_get_extension($path)) !== null)
	{
		$path = substr($path, 0, -1 * strlen($curExt));
	}
	else
	{
		$path .= '.';
	}
	$path .= $ext;
	return $path;
}

/**
 * Helper that moves an uploaded file, preserving the original extension.
 * @param id: The id to look for in the $_FILES array.
 * @param move_to_base: The base path to move the uploaded file to.
 *                      The original file extension (including a dot) will be appended.
 * @returns: The resulting name of the file.
 */
function move_uploaded_file_id($id, $move_to_base)
{
	if (!isset($_FILES[$id]))
	{
		throw new Exception("File '$id' was not uploaded.", E_MALFORMED_REQUEST);
	}

	$file = $_FILES[$id];
	if ($file['size'] === 0)
	{
		throw new Exception("File '$id' was empty.", E_MALFORMED_REQUEST);
	}

	if (($error = $file['error']) != UPLOAD_ERR_OK)
	{
		throw new Exception("Error in file '$id': $error.", E_MALFORMED_REQUEST);
	}

	$name = $file['name'];
	if (($pos = strrpos($name, '.')) !== FALSE)
	{
		$ext = substr($name, $pos);
		$move_to_base .= $ext;
	}

	$tmp_name = $file['tmp_name'];
	if (!move_uploaded_file($tmp_name, $move_to_base))
	{
		throw new Exception("Failed to move uploaded file '$tmp_name' to '$move_to_base'", E_INTERNAL_ERROR);
	}
	return $move_to_base;
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
		usleep(10000); // 10 milliseconds
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
