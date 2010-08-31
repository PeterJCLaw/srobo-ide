<?php

require_once("tokenstrategy.php");

class CookieStrategy extends TokenStrategy
{
    /**
     * the name for the cookie
     */
    const COOKIENAME = "token";

    /**
     * Default validity is 30 days
     *
     * >>> 3600*24*30
     * 2592000
     */
    const DEFAULT_COOKIE_VALIDITY = 2592000;

    public function getAuthToken()
    {
        return $_COOKIE[self::COOKIENAME];
    }

    public function setNextAuthToken($token)
    {
        setcookie(
                  self::COOKIENAME,
                  $token,
                  time() + self::DEFAULT_COOKIE_VALIDITIY
                 );
    }

}
