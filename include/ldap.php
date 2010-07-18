<?php

class LDAPManager
{
	private $connection = null;
	private $authed = FALSE;

	public function __construct($host, $user, $pass)
	{
		$this->connection = ldap_connect($host);
		$dn = "uid=$user,ou=users,o=sr";
		$this->authed = ldap_bind($this->connection, $dn, $pass);

	}

	public function getGroupsForUser($user)
	{
		if ($this->authed)
		{
			//do an ldap search
			$resultsID = ldap_search($this->connection,"ou=groups,o=sr", "memberUid=$user");
			$results = ldap_get_entries($this->connection , $resultsID);
			print_r($results);

		}
		else
		{
			throw new Exception("cannot search groups, not authed to ldap");
		}

	}

}

