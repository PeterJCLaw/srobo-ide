<?php

abstract class AuthBackend
{
    abstract public function getCurrentUser();
    abstract public function authUser($authToken);
    abstract public function deauthUser($authToken);
    abstract public function getNextAuthToken();
}
