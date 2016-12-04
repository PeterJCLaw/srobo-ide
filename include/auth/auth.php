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

	/**
	 * Checks that the current user can write to the requested team,
	 * or throws an exception if not.
	 */
	public static function ensureWrite($team)
	{
		$authModule = self::getInstance();
		if ($authModule->getCurrentUserName() == null)
		{
			throw new Exception('not authenticated', E_PERM_DENIED);
		}

		if (!$authModule->canCurrentUserWriteTeam($team))
		{
			throw new Exception('You do not have permission to write within that team', E_PERM_DENIED);
		}
	}

	abstract public function getCurrentUserName();
	/**
	 * Returns *all* the teams that the current user is a member of.
	 */
	abstract public function getCurrentUserTeams();
	abstract public function canCurrentUserWriteTeam($team);
	/**
	 * Returns the teams that the current user has write access to.
	 * We assume that users having write access to a team also have read
	 * access to that team.
	 */
	abstract public function getWritableTeams($username);
	/**
	 * Returns the teams that the current user has read access to.
	 */
	abstract public function getReadableTeams($username);
	abstract public function isCurrentUserAdmin();
	abstract public function authUser($username, $password);
	abstract public function deauthUser();
	abstract public function getNextAuthToken();
	abstract public function validateAuthToken($token);
	abstract public function displayNameForUser($user);
	abstract public function emailForUser($user);
}
