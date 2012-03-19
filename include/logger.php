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
		static $file = null;
		if ($file === null)
		{
			$path = Configuration::getInstance()->getConfig('log.location');
			$file = fopen($path, 'a');
		}
		$prefix = self::locationData($level);
		fwrite($file, "[$prefix] $message\n");
		fflush($file);
	}

	private static function locationData($level)
	{
		static $pid;
		if ($pid === null)
			$pid = getmypid();

		$level = self::$names[$level];
		$data = array();
		$data['D'] = date('Y-m-d H:i:s');
		$data['P'] = $pid;
		$data['L'] = $level;

		$input = Input::getInstance();
		$rq = $input->getRequestModule().'/'.$input->getRequestCommand();
		if (!$rq)
			$rq = 'none';
		$data['RQ'] = $rq;

		$prefix = array();
		foreach ($data as $k => $v)
		{
			$prefix[] = "$k = $v";
		}
		$prefix = implode(', ', $prefix);
		return $prefix;
	}

	/**
	 * Is the IDE logging a given level?
	 * @param level: The level to test.
	 * @returns: (bool) Whether or not that level is being logged
	 */
	public static function isLogging($level)
	{
		$loggingLevel = Configuration::getInstance()->getConfig('log.level');
		return $loggingLevel >= $level;
	}
}
