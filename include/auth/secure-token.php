<?php

abstract class SecureTokenAuth extends AuthBackend
{
	private $user     = null;
	private $teams    = array();
	private $read_only_teams = array();
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
		$all = array_merge($this->teams, $this->read_only_teams);
		$distinct = array_unique($all);
		return $distinct;
	}

	public function canCurrentUserWriteTeam($team)
	{
		$canWrite = in_array($team, $this->teams);
		return $canWrite;
	}

	private function generateToken($username, $password, $teams, $read_only_teams)
	{
		$teams = implode('|', $teams);
		$read_only_teams = implode('|', $read_only_teams);
		$parts = array();
		$parts[0] = microtime(false);
		$parts[1] = $username;
		$parts[2] = $password;
		$parts[3] = $teams;
		$parts[4] = $read_only_teams;
		$parts[5] = openssl_random_pseudo_bytes(mt_rand(2, 8));
		$data = implode(chr(0), $parts);
		return openssl_encrypt($data, self::METHOD, $this->key, self::RAW_OUTPUT, $this->iv);
	}

	public function authUser($username, $password)
	{
		// NB: This is a bit hacky!
		// Don't allow usernames with whitespace
		if (preg_match('/\s/', $username) !== 0)
			return false;
		if (!$this->checkAuthentication($username, $password))
			return false;
		$this->user = $username;
		$this->teams = $this->getTeams($username);
		$this->read_only_teams = $this->getReadOnlyTeams($username);
		$this->tok_next = $this->generateToken($username, $password, $this->teams, $this->read_only_teams);
		return true;
	}

	public function deauthUser()
	{
		$this->user     = null;
		$this->teams    = array();
		$this->read_only_teams = array();
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
		$this->teams = empty($parts[3]) ? array() : explode('|', $parts[3]);
		$this->read_only_teams = empty($parts[4]) ? array() : explode('|', $parts[4]);
		$this->tok_next = $this->generateToken($username, $password, $this->teams, $this->read_only_teams);
		return true;
	}

	abstract public function checkAuthentication($username, $password);
}
