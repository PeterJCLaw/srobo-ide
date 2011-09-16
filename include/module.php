<?php

abstract class Module
{
	private $commandHandlers = array();

	protected function installCommand($name, $handler)
	{
		$baseFunction = $handler;
		$preconditions = func_get_args();
		array_splice($preconditions, 0, 2);
		foreach ($preconditions as $precondition)
		{
			$baseFunction = function() use ($baseFunction, $precondition) {
				call_user_func($precondition);
				return call_user_func($baseFunction);
			};
		}
		$this->commandHandlers[$name] = $baseFunction;
	}

	/**
	 * Dispatches a command to the module
	 */
	public function dispatchCommand($name)
	{
		if (isset($this->commandHandlers[$name]))
			return call_user_func($this->commandHandlers[$name]);
		else
			return false;
	}
}

/**
 * Manages all modules within the ide
 */
class ModuleManager
{
	private static $singleton = null;

	/**
	 * Gets the module manager instance
	 */
	public static function getInstance()
	{
		if (self::$singleton == null)
			self::$singleton = new ModuleManager();
		return self::$singleton;
	}

	private $modules = array();

	private function moduleNameToClass($mod)
	{
		return transformCase($mod, CASE_SLASHES, CASE_CAMEL_UCFIRST) . 'Module';
	}

	/**
	 * Imports all modules defined in the config file
	 */
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

	/**
	 * Determines if the module exists
	 */
	public function moduleExists($mod)
	{
		return isset($this->modules[$mod]);
	}

	/**
	 * Gets the module for a module class
	 */
	public function getModule($mod)
	{
		return $this->modules[$mod];
	}
}
