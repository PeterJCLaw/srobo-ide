<?php

require_once('include/config.php');
require_once('include/cache-utils.php');

function css_tag($name)
{
	echo '<link rel="stylesheet" type="text/css" href="', $name, '">', PHP_EOL;
}

function js_tag($name)
{
	echo '<script type="text/javascript" src="', $name, '"></script>', PHP_EOL;
}

function prefetch_tag($name)
{
	echo '<link rel="prefetch" href="', $name, '">', PHP_EOL;
}

/**
 * Outputs the given collection using the given callback.
 * If a cache file is specified, then it is attempted to be used, but
 * if that fails this falls back to outputting the original files.
 */
function output_statics($items, $callback, $cache_file = null)
{
	if ($cache_file != null && Configuration::getInstance()->getConfig('combine_statics')
		&& combine_into($items, $cache_file) !== false)
	{
		$callback($cache_file);
	}
	else
	{
		foreach ($items as $item)
		{
			$callback($item);
		}
	}
}

/**
 * Outputs the given item for prefetch, but only if the file exists and
 * we're doing resource combining.
 */
function checked_prefetch($item)
{
	if (is_file($item) && Configuration::getInstance()->getConfig('combine_statics'))
	{
		prefetch_tag($item);
	}
}
