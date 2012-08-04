<?php

/**
 * Module for doing stuff periodically.
 * Other modules are expected to subscribe to this module via the module manager.
 */
class CronModule extends Module
{
	private $jobs;

	public function __construct()
	{
		$this->installCommand('cron', array($this, 'cron'));
		$this->jobs = array();
	}

	/**
	 * Subscribes a given callback against a name.
	 * The name should be that of the registering module.
	 * Modules are not expected to register more than one callback.
	 * Callbacks should not modify Output, but should return a boolean status.
	 */
	public function subscribe($name, $callback)
	{
		$this->jobs[$name] = $callback;
	}

	public function remove($name)
	{
		unset($this->jobs[$name]);
	}

	/**
	 * Run all the subscribed cron jobs, and return details about which worked.
	 */
	public function cron()
	{
		$output = Output::getInstance();
		$details = array();
		$overall = true;

		foreach ($this->jobs as $name => $callback)
		{
			$details[$name] = array();
			try
			{
				$result = call_user_func($callback);
			}
			catch (Exception $e)
			{
				$result = false;
				$details[$name]['error'] = array($e->getCode(), $e->getMessage(), $e->getTraceAsString());
			}

			$details[$name]['result'] = $result;
			$overall = $overall && $result;
		}

		$output->setOutput('detail', $details);
		$output->setOutput('summary', $overall);

		return true;
	}
}
