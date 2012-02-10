<?php

function test_str_insert($str, $insert, $pos, $result)
{
	$res = str_insert($str, $insert, $pos);
	test_equal($res, $result, "Wrong combined string for input: $str, $insert, $pos");
}

test_str_insert('bacon eggs', ' and', 5, 'bacon and eggs');

test_str_insert('bacon eggs', ' and', -5, 'bacon and eggs');

subsection('append');
test_str_insert('garden', ' center', 6, 'garden center');

subsection('prepend');
test_str_insert('garden', 'back ', 0, 'back garden');

