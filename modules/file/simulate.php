<?php

class FileSimulateModule extends Module
{
	public function dieUnpleasantly()
	{
		throw new Exception('unimplemented', E_NOT_IMPL);
	}

	public function __construct()
	{
		$unimpCallback = array($this, 'dieUnpleasantly');
		$this->installCommand('begin', $unimpCallback);
		$this->installCommand('end', $unimpCallback);
		$this->installCommand('status', $unimpCallback);
	}
}
