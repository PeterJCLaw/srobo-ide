<?php

class SwitchboardModule extends Module
{
	public function __construct()
	{
		$this->installCommand('messages', array($this, 'getMessages'));
		$this->installCommand('milestones', array($this, 'getMilestones'));
	}

	public function getMessages()
	{
		$output = Output::getInstance();
		$output->setOutput('messages', array());
	}

	public function getMilestones()
	{
		$output = Output::getInstance();
		$output->setOutput('milestones', array());
	}
}
