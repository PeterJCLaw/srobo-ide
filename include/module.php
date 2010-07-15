<?php

abstract class Module
{
	private $commandHandlers = array();

	protected function installCommand($name, $fn)
	{
		$this->commandHandlers[$name] = $fn;
	}

	public function dispatchCommand($name)
	{
		if (isset($this->commandHandlers[$name]))
			return $this->commandHandlers[$name]();
		else
			return false;
	}
}

class ModuleManager
{
	private static $singleton = null;

	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new ModuleManager();
		return self::$singleton;
	}

	private $modules = array();

	private function moduleNameToClass($mod)
	{
		$parts = explode('/', $mod);
		$parts = array_map('ucfirst', $parts);
		return implode('', $parts) . 'Module';
	}

	public function importModules()
	{
		$config = Configuration::getInstance();
		$module_list = $config->getConfig('modules');
		foreach ($module_list as $module)
		{
			require_once("modules/$module.php");
			$class = $this->moduleNameToClass($module);
			$this->modules[$module] = new $class();
		}
	}

	public function moduleExists($mod)
	{
		return isset($this->modules[$mod]);
	}

	public function getModule($mod)
	{
		return $this->modules[$mod];
	}
}
