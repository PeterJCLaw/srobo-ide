<?php

abstract class Module
{
	private $commandHandlers = array();
	private $_initDone = false;

	protected function installCommand($name, $fn)
	{
		$this->commandHandlers[$name] = $fn;
	}

	/**
	 * Initialises the module so that it's ready to have a command
	 * dispatched to it.
	 * This is intended to facilitate work that should be done for all
	 * the commands that a module supports, but which shouldn't be done
	 * in the constructor, such as verifying a user.
	 */
	protected function initModule()
	{
	}

	private function _initModule()
	{
		if (!$this->_initDone)
		{
			$this->initModule();
			$this->_initDone = true;
		}
	}

	/**
	 * Dispatches a command to the module
	 */
	public function dispatchCommand($name)
	{
		$this->_initModule();
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
