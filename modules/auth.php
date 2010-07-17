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
				throw new Exception("failed to validate auth token", E_BAD_AUTH_TOKEN);
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
			throw new Exception("you are already authenticated", E_AUTH_FAILED);
		$username = $input->getInput('username');
		$password = $input->getInput('password');
		if (!$username || !$password)
			throw new Exception("username/password not provided", E_AUTH_FAILED);
		if (!$this->authModule->authUser($username, $password))
			throw new Exception("authentication failed", E_AUTH_DENIED);
		$output->setOutput('auth-token', $this->authModule->getNextAuthToken());
		$output->setOutput('display-name', $this->authModule->displayNameForUser($username));
		return true;
	}

	public function deauthenticate()
	{
		$input    = Input::getInstance();
		$output   = Output::getInstance();
		if ($this->authModule->getCurrentUser() === null)
			throw new Exception("you are not authenticated", E_AUTH_FAILED);
		$this->authModule->deauthUser();
		$output->removeOutput('auth-token');
		return true;
	}
}
