<?php

/**
 * This module provides helpers to enable simplified installing of
 * commands that require either authentication or authorisation.
 */
abstract class AuthRequiredModule extends Module
{
	private static function shiftAndReplace($preconditions)
	{
		$more = func_get_args();
		array_shift($more);
		array_splice($preconditions, 0, 2, $more);
		return $preconditions;
	}

	/**
	 * Installs a command that requires the user be authenticated.
	 */
	protected function installCommandAuth($name, $handler)
	{
		$preconditions = self::shiftAndReplace(func_get_args());
		self::installCommandAuthArray($name, $handler, $preconditions);
	}

	/**
	 * Installs a command that requires the user be authenticated.
	 */
	protected function installCommandAuthArray($name, $handler, $preconditions)
	{
		array_unshift($preconditions, array('AuthBackend', 'requireAuthenticated'));
		parent::installCommandArray($name, $handler, $preconditions);
	}

	/**
	 * Installs a command that requires the user be in a given team.
	 */
	protected function installCommandTeam($name, $handler, $team)
	{
		$args = func_get_args();
		array_shift($args);
		$preconditions = self::shiftAndReplace($args, function () use($team) {
				$auth = AuthBackend::getInstance();
				if (!in_array($team, $auth->getCurrentUserTeams()))
				{
					throw new Exception('access attempted on team you aren\'t in', E_PERM_DENIED);
				}
			});
		self::installCommandAuthArray($name, $handler, $preconditions);
	}

	/**
	 * Installs a command that requires the user be an admin.
	 */
	protected function installCommandAdmin($name, $handler)
	{
		$preconditions = self::shiftAndReplace(func_get_args(),
		                       array('AuthBackend', 'requireAdministrator'));
		self::installCommandAuthArray($name, $handler, $preconditions);
	}
}
