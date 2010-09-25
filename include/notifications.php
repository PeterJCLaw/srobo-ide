<?php

class Notifications
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Notifications();
		return self::$singleton;
	}

	private $notepath = '';

	private function __construct()
	{
		$config = Configuration::getInstance();
		$this->notepath = $config->getConfig('notepath');
	}

	private function noteFileForTeam($team)
	{
		$path = "{$this->notepath}/$team.txt";
		if (!file_exists($path))
			touch($path);
		var_dump($path);
		return $path;
	}

	public function pendingNotificationsForTeam($team)
	{
		$contents = file($this->noteFileForTeam($team));
		$contents = array_map('trim', $contents);
		$contents = array_filter($contents, function($x) { return $x != ''; });
		return $contents;
	}

	public function clearNotificationsForTeam($team)
	{
		$fp = fopen($this->noteFileForTeam($team), 'w');
		ftruncate($fp, 0);
		fclose($fp);
	}

	public function writeNotificationForTeam($team, $notification)
	{
		$fp = fopen($this->noteFileForTeam($team), 'a');
		fwrite($fp, "$notification\n");
		fclose($fp);
	}
}
