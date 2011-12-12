<?php

$workDir = tmpdir();

echo "Working in $workDir\n";

$config = Configuration::getInstance();
$config->override('team.status_dir', $workDir);

$team = 'XYZ';
$user = 'first-user';

section("Draft saving");

$status = new TeamStatus($team);

$field = 'my-field';
$content = 'my-content"'."\n";

$status->setDraft($field, $content);

test_nonexistent($status->getStatusPath(), 'Status saved to disk before save called');

$status->save($user);

test_existent($status->getStatusPath(), 'Status not saved to disk after save called');

$data = json_decode(file_get_contents($status->getStatusPath()));
test_equal($data->$field->draft, $content, 'Wrong content in draft field');
test_equal($data->$field->uid, $user, 'Wrong user set for saved field');
test_nonempty($data->$field->date, 'No date set for saved field');

section("loading");

$status = new TeamStatus($team);
test_equal($status->getDraftOrLive($field), $content, "Loaded wrong draft content for $field.");

section("partial update");
$field1Date = $data->$field->date;

$user2 = 'second-user';

$field2 = 'my-other-field';
$content2 = 'my-other-content"'."\n";

$status->setDraft($field2, $content2);

$status->save($user2);

$data = json_decode(file_get_contents($status->getStatusPath()));
test_equal($data->$field2->draft, $content2, 'Wrong content in second draft field');
test_equal($data->$field2->uid, $user2, 'Wrong user set for second saved field');
test_nonempty($data->$field2->date, 'No date set for second saved field');

subsection("check that it didn't modify the first field");
test_equal($data->$field->draft, $content, 'Wrong content in first draft field');
test_equal($data->$field->uid, $user, 'Wrong user set for first saved field');
test_equal($data->$field->date, $field1Date, 'No date set for first saved field');

section('Items for review');

$items = $status->itemsForReview();
$expectedItems = array($field => $content, $field2 => $content2);

test_equal($items, $expectedItems, "Wrong items in list of things to review");

subsection('No review of things already live');

$data->$field->live = $data->$field->draft;
$data->$field->reviewed = true;
// save modified data
file_put_contents($status->getStatusPath(), json_encode($data));

// relaod
$status->load();
$items = $status->itemsForReview();
$expectedItems = array($field2 => $content2);

test_equal($items, $expectedItems, "Wrong items in list of things to review after making one live");

subsection('Needs review');

$needsReview = $status->needsReview();
test_true($needsReview, "When not all items are live should claim to need review");

// remove the other field so they're all live
unset($data->$field2);
// save modified data
file_put_contents($status->getStatusPath(), json_encode($data));

// relaod
$status->load();
$items = $status->itemsForReview();

test_equal($items, array(), "When all items are live should be nothing to review");

$needsReview = $status->needsReview();
test_false($needsReview, "When all items are live should not claim to need review");

subsection('saving reviewing state');

// set different content & save - should now need review
$status->setDraft($field2, $content);
$status->save($user);

$reviewState = $status->getReviewState($field2);
test_null($reviewState, "Unreviewed items should claim as much");

$status->setReviewState($field2, $content, true);

$reviewState = $status->getReviewState($field2);
test_true($reviewState, "Reviewed items should return boolean status indicator");

$status->save($user);
$data = json_decode(file_get_contents($status->getStatusPath()));
test_equal($data->$field2->live, $content, "Valid review should set the live value");

// set different content & save - should now need review
$status->setDraft($field2, $content2);
$status->save($user);

$reviewState = $status->getReviewState($field2);
test_null($reviewState, "Unreviewed items should claim as much");

$status->setReviewState($field2, $content2, false);

$reviewState = $status->getReviewState($field2);
test_false($reviewState, "Reviewed items should return boolean status indicator");

$status->save($user);
$data = json_decode(file_get_contents($status->getStatusPath()));
test_equal($data->$field2->live, $content, "Valid review should not set the live value");

section('All teams listing');
$teams = array($team, 'bacon', 'foo ?');

foreach ($teams as $team) {
	// create the file
	$status = new TeamStatus($team);
	$status->setDraft('bees', 'bees');
	$status->save('no-one');
}

$teamsListed = TeamStatus::listAllTeams();

// need to sort them else they don't compare the same
sort($teams);
sort($teamsListed);

test_equal($teamsListed, $teams, "Returned the wrong list of teams");

// teardown.
delete_recursive($workDir);
