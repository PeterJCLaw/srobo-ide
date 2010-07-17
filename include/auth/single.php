<?php

class SingleAuth extends AuthBackend
{
    private $authed = false;
    private $user;

    public function __construct()
    {
        $config = Configuration::getInstance();
        $this->user = $config->getConfig("user.default");
    }

    public function getCurrentUser()
    {
        if (!$this->authed)
        {
            return null;
        }
        else
        {
            return $this->user;
        }
    }

    public function authUser($username, $password)
    {
        if ($username !== null && $password !== null)
        {
            $this->authed = true;
            return true;
        }
        else
        {
            return false;
        }
    }

	public function validateAuthToken($token)
	{
		if ($token === 1)
		{
			$this->authed = true;
			return true;
		}
		else
		{
			return false;
		}
	}

    public function getNextAuthToken()
    {
        return 1;
    }

    public function deauthUser()
    {
    	$this->authed = false;
    }

    public function getCurrentUserGroups()
    {
        if ($this->authed)
        {
            return Configuration::getInstance()->getConfig("user.default.groups");
        }
        else
        {
            return array();
        }
    }

	public function displayNameForGroup($group)
	{
		return $group;
	}

	public function displayNameForUser($user)
	{
		return $user;
	}
}
