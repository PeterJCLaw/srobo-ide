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
		$config = Configuration::getInstance();
		$ldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $ldapManager->getGroupsForUser($username);
		$teams = array();

		foreach ($groups as $group)
		{
            $groupNamePrefix = $config->getConfig("ldap.team.prefix");
			if (stripos($group["cn"], $groupNamePrefix) === 0)
			{
				$teams[] = (int)substr($group["cn"], strlen($groupNamePrefix));
			}
		}

		return $teams;
	}

	public function isCurrentUserAdmin()
	{
		return false;
	}

	public function displayNameForGroup($group)
	{
		$user = $this->ldapManager->getUser();
		$ldapManager = new LDAPManager($config->getConfig("ldap.host"), "ide", $config->getConfig("ldap.ideuser.password"));
		$groups = $this->ldapManager->getGroupsForUser($user);

		foreach ($groups as $group)
		{
			if ($group["cn"] == "team"+$group)
			{
				return $group["description"];
			}
		}

		return $group;
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

	public function setTeamDesc($team, $name)
	{
		$userTeams = $this->getTeams($this->getCurrentUser());
		$prefix = Configuration::getInstance()->getConfig('ldap.team.prefix');
		if(!in_array($team, $userTeams))
		{
			throw new Exception('You cannot change the name of a team that you are not in!', E_PERM_DENIED);
		}
		else
		{
			$this->ldapManager->changeTeamDesc($prefix.$team, $name);
		}
	}
}
