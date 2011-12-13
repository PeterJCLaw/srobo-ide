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
