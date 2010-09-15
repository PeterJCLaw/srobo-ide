<?php

require_once("tokenstrategy.php");

class CookieStrategy extends TokenStrategy
{
	/**
	 * the name for the cookie
	 */
	const COOKIENAME = "token";

	/**
	 * the path the cookie is valid for
	 */
	private $path;

	public function __construct()
	{
		$this->path = dirname($_SERVER['SCRIPT_NAME']).'/';
	}

	public function getAuthToken()
	{
		if (isset($_COOKIE[self::COOKIENAME]))
		{
			return $_COOKIE[self::COOKIENAME];
		}
		else
		{
			return null;
		}
	}

	public function setNextAuthToken($token)
	{
		$expiry = Configuration::getInstance()->getConfig("cookie_expiry_time");
		if ($expiry == null)
		{
			throw new Exception("no cookie expiry time found in config file", E_NO_EXPIRY_TIME);
		}
		setcookie(
		          self::COOKIENAME,
		          $token,
		          time() + (int)$expiry,
		          $this->path
		         );
	}

	public function removeAuthToken()
	{
		setcookie(self::COOKIENAME, "", time() - 3600, $this->path);
	}

}
