<?php

class LDAPManager
{
	private $connection = null;
	private $authed = false;
	private $user;

	public function __construct($host, $user, $pass)
	{
		$this->connection = ldap_connect($host);
		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
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
		$groups = $this->groupCnSearch($ldap_filter);
		return $groups;
	}

	/**
	 * Get all the groups that match the given filter.
	 * @param filter: filter of group names to search for, applied in LDAP.
	 */
	public function getGroups($filter)
	{
		$ldap_filter = "cn=$filter";
		$groups = $this->groupCnSearch($ldap_filter);
		return $groups;
	}

	/**
	 * Get "cn"s of all the groups that match the given filter.
	 * @param filter: filter of items to search for, applied in LDAP.
	 */
	private function groupCnSearch($ldap_filter)
	{
		$attrs = array('cn');
		$results = $this->search('ou=groups,o=sr', $ldap_filter, $attrs);
		$groups = $this->extractSingleAttr($results, 'cn');
		return $groups;
	}

	/**
	 * Perform a search against our LDAP connection.
	 *
	 * @param base_dn: the base DN to search within.
	 * @param filter: filter of items to search for, applied in LDAP.
	 * @param attrs: attributes to fetch.
	 * @returns: The matching entries.
	 */
	private function search($base_dn, $ldap_filter, $attrs)
	{
		if (!$this->authed)
			throw new Exception('cannot search ldap, not authed', E_LDAP_NOT_AUTHED);
		if ($this->user != 'ide')
			throw new Exception('cannot search ldap, not the IDE user', E_LDAP_NOT_AUTHED);

		$resultsID = ldap_search($this->connection, $base_dn, $ldap_filter, $attrs, 0, 0);
		$results = ldap_get_entries($this->connection, $resultsID);

		return $results;
	}

	/**
	 * Given the results of an LDAP search, extract a single attribute
	 * from each result and return an array of just those attribtue values.
	 *
	 * @param results: the result entries from a search.
	 * @param attr: the name of the attribute to extract.
	 * @returns: an array containing the value of the given attribute
	 *           from each of the results. Result ordering is preserved.
	 */
	private function extractSingleAttr($results, $attr)
	{
		$flattened = array();
		for ($i = 0; $i < $results['count']; $i++)
		{
			$item = $results[$i];
			$flattened[] = $item[$attr][0];
		}

		return $flattened;
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

