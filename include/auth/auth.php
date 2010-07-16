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

    abstract public function getCurrentUser();
    abstract public function getCurrentUserGroups();
    abstract public function authUser($username, $password);
    abstract public function deauthUser();
    abstract public function getNextAuthToken();
    abstract public function validateAuthToken($token);
}
