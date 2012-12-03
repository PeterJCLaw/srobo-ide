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
		$config = Configuration::getInstance();
		$this->ldapManager = new LDAPManager($config->getConfig("ldap.host"), $username, $password);
		return $this->ldapManager->getAuthed();
	}

	public function getTeams($username)
	{
		ide_log(LOG_INFO, "Getting teams for '$username'.");
		$config = Configuration::getInstance();
		$ldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $ldapManager->getGroupsForUser($username);
		$teams = array();

		$groupNamePrefix = $config->getConfig("ldap.team.prefix");
		ide_log(LOG_INFO, "Using prefix '$groupNamePrefix'.");
		foreach ($groups as $group)
		{
			if (stripos($group["cn"], $groupNamePrefix) === 0)
			{
				ide_log(LOG_DEBUG, "Got group '$group[cn]'.");
				$teams[] = substr($group["cn"], strlen($groupNamePrefix));
			}
		}
		ide_log(LOG_INFO, "Got teams: ". print_r($teams, true));

		return $teams;
	}

	public function isCurrentUserAdmin()
	{
		$config = Configuration::getInstance();
		$adminName = $config->getConfig("ldap.admin_group");
		$user = $this->ldapManager->getUser();
		$IDEldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $IDEldapManager->getGroupsForUser($user);
		foreach ($groups as $group)
		{
			if ($group["cn"] == $adminName)
			{
				return true;
			}
		}

		return false;
	}

	public function displayNameForTeam($team)
	{
		$config = Configuration::getInstance();
		$user = $this->ldapManager->getUser();
		$ldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $ldapManager->getGroupsForUser($user);

		foreach ($groups as $group)
		{
			if ($group["cn"] == $config->getConfig("ldap.team.prefix") . $team)
			{
				if (isset($group["description"]))
				{
					return $group["description"];
				}
				else
				{
					return "Team $team";
				}
			}
		}

		return "Team $team";
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
