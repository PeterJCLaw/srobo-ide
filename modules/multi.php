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
		$this->setOutputs();
		return true;
	}

	private function setOutputs()
	{
		foreach ($this->cmd_outputs as $key => $value)
		{
			$this->output->setOutput($key, $value);
		}
	}

	private function setException($command, $exception)
	{
		ide_log_exception($exception);
		$this->cmd_outputs[$command['cmd']]['error'] = parts_for_output($exception);
	}

	private function safeDispatch($command)
	{
		try
		{
			$this->dispatch($command);
		}
		catch (Exception $e)
		{
			$this->setException($command, $e);
		}
	}

	private function dispatchSequence($sequence)
	{
		try
		{
			foreach ($sequence as $command)
			{
				$this->dispatch($command);
			}
		}
		catch (Exception $e)
		{
			$this->setException($command, $e);
		}
	}

	/**
	 * Dispatch a given command request in a manner that simulates a top
	 * level dispatch, and should be transparent for any command.
	 * @param request: An array containing:
	 *         'cmd' - the full name of the command to run, eg: file/get
	 *        'data' - a map of the input variables for that command.
	 */
	private function dispatch($request)
	{
		$this->cmd_outputs[$command['cmd']] = array();
		$this->input->clear();
		$rqData = $request['data'];
		if ($rqData != null)
		{
			foreach ($request['data'] as $key => $value)
			{
				$this->input->setInput($key, $value);
			}
		}

		// TODO: hierarchy levels?
		$cmd = $request['cmd'];
		$this->input->setRequest('multi:' . $cmd);

		list($module, $command) = Input::parseRequest($cmd);

		// PHP < 5.5 doesn't support finally, so we need to mock it.
		// rely on the assumption that this isn't going to throw itself
		$that = $this;
		$finally = function() use($that, $cmd)
		{
			$output = json_decode($that->output->encodeOutput(), true);
			$that->cmd_outputs[$cmd] = $output;
			$that->output->clear();
		};

		try
		{
			$this->manager->dispatchCommand($module, $command);
			$finally();
		}
		catch (Exception $e)
		{
			$finally();
			throw $e;
		}
	}
}
