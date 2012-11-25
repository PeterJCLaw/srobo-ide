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

	private $module_list = null;
	private $modules = array();

	private function moduleNameToClass($mod)
	{
		return transformCase($mod, CASE_SLASHES, CASE_CAMEL_UCFIRST) . 'Module';
	}

	/**
	 * Loads the given module into the local cache.
	 */
	private function importModule($module)
	{
		require_once("modules/$module.php");
		$class = $this->moduleNameToClass($module);
		$this->modules[$module] = new $class();
	}

	/**
	 * Loads the module list from the centeral configuration.
	 * @param flat_list: whether or not to flatten the always/lazy groups
	 *                   to provide a simple list of all known modules.
	 */
	private function getModuleList($flat_list)
	{
		if ($this->module_list == null)
		{
			$config = Configuration::getInstance();
			$this->module_list['always'] = $config->getConfig('modules.always');
			$this->module_list['lazy'] = $config->getConfig('modules.lazy');
		}
		if ($flat_list)
		{
			return array_merge($this->module_list['always'], $this->module_list['lazy']);
		}
		return $this->module_list;
	}

	/**
	 * Imports all modules defined in the config file
	 * This is not generally needed, as modules will be created on-demand.
	 * @param include_lazy: include modules marker as lazy-loadable.
	 */
	public function importModules($include_lazy = false)
	{
		$module_list = $this->getModuleList(false);
		foreach ($module_list['always'] as $module)
		{
			$this->importModule($module);
		}
		if ($include_lazy)
		{
			foreach ($module_list['lazy'] as $module)
			{
				$this->importModule($module);
			}
		}
	}

	/**
	 * Determines if the module exists
	 */
	public function moduleExists($mod)
	{
		$module = $this->getModule($mod);
		$exists = ($module != null);
		return $exists;
	}

	/**
	 * Gets the module for a module class
	 */
	public function getModule($mod)
	{
		$list = $this->getModuleList(true);
		if (!in_array($mod, $list))
		{
			return false;
		}
		if (!isset($this->modules[$mod]))
		{
			$this->importModule($mod);
		}
		return $this->modules[$mod];
	}
}
