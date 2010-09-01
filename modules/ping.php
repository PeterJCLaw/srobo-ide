<?php

/**
 * Module for pinging the server
 *
 * Installed commands are:
 * ping ('data':Int) -> ('data':Int)
 */
class PingModule extends Module
{
	public function __construct()
	{
		$this->installCommand('ping', array($this, 'ping'));
	}

	public function ping()
	{
		$output = Output::getInstance();
		$input  = Input::getInstance();
		if ($ping = $input->getInput('data', 1))
			$output->setOutput('data', $ping);
		return true;
	}
}
