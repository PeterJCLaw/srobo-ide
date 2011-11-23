<?php

/**
 * A (static) helper class that contains logging related functionality.
 */
class Logger
{
	public static $names = array(LOG_DEBUG => 'debug', LOG_INFO => 'info', LOG_ERR => 'error');

	public static function log($level, $message)
	{
		if (!self::isLogging($level))
			return;
		$level = self::$names[$level];
		static $file = null;
		if ($file === null)
			$file = fopen('/tmp/ide-log', 'a');
		$input = Input::getInstance();
		$rq = $input->getRequestCommand();
		if (!$rq)
			$rq = 'none';
		fwrite($file, "[RQ = $rq, L = $level] $message\n");
		fflush($file);
	}

	/**
	 * Is the IDE logging a given level?
	 * @param level: The level to test.
	 * @returns: (bool) Whether or not that level is being logged
	 */
	public static function isLogging($level)
	{
		$loggingLevel = Configuration::getInstance()->getConfig('log_level');
		return $loggingLevel >= $level;
	}
}
