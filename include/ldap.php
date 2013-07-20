<?php

class LDAPManager
{
	private $connection = null;
	private $authed = false;
	private $user;

	public function __construct($host, $user, $pass)
	{
		$this->connection = ldap_connect($host);
		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION,3);
		$dn = "uid=$user,ou=users,o=sr";
		$this->authed = ldap_bind($this->connection, $dn, $pass);
		$this->user = $user;
	}

	/**
	 * Get the groups that the user is in, optionally pre-filtered.
	 * @param user: the uid of the user to lookup.
	 * @param filter: optional filter to the groups, applied in LDAP.
	 */
	public function getGroupsForUser($user, $filter = null)
	{
		$ldap_filter = "memberUid=$user";
		if ($filter != null)
		{
			$ldap_filter = "(&($ldap_filter)(cn=$filter))";
		}
		$groups = $this->cnSearch($ldap_filter);
		return $groups;
	}

	/**
	 * Get "cn"s of all the items that match the given filter.
	 * @param filter: filter of items to search for, applied in LDAP.
	 */
	private function cnSearch($ldap_filter)
	{
		if (!$this->authed)
			throw new Exception('cannot search groups, not authed to ldap', E_LDAP_NOT_AUTHED);
		if ($this->user != 'ide')
			throw new Exception('cannot search groups, not the IDE user', E_LDAP_NOT_AUTHED);
		//do an ldap search
		$attrs = array('cn');
		$resultsID = ldap_search($this->connection,'ou=groups,o=sr', $ldap_filter, $attrs);
		$results = ldap_get_entries($this->connection , $resultsID);
		$saneGroups = array();
		for ($i = 0; $i < $results['count']; $i++)
		{
			$group = $results[$i];
			$saneGroups[] = $group['cn'][0];
		}

		return $saneGroups;
	}

	public function getUserInfo($user)
	{
		if ($this->authed)
		{
			$resultsID = ldap_search($this->connection, "uid=$user,ou=users,o=sr", 'uid=*');
			$results = ldap_get_entries($this->connection, $resultsID);
			$saneResults = array();
			$saneResults['email']      = $results[0]['mail'][0];
			$saneResults['username']   = $results[0]['uid'][0];
			$saneResults['name.first'] = $results[0]['cn'][0];
			$saneResults['name.last']  = $results[0]['sn'][0];
			return $saneResults;
		}
		else
		{
			throw new Exception('cannot search userinfo, not authed to ldap', E_LDAP_NOT_AUTHED);
		}
	}

	public function getAuthed()
	{
		return $this->authed;
	}

	public function getUser()
	{
		return $this->user;
	}

}

