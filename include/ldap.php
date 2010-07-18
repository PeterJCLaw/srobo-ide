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
			$saneGroups = array();
			for ($i = 0; $i < $results["count"]; $i++)
			{
				$group = $results[$i];
				$saneGroup = array();
				$saneGroup["cn"] = $group["cn"][0];
				$saneGroup["description"] = $group["description"][0];
				$saneGroups[] = $saneGroup;
			}

			return $saneGroups;

		}
		else
		{
			throw new Exception("cannot search groups, not authed to ldap");
		}

	}

	public function getUserInfo($user)
	{
		if ($this->authed)
		{
			 $resultsID = ldap_search($this->connection, "uid=$user,ou=users,o=sr", "uid=*");
             $results = ldap_get_entries($this->connection, $resultsID);
             $saneResults = array();
             $saneResults["cn"] = $results[0]["cn"][0];
             $saneResults["email"] = $results[0]["mail"][0];
             $saneResults["username"] = $results[0]["uid"][0];
             return $saneResults;

		}

	}

}

