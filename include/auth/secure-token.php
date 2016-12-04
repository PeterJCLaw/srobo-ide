<?php

abstract class SecureTokenAuth extends AuthBackend
{
	private $user     = null;
	private $writable_teams	= array();
	private $readable_teams = array();
	private $key      = null;
	private $iv       = null;
	private $tok_next = null;

	const METHOD = 'AES128';
	const TIMEOUT = 600.0;
	const RAW_OUTPUT = false;

	public function __construct()
	{
		$config = Configuration::getInstance();
		$keyfile = $config->getConfig('keyfile');
		if (!$keyfile)
			$keyfile = '/tmp/ide-key.key';
		if (!file_exists($keyfile))
		{
			$this->key = openssl_random_pseudo_bytes(16);
			$this->iv = openssl_random_pseudo_bytes(16);
			file_put_contents($keyfile, base64_encode($this->key.$this->iv));
		}
		else
		{
			$decoded = base64_decode(file_get_contents($keyfile));
			$this->key = substr($decoded, 0, 16);
			$this->iv = substr($decoded, 16);
		}
	}

	public function getCurrentUserName()
	{
		return $this->user;
	}

	public function getCurrentUserTeams()
	{
		$all = array_merge($this->writable_teams, $this->readable_teams);
		$distinct = array_unique($all);
		return $distinct;
	}

	public function canCurrentUserWriteTeam($team)
	{
		$canWrite = in_array($team, $this->writable_teams);
		return $canWrite;
	}

	private function generateToken($username, $password, $writable_teams, $readable_teams)
	{
		$writable_teams = implode('|', $writable_teams);
		$readable_teams = implode('|', $readable_teams);
		$parts = array();
		$parts[0] = microtime(false);
		$parts[1] = $username;
		$parts[2] = $password;
		$parts[3] = $writable_teams;
		$parts[4] = $readable_teams;
		$parts[5] = openssl_random_pseudo_bytes(mt_rand(2, 8));
		$data = implode(chr(0), $parts);
		return openssl_encrypt($data, self::METHOD, $this->key, self::RAW_OUTPUT, $this->iv);
	}

	/**
	 * Helper to normalise a username if needed.
	 * Called after the user is known to be authenticated.
	 * Can optionally be overridden, default behaviour is no change.
	 */
	protected function normaliseUsername($username)
	{
		return $username;
	}

	public function authUser($username, $password)
	{
		// NB: This is a bit hacky!
		// Don't allow usernames with whitespace
		if (preg_match('/\s/', $username) !== 0)
			return false;
		if (!$this->checkAuthentication($username, $password))
			return false;
		$username = $this->normaliseUsername($username);
		$this->user = $username;
		$this->writable_teams = $this->getWritableTeams($username);
		$this->readable_teams = $this->getReadableTeams($username);
		$this->tok_next = $this->generateToken($username, $password, $this->writable_teams, $this->readable_teams);
		return true;
	}

	public function deauthUser()
	{
		$this->user     = null;
		$this->writable_teams = array();
		$this->readable_teams = array();
		$this->tok_next = null;
		$this->password = null;
	}

	public function getNextAuthToken()
	{
		return $this->tok_next;
	}

	public function validateAuthToken($token)
	{
		$decrypted = openssl_decrypt($token, self::METHOD, $this->key, self::RAW_OUTPUT, $this->iv);
		$parts = explode(chr(0), $decrypted);
		$time = (float)$parts[0];
		if ($time - microtime(true) > self::TIMEOUT*1000000.0)
		{
			return false;
		}
		$username = empty($parts[1]) ? '' : $parts[1];
		$password = empty($parts[2]) ? '' : $parts[2];
		if (!$this->checkAuthentication($username, $password))
		{
			return false;
		}
		$this->user = $username;
		$this->writable_teams = empty($parts[3]) ? array() : explode('|', $parts[3]);
		$this->readable_teams = empty($parts[4]) ? array() : explode('|', $parts[4]);
		$this->tok_next = $this->generateToken($username, $password, $this->writable_teams, $this->readable_teams);
		return true;
	}

	abstract public function checkAuthentication($username, $password);
}
