<?php

class UserInfo
{
	private static $instance = null;
	private $info = array();
	private $user = null;

	//TODO: things here
	private function __construct()
	{
		$auth = AuthBackend::getInstance();
		$this->user = $auth->getCurrentUser();
		$this->info['teams'] = $auth->getCurrentUserTeams();
	}

	public static function getInstance()
	{
		if (self::$instance == null)
		{
			$input = Input::getInstance();
			self::$instance = new UserInfo();
		}

		return self::$instance;
	}

	public static function makeCommitterEmail($username)
	{
		return "$username@srobo.org";
	}

	public function getInfo($info)
	{
		if (isset($this->info[$info]))
		{
			return $this->info[$info];
		}
		else
		{
			throw new Exception('key in user info did not exist', E_INTERNAL_ERROR);
		}
	}

}
