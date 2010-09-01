<?php

/**
 * Base class for token strategies
 *
 * There was some lengthy discussion about the best way to handle tokens, this
 * can be used to easily manufacture and swap token strategies based on what is
 * perceived to be best at the time
 *
 * @author Sam Phippen <samphippen@googlemail.com>
 */
abstract class TokenStrategy
{
    /**
     * Returns the current auth token supplied on the request
     */
    abstract public function getAuthToken();

    /**
     * Sets the auth token that should be passed on the next request
     */
    abstract public function setNextAuthToken($token);

    /**
     * Removes the auth token such that the client can't auth again
     */
    abstract public function removeAuthToken();
}

/**
 * returns an object for the default strategy for handling auth tokens
 */
function getDefaultTokenStrategy()
{
    $name = Configuration::getInstance()->getConfig("default_token_strategy");
    if (!class_exists($name) || !is_subclass_of($name, 'TokenStrategy'))
    {
        throw new Exception("The default token strategy ($name) is not valid", E_TOKEN_STRAT_CONFIG);
    }
    return new $name();
}
