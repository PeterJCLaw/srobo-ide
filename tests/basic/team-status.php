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

// teardown.
delete_recursive($workDir);
