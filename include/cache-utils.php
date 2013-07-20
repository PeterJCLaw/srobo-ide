<?php

/**
 * Works out whether or not the given outputs are up to date with regards
 * to the specified inputs, using the files' mtimes as a guide.
 * @param inputs: An array of input files.
 * @param outputs: An array of output files.
 * @returns: whether or not the output files are up to date.
 */
function up_to_date($inputs, $outputs)
{
	// find the oldest output
	$oldest = time();
	foreach ($outputs as $output)
	{
		if (!file_exists($output))
		{
			return false;
		}
		$mtime = filemtime($output);
//		echo "$output: $mtime\n";
		if ($mtime < $oldest)
		{
			$oldest = $mtime;
		}
	}
//	echo "oldest output: $oldest\n";

	foreach ($inputs as $input)
	{
		$mtime = filemtime($input);
//		echo "$input: $mtime\n";
		if ($mtime > $oldest)
		{
			return false;
		}
	}
	return true;
}

/**
 * Combines the given list of files' content into the given output file.
 * This function will only overwrite an existing file if it's out of date.
 * @param collection: An array of input files to combine.
 * @param output_file: The path to the file to combine the files into.
 * @returns: true if the outputs are up-to-date, otherwise the result from
 *           the write using file_put_contents.
 */
function combine_into($collection, $output_file)
{
	if (up_to_date($collection, array($output_file)))
	{
		return true;
	}
	$raw_data = '';
	foreach ($collection as $input_file)
	{
		$content = file_get_contents($input_file);
		if ($content !== FALSE)
		{
			$raw_data .= $content;
		}
	}
	$put = file_put_contents($output_file, $raw_data);
	return $put;
}
