<?php

abstract class SecureTokenAuth extends AuthBackend
{
	private $user     = null;
	private $teams    = array();
	private $key      = null;
	private $tok_next = null;

	const METHOD = 'AES128';
	const TIMEOUT = 600.0;

	public function __construct()
	{
		$config = Configuration::getInstance();
		$keyfile = $config->getConfig('keyfile');
		if (!$keyfile)
			$keyfile = '/tmp/ide-key.key';
		if (!file_exists($keyfile))
		{
			$this->key = openssl_random_pseudo_bytes(16);
			file_put_contents($keyfile, base64_encode($this->key));
		}
		else
		{
			$this->key = base64_decode(file_get_contents($keyfile));
		}
	}

	public function getCurrentUser()
	{
		return $this->user;
	}

	public function getCurrentUserTeams()
	{
		return $this->teams;
	}
	
	private function generateToken($username, $password, $teams)
	{
		$teams = implode('|', $teams);
		$parts = array();
		$parts[0] = microtime(false);
		$parts[1] = $username;
		$parts[2] = $password;
		$parts[3] = $teams;
		$parts[4] = openssl_random_pseudo_bytes(mt_rand(2, 8));
		$data = implode(chr(0), $parts);
		return openssl_encrypt($data, self::METHOD, $this->key);
	}

	public function authUser($username, $password)
	{
		if (!$this->checkAuthentication($username, $password))
			return false;
		$this->user = $username;
		$this->teams = $this->getTeams($username);
		$this->tok_next = $this->generateToken($username, $password, $this->teams);
		return true;
	}

	public function deauthUser()
	{
		$this->user     = null;
		$this->teams    = array();
		$this->tok_next = null;
		$this->password = null;
	}

    public function getNextAuthToken()
    {
    	return $this->tok_next;
    }
    
    public function validateAuthToken($token)
    {
    	$decrypted = openssl_decrypt($token, self::METHOD, $this->key);
    	$parts = explode(chr(0), $decrypted);
    	$time = (float)$parts[0];
    	if ($time - microtime(true) > self::TIMEOUT*1000000.0)
    	{
    		return false;
    	}
    	$username = $parts[1];
    	$password = $parts[2];
    	if (!$this->checkAuthentication($username, $password))
    	{
    		return false;
    	}
    	$this->user = $username;
    	$this->teams = explode('|', $parts[3]);
    	$this->tok_next = $this->generateToken($username, $password, $this->teams);
    	return true;
    }

	abstract public function checkAuthentication($username, $password);
	abstract public function getTeams($username);
}
