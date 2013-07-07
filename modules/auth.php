<?php

/**
 * Module for handling client side auth requests
 *
 * commands are:
 * authenticate (username,password) -> (display_name)
 * deauthenticate (void) -> (void)
 */
class AuthModule extends Module
{
	private $authModule;

	public function __construct()
	{
		$this->authModule = AuthBackend::getInstance();
		$ts = getDefaultTokenStrategy();
		$in = $ts->getAuthToken();
		if ($in)
		{
			$tok = $this->authModule->validateAuthToken($in);
			if (!$tok)
			{
				$ts->setNextAuthToken();
				throw new Exception('failed to validate auth token', E_BAD_AUTH_TOKEN);
			}
			$next = $this->authModule->getNextAuthToken();
			$ts->setNextAuthToken($next);
		}
		$this->installCommand('authenticate', array($this, 'authenticate'));
		$this->installCommand('deauthenticate', array($this, 'deauthenticate'));
	}

	/**
	 * Authenticates a user
	 */
	public function authenticate()
	{
		$input    = Input::getInstance();
		$output   = Output::getInstance();
		if ($this->authModule->getCurrentUserName() !== null)
		{
			throw new Exception('you are already authenticated', E_AUTH_FAILED);
		}
		$username = $input->getInput('username');
		$password = $input->getInput('password');
		if (!$username || !$password)
		{
			throw new Exception('username/password not provided', E_AUTH_FAILED);
		}
		// SR standard is lowercase, but LDAP is insensitive, so this might otherwise give odd results.
		$username = strtolower($username);
		if (!$this->authModule->authUser($username, $password))
		{
			throw new Exception('authentication failed', E_AUTH_DENIED);
		}
		getDefaultTokenStrategy()->setNextAuthToken($this->authModule->getNextAuthToken());
		$output->setOutput('display-name', $this->authModule->displayNameForUser($username));
		return true;
	}


	/**
	 * Deauthenticates a user
	 */
	public function deauthenticate()
	{
		$input    = Input::getInstance();
		$output   = Output::getInstance();
		if ($this->authModule->getCurrentUserName() !== null)
		{
			$this->authModule->deauthUser();
			getDefaultTokenStrategy()->removeAuthToken();
		}
		return true;
	}
}
