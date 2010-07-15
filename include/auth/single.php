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
        if ($this->authed)
        {
            return null;
        }
        else
        {
            return $this->user;
        }

    }

    public function authUser($authtoken)
    {
        if (isset($authtoken["user"]) && isset($authtoken["password"]))
        {
            $this->authed = true;
            return 1;
        }
        else
        {
            return 0;
        }
    }

    public function getNextAuthToken()
    {
        return 1;
    }

    public function deauthUser($authtoken)
    {
        if ($authtoken != 1)
        {
            throw new Exception("invalid deauth token", 0x34);
        }

        $this->authed = false;
    }

}
