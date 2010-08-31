<?php

abstract class TokenStrategy
{
    abstract public function getAuthToken();
    abstract public function setNextAuthToken($token);
}

function getDefaultTokenStrategy()
{
    $name = Configuration::getInstance()->getConfig("default_token_strategy");
    if (!class_exists($name) || !is_subclass_of($this, $name))
    {
        throw new Exception("the default token strategy is not valid", E_TOKEN_STRAT_CONFIG);
    }
    return new $name();
}
