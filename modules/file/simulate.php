<?php

class FileSimulateModule extends Module
{
	public function dieUnpleasantly()
	{
		throw new Exception('unimplemented', 7);
	}

	public function __construct()
	{
		$unimpCallback = array($this, 'dieUnpleasantly');
		$this->installCommand('begin', $unimpCallback);
		$this->installCommand('end', $unimpCallback);
		$this->installCommand('status', $unimpCallback);
	}
}
