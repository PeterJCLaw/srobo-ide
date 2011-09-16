<?php

abstract class AuthBackend
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
		{
			$config = Configuration::getInstance();
			$module = $config->getConfig('auth_module');
			require_once("include/auth/$module.php");
			$class = transformCase($module, CASE_SLASHES, CASE_CAMEL_UCFIRST) . 'Auth';
			self::$singleton = new $class();
		}
		return self::$singleton;
	}

	public static function requireAuthenticated()
	{
		$instance = self::getInstance();
		if (!$instance->getCurrentUser())
		{
			throw new Exception('authentication required', E_AUTH_REQUIRED);
		}
	}

	public static function requireAdministrator()
	{
		$instance = self::getInstance();
		if (!$instance->isCurrentUserAdmin())
		{
			throw new Exception('administrator access required', E_PERM_DENIED);
		}
	}

	abstract public function getCurrentUser();
	abstract public function getCurrentUserTeams();
	abstract public function isCurrentUserAdmin();
	abstract public function authUser($username, $password);
	abstract public function deauthUser();
	abstract public function getNextAuthToken();
	abstract public function validateAuthToken($token);
	abstract public function displayNameForTeam($team);
	abstract public function displayNameForUser($user);
	abstract public function emailForUser($user);
}
