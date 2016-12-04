<?php

require_once('include/auth/secure-token.php');

class SingleAuth extends SecureTokenAuth
{

	public function __construct()
	{
		parent::__construct();
	}

	protected function normaliseUsername($username)
	{
		return strtolower($username);
	}

	public function checkAuthentication($username, $password)
	{
		return $username != null && $password != null;
	}

	public function getWritableTeams($username)
	{
		return Configuration::getInstance()->getConfig("user.default.teams");
	}

	public function getReadableTeams($username)
	{
		return Configuration::getInstance()->getConfig("user.default.read_only_teams");
	}

	public function isCurrentUserAdmin()
	{
		return (bool) Configuration::getInstance()->getConfig('user.default.is_admin');
	}

	public function displayNameForUser($user)
	{
		return $user;
	}

	public function emailForUser($user)
	{
		return $user . "@" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com');
	}
}
