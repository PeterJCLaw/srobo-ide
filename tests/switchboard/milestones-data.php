<?php

$config = Configuration::getInstance();

$start = $config->getConfig('switchboard.start');
$start_stamp = strtotime($start);
$end = $config->getConfig('switchboard.end');
$end_stamp = strtotime($end);

test_true($start_stamp < $end_stamp, "The start of the milestones ($start) cannot be before the end ($end)!");

$events_file = $config->getConfig('switchboard.events');

test_true(file_exists($events_file), "The switchboard events file ($events_file) must exist!");

$events = json_decode(file_get_contents($events_file));

foreach ($events as $event)
{
	$date = strtotime($event->date);
	test_true($start_stamp < $date, "The date ($event->date) of milestone ($event->title) cannot be before the start of the switchboard ($start)!");
	test_true($date < $end_stamp, "The date ($event->date) of milestone ($event->title) cannot be after the end of the switchboard ($end)!");
}
