<?php

abstract class Module
{
	private $commandHandlers = array();

	/**
	 * Installs a command to be run against an enpoint.
	 * @param name: The endpoint to register against.
	 * @param handler: The callback to use for the given endpoint.
	 * @param preconditions...: Additional arguments are taken, in order,
	 *           as a set of condition callbacks to run before dispatching
	 *           the handler.
	 */
	protected function installCommand($name, $handler)
	{
		$preconditions = func_get_args();
		array_splice($preconditions, 0, 2);
		$this->installCommandArray($name, $handler, $preconditions);
	}

	/**
	 * Installs a command to be run against an enpoint.
	 * @param name: The endpoint to register against.
	 * @param handler: The callback to use for the given endpoint.
	 * @param preconditions: A set of condition callbacks to run before dispatching the handler.
	 *           The callbacks are executed in the order from the array.
	 */
	protected function installCommandArray($name, $handler, $preconditions)
	{
		$baseFunction = $handler;
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
			$instance = new $class();
			$this->addModule($module, $instance);
		}
	}

	/**
	 * Adds a single module
	 */
	public function addModule($name, $instance)
	{
		if (!$instance instanceof Module)
			throw new Exception('adding a non-module as a module', E_INTERNAL_ERROR);
		$this->modules[$name] = $instance;
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
