<?php

// grab the singleton
$input = Input::getInstance();
test_nonnull($input, "failed to get input singleton");

// test the request handling
$input->setRequest('bees/death');
test_equal($input->getRequestModule(), 'bees', "wrong module on first RQ test");
test_equal($input->getRequestCommand(), 'death', "wrong command on first RQ test");
$input->setRequest('bees/cake/death');
test_equal($input->getRequestModule(), 'bees/cake', "wrong module on second RQ test");
test_equal($input->getRequestCommand(), 'death', "wrong command on second RQ test");

// test the data handling
test_null($input->getInput('dogbees', true), "undefined input was not null");
$input->setInput('dogbees', 'moof');
test_equal($input->getInput('dogbees'), 'moof', "input was incorrect after addition");
$input->removeInput('dogbees');
test_null($input->getInput('dogbees', true), "input failed to remove key");
