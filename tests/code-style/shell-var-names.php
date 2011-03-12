<?php

$failures = false;
$fial_lines = 0;

$pattern = '(gitExecute|shell_exec)\s*\(.*\\$.*\)';
$s_pattern = escapeshellarg($pattern);

$raw_results = shell_exec('grep -rnE --include=*.php '.$s_pattern.' .');

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

function get_cmd_line($match)
{
	$pos = strpos($match, 'shell_exec');
	if ($pos === FALSE)
	{
		$pos = strpos($match, 'gitExecute');
		if ($poa === FALSE)
		{
			// something seriously messed up here!
			return $match;
		}
	}
	// add length of the search terms
	$pos += 10;

	return substr($match, $pos);
}

function startswith($string, $expectedStart)
{
	$len = strlen($expectedStart);
	$start = substr($string, 0, $len);
	return $start == $expectedStart;
}

$failures = 1;

$results = explode("\n", $raw_results);

foreach($results as $result)
{
	$local_fail = FALSE;
	list($file, $line, $full_match) = get_parts($result);
	$args = get_cmd_line($full_match);
//	var_dump($args);

	$matches = array();
	$count = preg_match_all('/\$\S+/', $args, $matches);

	foreach($matches[0] as $arg)
	{
//		var_dump($arg);
		if (!startswith($arg, '$s_'))
		{
			$local_fail = TRUE;
			$failures++;
		}
	}
	if ($local_fail)
	{
		echo $result."\n";
		$fail_lines++;
	}
}

test_false($failures, 'Found '.$failures.' bad shell variable(s) on '.$fail_lines.' line(s)!');
