<?php

require_once('include/auth/secure-token.php');

class SingleAuth extends SecureTokenAuth
{
    /*private $authed = false;
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

    public function getCurrentUserTeams()
    {
        if ($this->authed)
        {
            return Configuration::getInstance()->getConfig("user.default.teams");
        }
        else
        {
            return array();
        }
    }*/

	public function __construct()
	{
		parent::__construct();
	}

	public function checkAuthentication($username, $password)
	{
		return $username != null && $password != null;
	}

	public function getTeams($username)
	{
		return Configuration::getInstance()->getConfig("user.default.teams");
	}

	public function displayNameForGroup($group)
	{
		return $group;
	}

	public function displayNameForUser($user)
	{
		return $user;
	}

	public function emailForUser($user)
	{
		return $user . "@" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com');
	}
}
