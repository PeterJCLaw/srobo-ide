<?php

/**
 * Module for handling the switchboard
 *
 * installed commands are:
 * messages (void) -> ('messages' : [{'link' : Url, 'title' : String, 'body' : String, 'author': String}])
 * milestones (void) -> ('events' : [{'title' : String, 'desc' : String, 'date' : String}])
 */
class SwitchboardModule extends Module
{
	public function __construct()
	{
		$this->installCommand('messages', array($this, 'getMessages'));
		$this->installCommand('milestones', array($this, 'getMilestones'));
	}

    /**
     * Gets switchboard messages
     */
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

    /**
     * Gets switchboard milestones
     */
	public function getMilestones()
	{
		$output = Output::getInstance();
		$output->setOutput('start', (int)((time() - 3600) . '000'));
		$output->setOutput('end',   (int)((time() + 3600) . '000'));
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
