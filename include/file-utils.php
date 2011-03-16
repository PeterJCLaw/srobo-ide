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
