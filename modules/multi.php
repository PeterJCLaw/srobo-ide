<?php

/**
 * Module for handling multiple requests
 */
class MultiModule extends Module
{
	private $manager = null;
	private $input = null;
	private $output = null;
	private $cmd_outputs = array();

	public function __construct()
	{
		$this->installCommand('independent', array($this, 'independent'));
		$this->installCommand('sequential', array($this, 'sequential'));
		$this->manager = ModuleManager::getInstance();
		$this->input = Input::getInstance();
		$this->output = Output::getInstance();
	}

	public function independent()
	{
		$commands = $this->input->getInput('commands');
		foreach ($commands as $command)
		{
			$this->safeDispatch($command);
		}
		$this->setOutputs();
		return true;
	}

	public function sequential()
	{
		$commands = $this->input->getInput('commands');
		$this->dispatchSequence($commands);
		return true;
	}

	private function setOutputs()
	{
		foreach ($this->cmd_outputs as $key => $value)
		{
			$this->output->setOutput($key, $value);
		}
	}

	private function safeDispatch($command)
	{
		try
		{
			$ret = $this->dispatch($command);
			return $ret;
		}
		catch (Exception $e)
		{
			$command['error'] = $e;
		}
		return false;
	}

	private function dispatchSequence($sequence)
	{
		try
		{
			foreach ($sequence as $command)
			{
				$ret = $this->dispatch($command);
				if (!$ret)
				{
					return false;
				}
			}
			return true;
		}
		catch (Exception $e)
		{
			// TODO: something better!
			$sequence['error'] = $e;
		}
		return false;
	}

	/**
	 * Dispatch a given command request in a manner that simulates a top
	 * level dispatch, and should be transparent for any command.
	 * @param request: An array containing:
	 *         'cmd' - the full name of the command to run, eg: file/get
	 *        'data' - a map of the input variables for that command.
	 * @returns: The return value from the command dispatch.
	 */
	private function dispatch($request)
	{
		$this->input->clear();
		foreach ($request['data'] as $key => $value)
		{
			$this->input->setInput($key, $value);
		}

		// TODO: hierarchy levels?
		$cmd = $request['cmd'];
		$this->input->setRequest('multi:' . $cmd);

		list($module, $command) = Input::parseRequest($cmd);

		$mod = $this->manager->getModule($module);
		if ($mod == false || $mod == null)
		{
			// TODO: error handling
			fail();
		}
		$ret = $mod->dispatchCommand($command);
		$output = json_decode($this->output->encodeOutput(), true);
		$this->cmd_outputs[$cmd] = $output;

		// TODO: this needs to be in a finally block
		$this->output->clear();

		return $ret;
	}
}
