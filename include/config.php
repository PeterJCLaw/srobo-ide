<?php

class Configuration
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Configuration();
		return self::$singleton;
	}

	private $config = array();

	private function __construct()
	{
		$this->config = parse_ini_file("config/config.ini");
	}

	public function getConfig($key)
	{
		if (isset($this->config[$key]))
			return $this->config[$key];
		else
			return null;
	}

	public function override($key, $value)
	{
		$this->config[$key] = $value;
	}
}

