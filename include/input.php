<?php

class Input
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Input();
		return self::$singleton;
	}

	private $requestModule = '';
	private $requestCommand = '';
	private $inputs = array();

	/**
	 * Gets the name of the requested module
	 */
	public function getRequestModule()
	{
		return $this->requestModule;
	}

	/**
	 * Gets the name of the requested command
	 */
	public function getRequestCommand()
	{
		return $this->requestCommand;
	}

	public static function parseRequest($newRequest)
	{
		$parts = explode('/', $newRequest);
		$requestCommand = array_pop($parts);
		$requestModule = implode('/', $parts);
		return array($requestModule, $requestCommand);
	}

	public function setRequest($newRequest)
	{
		$parts = self::parseRequest($newRequest);
		$this->requestModule  = $parts[0];
		$this->requestCommand = $parts[1];
	}

	public function getInput($key, $optional = false)
	{
		if (isset($this->inputs[$key]))
		{
			return $this->inputs[$key];
		}
		elseif ($optional)
		{
			return null;
		}
		else
		{
			throw new Exception("Input key '$key' failed to exist", E_MALFORMED_REQUEST);
		}
	}

	public function setInput($key, $value)
	{
		if ($value === null)
		{
			if (isset($this->inputs[$key]))
			{
				unset($this->inputs[$key]);
			}
		}
		else
		{
			$this->inputs[$key] = $value;
		}
	}

	public function removeInput($key)
	{
		$this->setInput($key, null);
	}
}
