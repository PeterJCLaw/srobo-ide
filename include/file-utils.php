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

/**
 * Helper that moves an uploaded file, preserving the original extension.
 * @param id: The id to look for in the $_FILES array.
 * @param move_to_base: The base path to move the uploaded file to.
 *                      The original file extension (including a dot) will be appended.
 * @returns: The resulting name of the file, or FALSE if it failed.
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
	return true;
}
