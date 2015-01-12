<?php

/**
 * An emitter for the logger class which outputs to the system log.
 */
class SyslogEmitter
{
	public function __construct($facility_name)
	{
		$facility = constant($facility_name);
		openlog("", LOG_ODELAY, $facility);
	}

	public function emit($level, $message)
	{
		syslog($level, $message);
	}
}
