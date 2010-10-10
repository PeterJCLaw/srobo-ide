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
		$config = Configuration::getInstance();

		$messagesURL = $config->getConfig('messages_url');
		$messagesLimit = $config->getConfig('messages_limit');

		$messages = Feeds::getRecentPosts($messagesURL, $messagesLimit);
		$output->setOutput('messages', $messages);
	}

	/**
	 * Gets switchboard milestones
	 */
	public function getMilestones()
	{
		$config = Configuration::getInstance();
		$output = Output::getInstance();

		$start = strtotime($config->getConfig('switchboard.start'));
		$end   = strtotime($config->getConfig('switchboard.end'));
		$eventsFile = $config->getConfig('switchboard.events');
		$eventsIn = file_get_contents($eventsFile);
		$eventsIn = json_decode($eventsIn);

		$events = array();
		foreach($eventsIn as $event)
		{
			var_dump($event);
			$event->date = strtotime($event->date);
			$events[] = $event;
		}

		$output->setOutput('start', $start);
		$output->setOutput('end', $end);
		$output->setOutput('events', $events);
	}
}
