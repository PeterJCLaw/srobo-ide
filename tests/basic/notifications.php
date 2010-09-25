<?php

$notifications = Notifications::getInstance();
test_nonempty($notifications, "failed to get Notifications singleton");

$notifications->clearNotificationsForTeam('fake-team');
$notifications->writeNotificationForTeam('fake-team', "BEES IN MY EYES");
test_equal($notifications->pendingNotificationsForTeam('fake-team'), array('BEES IN MY EYES'), 'notification was not present');
$notifications->clearNotificationsForTeam('fake-team');
test_empty($notifications->pendingNotificationsForTeam('fake-team'), 'notifications were not cleared');
