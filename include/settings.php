<?php

class Settings
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Settings();
		return self::$singleton;
	}

	private $settingsPath;

	private function __construct()
	{
		$config = Configuration::getInstance();
		$this->settingsPath = $config->getConfig('settingspath');
	}

	private function settingsFile($user)
	{
		return "$this->settingsPath/$user.json";
	}

	public function getSettings($user)
	{
		$path = $this->settingsFile($user);
		if (file_exists($path))
		{
			$data = file_get_contents($path);
			return (array)json_decode($data);
		}
		else
		{
			return array();
		}
	}

	public function setSettings($user, $settings)
	{
		$data = json_encode($settings);
		file_put_contents($this->settingsFile($user), $data);
	}

	public function clearSettings($user)
	{
		@unlink($this->settingsFile($user));
	}
}
