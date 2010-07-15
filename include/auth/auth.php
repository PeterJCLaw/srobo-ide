<?php
abstract class AuthBackend {
    abstract public function getCurrentUser();
    abstract public function authUser($authtoken);
    abstract public function deauthUser($authtoken);
    abstract public function getNextAuthToken();
}
