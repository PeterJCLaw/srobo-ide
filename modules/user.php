<?php

class UserModule extends Module
{
	public function __construct()
	{
		$this->installCommand('info', array($this, 'getInfo'));
	}

	public function getInfo()
	{
		$output = Output::getInstance();
		$auth   = AuthBackend::getInstance();
		if (!($username = $auth->getCurrentUser()))
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
		$output->setOutput('display-name', $auth->displayNameForUser($username));
		$output->setOutput('email', $auth->emailForUser($username));
		$output->setOutput('teams', $auth->getCurrentUserTeams());
		$output->setOutput('is-admin', false);
	}
}
