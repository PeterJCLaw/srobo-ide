<?php

/**
 * Insert one string into another, at a given position.
 * @param str: The string to insert into.
 * @param insert: The string to insert.
 * @param pos: The index to insert the string at.
 * @returns: The resulting string, or FALSE on failure.
 */
function str_insert($str, $insert, $pos)
{
	$start = substr($str, 0, $pos);
	$end = substr($str, $pos);
	$result = $start.$insert.$end;
	return $result;
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
