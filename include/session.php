<?php

class Session
{
	private static $singleton = null;

	private function __construct()
	{
		//Cookies only, no url tweaking.
		echo 'use_only_cookies: '; var_dump( ini_set('session.use_only_cookies', true) );
		echo 'url_rewriter.tags: '; var_dump( ini_set('url_rewriter.tags', '') );

		//Don't allow scripts access to the cookie, only us.
	//	echo 'cookie_httponly: '; var_dump( ini_set('session.cookie_httponly', true) );

		//Use a useful cookie name.
		echo 'name: '; var_dump( ini_set('session.name', 'CyanIDE') );

		//HTTPS cookies only, if config says so.
		$config = Configuration::getInstance();
		if ($config->getConfig('require_ssl'))
		{
			echo 'secure cookies';
			var_dump( ini_set('session.cookie_secure', true));
		}
		session_start();
		print 'session inited'."\n";
	}

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Session();
		return self::$singleton;
	}

	public function getVar($key)
	{
		echo "getting $key\n";
		if (isset($_SESSION[$key]))
			return $_SESSION[$key];
		else
			return null;
	}

	public function setVar($key, $value)
	{
		echo "setting $key\n";
		if ($value === null)
			unset($_SESSION[$key]);
		else
			$_SESSION[$key] = $value;
	}

	public function removeVar($key)
	{
		$this->setVar($key, null);
	}
}
