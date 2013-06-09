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

/**
 * Outputs the given collection using the given callback.
 */
function output_statics($items, $callback, $cache_file = null)
{
	if ($cache_file != null && Configuration::getInstance()->getConfig('combine_statics'))
	{
		combine_into($items, $cache_file);
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
