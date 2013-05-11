<?php

require_once("include/auth/secure-token.php");
require_once("include/ldap.php");
/**
 * A class for doing LDAP authentication using the secure token system
 * implements SecureTokenAuth
 * @author Sam Phippen <samphippen@googlemail.com>
 */
class LDAPAuth extends SecureTokenAuth
{
	private $ldapManager;

	public function __construct()
	{
		parent::__construct();
	}

	public function checkAuthentication($username, $password)
	{
		// empty usernames aren't valid in LDAP
		if (empty($username))
		{
			return false;
		}
		$config = Configuration::getInstance();
		$this->ldapManager = new LDAPManager($config->getConfig("ldap.host"), $username, $password);
		return $this->ldapManager->getAuthed();
	}

	public function getTeams($username)
	{
		ide_log(LOG_INFO, "Getting teams for '$username'.");
		$config = Configuration::getInstance();
		$ldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groupNamePrefix = $config->getConfig("ldap.team.prefix");
		$groups = $ldapManager->getGroupsForUser($username, $groupNamePrefix.'*');
		$teams = array();

		ide_log(LOG_INFO, "Using prefix '$groupNamePrefix'.");
		foreach ($groups as $group)
		{
			ide_log(LOG_DEBUG, "Got group '$group'.");
			$teams[] = substr($group, strlen($groupNamePrefix));
		}
		ide_log(LOG_INFO, "Got teams: ". print_r($teams, true));

		return $teams;
	}

	/**
	 * Returns whether or not the user is in the requested group.
	 * @param group: The group to search for.
	 */
	private function inGroup($group)
	{
		$config = Configuration::getInstance();
		$user = $this->ldapManager->getUser();
		$IDEldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $IDEldapManager->getGroupsForUser($user, $group);
		// should either be 0 or 1 responses...
		$inGroup = count($groups) > 0;
		return $inGroup;
	}

	public function isCurrentUserAdmin()
	{
		$config = Configuration::getInstance();
		$adminName = $config->getConfig("ldap.admin_group");
		$isAdmin = $this->inGroup($adminName);
		return $isAdmin;
	}

	public function displayNameForUser($user)
	{
		if ($this->ldapManager->getAuthed())
		{
			$info = $this->ldapManager->getUserInfo($user);
			return $info["name.first"] . " " . $info["name.last"];
		}
		else
		{
			throw new Exception("you aren't authed to ldap", E_LDAP_NOT_AUTHED);
		}
	}

	public function emailForUser($user)
	{
		if ($this->ldapManager->getAuthed())
		{
			$info = $this->ldapManager->getUserInfo($user);
			return $info["email"];
		}
		else
		{
			throw new Exception("you aren't authed to ldap", E_LDAP_NOT_AUTHED);
		}
	}
}
