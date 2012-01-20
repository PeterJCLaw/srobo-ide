<?php

/**
 * Class to handle pumping information at pipebot
 */
class Pipebot
{
	public static function say($message)
	{
		$pipe_file = Configuration::getInstance()->getConfig('pipebot.file');
		if ($pipe_file === null)
		{
			return;
		}

		$fd = fopen($pipe_file, "a");
		fwrite($fd, "$message\n");
		fclose($fd);
	}
}
