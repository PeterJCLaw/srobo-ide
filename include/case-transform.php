<?php

define('CASE_HUMAN_LOWER', 0);
define('CASE_HUMAN_UPPER', 1);
define('CASE_CAMEL_UCFIRST', 2);
define('CASE_CAMEL_LCFIRST', 3);
define('CASE_UNDERSCORES', 4);
define('CASE_SLASHES', 5);

function _transformCaseToUnderscores($source, $sourceType)
{
	switch ($sourceType)
	{
		case CASE_HUMAN_LOWER:
		case CASE_HUMAN_UPPER:
			return strtolower(str_replace(' ', '_', $source));
		case CASE_CAMEL_UCFIRST:
		case CASE_CAMEL_LCFIRST:
			$repl = preg_replace('/[A-Z]/', '_\\0', $source);
			if (strlen($repl) > 0 && $repl[0] == '_')
				$repl = substr($repl, 1);
			return strtolower($repl);
		case CASE_UNDERSCORES:
			return $source;
		case CASE_SLASHES:
			return str_replace('/', '_', $source);
	}
	return $source;
}

function _transformCaseFromUnderscores($source, $destType)
{
	switch ($destType)
	{
		case CASE_HUMAN_LOWER:
			return str_replace('_', ' ', $source);
		case CASE_HUMAN_UPPER:
			return ucwords(str_replace('_', ' ', $source));
		case CASE_CAMEL_UCFIRST:
			return str_replace(' ', '', _transformCaseFromUnderscores($source, CASE_HUMAN_UPPER));
		case CASE_CAMEL_LCFIRST:
			$name = str_replace(' ', '', _transformCaseFromUnderscores($source, CASE_HUMAN_UPPER));
			if (!empty($name))
			{
				$name[0] = strtolower($name[0]);
			}
			return $name;
		case CASE_UNDERSCORES:
			return $source;
		case CASE_SLASHES:
			return str_replace('_', '/', $source);
	}
	return $source;
}

function transformCase($source, $sourceType, $destType)
{
	if ($sourceType == $destType)
		return $source;
	$temp = _transformCaseToUnderscores($source, $sourceType);
	return _transformCaseFromUnderscores($temp, $destType);
}
