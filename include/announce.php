<?php

require_once('include/pipebot/pipebot.php');

/**
 * Class to handle announcing things to the world.
 * Currently wraps a pipebot instance.
 */
class Announce
{
	/**
	 * Announces something to the world.
	 * @param message: The thing to announce.
	 */
	public static function that($message)
	{
		$enabled = Configuration::getInstance()->getConfig('announce.enabled');
		if ($enabled !== true)
		{
			return;
		}

		Pipebot::say($message);
	}
}
