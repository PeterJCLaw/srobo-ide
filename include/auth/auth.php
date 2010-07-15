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
			$class = caseTransform($module, CASE_SLASHES, CASE_CAMEL_UCFIRST) . 'Auth';
			self::$singleton = new $class();
		}
		return self::$singleton;
	}

    abstract public function getCurrentUser();
    abstract public function authUser($authToken);
    abstract public function deauthUser($authToken);
    abstract public function getNextAuthToken();
}
