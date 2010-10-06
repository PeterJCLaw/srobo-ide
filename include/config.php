<?php

/**
 * Class to deal with configuration
 *
 * Reads config.ini to set config keys, is a singleton class
 */
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

	private function add_config_file($path)
	{
		if (file_exists($path))
		{
			$config = parse_ini_file($path);
			if ($this->config == false) {
				$this->config = $config;
			} else if ($config != false) {
				$this->config = $config + $this->config;
			}

		}
	}

	private function __construct()
	{
		$this->add_config_file('config/config.ini');
		$this->add_config_file('config/automagic.ini');
		$this->add_config_file('config/local.ini');
	}

	/**
	 * Gets a specific config key, returns null if the key doesn't exist
	 */
	public function getConfig($key)
	{
		if (isset($this->config[$key]))
			return $this->config[$key];
		else
			return null;
	}

	/**
	 * Overrides a config key
	 */
	public function override($key, $value)
	{
		$this->config[$key] = $value;
	}
}

