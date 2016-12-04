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
	// uses the user's credentials
	private $ldapManager;

	// uses the IDE credentials
	private $ideLdapManager;

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

		if (self::isEmailAddress($username))
		{
			$username = $this->usernameFromEmailAddress($username);
		}

		$config = Configuration::getInstance();
		$this->ldapManager = new LDAPManager($config->getConfig("ldap.host"), $username, $password);
		return $this->ldapManager->getAuthed();
	}

	private static function isEmailAddress($maybeEmailAddress)
	{
		return strpos($maybeEmailAddress, '@') !== false;
	}

	private function usernameFromEmailAddress($email)
	{
		$s_email = ldap_escape($email, null, LDAP_ESCAPE_FILTER);

		$ldapManager = $this->getIDELDAPManager();
		$usernames = $ldapManager->getUsernamesForEmail($s_email);
		if (count($usernames) != 1)
		{
			return null;
		}
		else
		{
			return $usernames[0];
		}
	}

	protected function normaliseUsername($username)
	{
		if (self::isEmailAddress($username))
		{
			return $this->usernameFromEmailAddress($username);
		}

		if (!$this->ldapManager->getAuthed())
		{
			throw new Exception('Cannot normalise username without LDAP authentication.', E_INTERNAL_ERROR);
		}

		$info = $this->ldapManager->getUserInfo($username);
		//var_dump($info);
		return $info['username'];
	}

	private static function stripPrefix($array, $prefix)
	{
		return array_map(function($str) use ($prefix) {
			return substr($str, strlen($prefix));
		}, $array);
	}

	public function getWritableTeams($username)
	{
		ide_log(LOG_INFO, "Getting teams for '$username'.");
		$config = Configuration::getInstance();
		$groupNamePrefix = $config->getConfig("ldap.team.prefix");

		$ldapManager = $this->getIDELDAPManager();
		$groups = $ldapManager->getGroupsForUser($username, $groupNamePrefix.'*');
		$teams = self::stripPrefix($groups, $groupNamePrefix);

		ide_log(LOG_INFO, "Got teams: ". print_r($teams, true));

		return $teams;
	}

	public function getReadableTeams($username)
	{
		ide_log(LOG_INFO, "Getting read-only teams for '$username'.");
		$config = Configuration::getInstance();
		$readAllGroup = $config->getConfig('ldap.read_all_group');
		if (empty($readAllGroup) || !$this->inGroup($username, $readAllGroup))
		{
			return array();
		}

		$groupNamePrefix = $config->getConfig("ldap.team.prefix");

		$ldapManager = $this->getIDELDAPManager();
		$groups = $ldapManager->getGroups($groupNamePrefix.'*');

		$teams = self::stripPrefix($groups, $groupNamePrefix);

		ide_log(LOG_INFO, "Got teams: ". print_r($teams, true));

		return $teams;
	}

	private function getIDELDAPManager()
	{
		if ($this->ideLdapManager == null)
		{
			$config = Configuration::getInstance();
			$host = $config->getConfig("ldap.host");
			$pass = $config->getConfig("ldap.ideuser.password");
			$this->ideLdapManager = new LDAPManager($host, "ide", $pass);
		}
		return $this->ideLdapManager;
	}

	/**
	 * Returns whether or not the user is in the requested group.
	 * @param user: The user to check membership for.
	 * @param group: The group to search for.
	 */
	private function inGroup($user, $group)
	{
		$ldapManager = $this->getIDELDAPManager();
		$groups = $ldapManager->getGroupsForUser($user, $group);
		// should either be 0 or 1 responses...
		$inGroup = count($groups) > 0;
		return $inGroup;
	}

	public function isCurrentUserAdmin()
	{
		$config = Configuration::getInstance();
		$adminName = $config->getConfig("ldap.admin_group");
		$user = $this->ldapManager->getUser();
		$isAdmin = $this->inGroup($user, $adminName);
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
