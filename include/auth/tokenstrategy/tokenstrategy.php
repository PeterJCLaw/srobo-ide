<?php

abstract class TokenStrategy
{
    abstract public function getAuthToken();
    abstract public function setNextAuthToken($token);
}

function getDefaultTokenStrategy()
{
    return new IOTokenStrategy();
}
