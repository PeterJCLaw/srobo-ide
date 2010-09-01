<?php

require_once("tokenstrategy.php");

class CookieStrategy extends TokenStrategy
{
    /**
     * the name for the cookie
     */
    const COOKIENAME = "token";

    public function getAuthToken()
    {
        return $_COOKIE[self::COOKIENAME];
    }

    public function setNextAuthToken($token)
    {
        $expiry = Configuration::getInstance()->getConfig("cookie_expiry_time");
        if ($expiry == null)
        {
            throw new Exception("no cookie expiry time found in config file", E_NO_EXPIRY_TIME);
        }
        setcookie(
                  self::COOKIENAME,
                  $token,
                  time() + (int)$expiry
                 );
    }

}
