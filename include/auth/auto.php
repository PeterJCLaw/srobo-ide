<?php

require_once('include/auth/single.php');

class AutoAuth extends SingleAuth
{
	public function __construct()
	{
		$this->authUser(Configuration::getInstance()->getConfig('user.default'), '');
		getDefaultTokenStrategy()->setNextAuthToken($this->getNextAuthToken());
	}

	public function checkAuthentication($username, $password)
	{
		return true;
	}
}
