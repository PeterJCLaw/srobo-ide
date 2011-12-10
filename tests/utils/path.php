<?php

function test_get_ext($path, $ext)
{
	$actual = path_get_extension($path);
	test_equal($actual, $ext, "Failed to extract extension '$ext' from '$path'");
}

function test_change_extension($path, $ext, $expected)
{
	$actual = path_change_extension($path, $ext);
	test_equal($actual, $expected, "Failed to convert '$path' to extension '$ext'");
}

section('path_get_extension');

test_get_ext('a', null);
test_get_ext('a.bar', 'bar');
test_get_ext('a/b/a.bar', 'bar');
test_get_ext('/a//b/a.bar', 'bar');

section('path_change_extension');

test_change_extension('a', 'foo', 'a.foo');
test_change_extension('a.bar', 'foo', 'a.foo');
test_change_extension('a/b/a.bar', 'foo', 'a/b/a.foo');
test_change_extension('/a//b/a.bar', 'foo', '/a//b/a.foo');
