<?php

class FakeModule
{
	private $commands = array();
	private $handler = null;

	public function setHandler($handler)
	{
		$this->handler = $handler;
	}

	public function dispatchCommand($cmd)
	{
		$this->commands[] = $cmd;
		$handler = $this->handler;
		if ($handler != null)
		{
			$handler($cmd);
		}
	}

	public function getCommands()
	{
		return $this->commands;
	}
}
