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
		$output->setOutput('messages', array(
			array(
				'link'   => 'http://www.example.com',
				'title'  => 'An Example Title',
				'body'   => 'Message body.',
				'author' => 'pony man'
			)
		));
	}

	public function getMilestones()
	{
		$output = Output::getInstance();
		$output->setOutput('start', (time() - 3600) . '000');
		$output->setOutput('end',   (time() + 3600) . '000');
		$output->setOutput('events', array(
			array(
				'title' => 'One',
				'desc'  => 'First Event',
				'date'  => 'Now'
			),
			array(
				'title' => 'Two',
				'desc'  => 'Second Event',
				'date'  => 'Also Now'
			),
			array(
				'title' => 'Three',
				'desc'  => 'Third Event',
				'date'  => 'Still Now'
			)
		));
	}
}
