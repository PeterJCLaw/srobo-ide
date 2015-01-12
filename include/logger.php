<?php

/**
 * A helper class that contains logging related functionality.
 */
class Logger
{
	public static $names = array(LOG_DEBUG => 'debug',
	                             LOG_INFO => 'info',
	                             LOG_NOTICE => 'notice',
	                             LOG_WARNING => 'warning',
	                             LOG_ERR => 'error');

	private static $instance = null;

	private $emitter;
	private $level;

	public static function getInstance()
	{
		if (self::$instance === null)
		{
			$config = Configuration::getInstance();

			$location = $config->getConfig('log.location');
			$emitter  = new FileEmitter($location);

			$level = $config->getConfig('log.level');

			self::$instance = new Logger($emitter, $level);
		}
		return self::$instance;
	}

	protected function __construct($emitter, $level)
	{
		$this->emitter = $emitter;
		$this->level = $level;
	}

	public function log($level, $message)
	{
		if (!$this->isLogging($level))
			return;
		$this->emitter->emit($level, $message);
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
	 * Is this logger logging a given level?
	 * @param level: The level to test.
	 * @returns: (bool) Whether or not that level is being logged
	 */
	public function isLogging($level)
	{
		return $this->level >= $level;
	}
}
