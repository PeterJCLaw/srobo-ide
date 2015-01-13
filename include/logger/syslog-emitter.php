<?php

/**
 * An emitter for the logger class which outputs to the system log.
 */
class SyslogEmitter
{
	/**
	 * Create a new instance.
	 * @param facility: The syslog facility to use. One of PHP's LOG_
	 *                  constants suitable for passing to 'openlog'.
	 */
	public function __construct($facility)
	{
		openlog("", LOG_ODELAY, $facility);
	}

	public function emit($level, $message)
	{
		syslog($level, $message);
	}
}
