<?php

class AuthModule extends Module
{
	private $authModule;

	public function __construct()
	{
		$this->authModule = AuthBackend::getInstance();
		$input  = Input::getInstance();
		$output = Output::getInstance();
		if ($in = $input->getInput('auth-token'))
		{
			$tok = $this->authModule->validateAuthToken($in);
			if (!$tok)
			{
				throw new Exception("failed to validate auth token", 3);
			}
			$next = $this->authModule->getNextAuthToken();
			$output->setOutput('auth-token', $next);
		}
		$this->installCommand('authenticate', array($this, 'authenticate'));
		$this->installCommand('deauthenticate', array($this, 'deauthenticate'));
	}

	public function authenticate()
	{
		$input    = Input::getInstance();
		$output   = Output::getInstance();
		if ($this->authModule->getCurrentUser() !== null)
			throw new Exception("you are already authenticated", 5);
		$username = $input->getInput('username');
		$password = $input->getInput('password');
		if (!$username || !$password)
			throw new Exception("username/password not provided", 5);
		if (!$this->authModule->authUser($username, $password))
			throw new Exception("authentication failed", 6);
		$output->setOutput('auth-token', $this->authModule->getNextAuthToken());
	}

	public function deauthenticate()
	{
		$input    = Input::getInstance();
		$output   = Output::getInstance();
		if ($this->authModule->getCurrentUser() === null)
			throw new Exception("you are not authenticated", 5);
		$this->authModule->deauthUser();
		$output->removeOutput('auth-token');
	}
}
