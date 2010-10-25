<?php

class NotificationsModule extends Module
{
	public function __construct()
	{
		$input         = Input::getInstance();
		$output        = Output::getInstance();
		$notifications = Notifications::getInstance();
		if ($team = $input->getInput('team', true))
		{
			$output->setOutput('notifications', $notifications->pendingNotificationsForTeam($team));
			$notifications->clearNotificationsForTeam($team);
		}
	}
}
