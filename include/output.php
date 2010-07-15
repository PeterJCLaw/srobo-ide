<?php

class Output
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new Output();
		return self::$singleton;
	}

	private $outputs = array();

	public function getOutput($key)
	{
		if (isset($this->outputs[$key]))
			return $this->outputs[$key];
		else
			return null;
	}

	public function setOutput($key, $value)
	{
		if ($value === null)
		{
			if (isset($this->outputs[$key]))
				unset($this->outputs[$key]);
		}
		else
		{
			$this->outputs[$key] = $value;
		}
	}

	public function removeOutput($key)
	{
		$this->setOutput($key, null);
	}

	public function encodeOutput()
	{
		if (empty($this->outputs))
			return '{}';
		return json_encode($this->outputs);
	}
}
