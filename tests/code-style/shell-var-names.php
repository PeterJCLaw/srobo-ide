<?php

$failures = false;
$fail_lines = 0;

$shellCommands = array('gitExecute', 'gitExecuteInternal', 'proc_exec', 'shell_exec', 'proc_open');
$pattern = '('.implode('|', $shellCommands).')\s*\(.*\\$.*\)';
$s_pattern = escapeshellarg($pattern);

$raw_results = shell_exec('grep -rnE --include=*.php '.$s_pattern.' include/ modules/ tests/');

function get_parts($line)
{
	$pos = strpos($line, ':');
	$file = substr($line, 0, $pos);
	$line = substr($line, $pos + 1);

	$pos = strpos($line, ':');
	$num = substr($line, 0, $pos);
	$match = substr($line, $pos + 1);

	return array($file, $num, $match);
}

function get_cmd_line($match, $commands)
{
	$pos = FALSE;
	foreach ($commands as $command)
	{
		$pos = strpos($match, $command);
		if ($pos !== FALSE)
		{
			// match
			break;
		}
	}
	if ($pos === FALSE)
	{
		// something seriously messed up here!
		return $match;
	}
	// add length of the search terms
	$pos += strlen($command);

	return substr($match, $pos);
}

function endswith($string, $expectedEnd)
{
	$len = strlen($expectedEnd);
	$end = substr($string, -1 * $len);
	return $end == $expectedEnd;
}

function startswith($string, $expectedStart)
{
	$len = strlen($expectedStart);
	$start = substr($string, 0, $len);
	return $start == $expectedStart;
}

$results = explode("\n", $raw_results);
$declarationsPattern = '/(private )?(static )?function (' . implode('|', $shellCommands) . ')/';

foreach($results as $result)
{
	$local_fail = FALSE;
	list($file, $line, $full_match) = get_parts($result);

	// ignore the funciton declaration
	if (preg_match($declarationsPattern, $full_match))
	{
		continue;
	}

	// Allow things that have been explicitly marked safe
	if (endswith(trim($full_match), '// SHELL SAFE'))
	{
		continue;
	}

	// if it's one of ours, then we'll allow the first parameter to be
	// "unsafe" since we know that this is a path.
	$allowFirstUnsafe = false;
	if (preg_match('/::gitExecute(Internal)?\s*\(/', trim($full_match)))
	{
		$allowFirstUnsafe = true;
	}

	$args = get_cmd_line($full_match, $shellCommands);
//	var_dump($args);

	$matches = array();
	$count = preg_match_all('/\$\S+/', $args, $matches);

	$first = true;
	foreach($matches[0] as $arg)
	{
//		var_dump($arg);
		if ($first && $allowFirstUnsafe)
		{
			continue;
		}
		if (!startswith($arg, '$s_'))
		{
			$local_fail = TRUE;
			$failures += 1;
		}
		$first = false;
	}
	if ($local_fail)
	{
		echo $result."\n";
		$fail_lines++;
	}
}

test_false($failures, 'Found '.$failures.' bad shell variable(s) on '.$fail_lines.' line(s)!');
