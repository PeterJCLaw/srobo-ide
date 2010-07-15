<?php

$output = Output::getInstance();
test_nonnull($output, "failed to get output singleton");

// stupid test
test_equal($output->encodeOutput(), "{}", "failed to correctly encode empty output");

// test the data handling
test_null($output->getOutput('dogbees'), "undefined output was not null");
$output->setOutput('dogbees', 'moof');
test_equal($output->getOutput('dogbees'), 'moof', "output was incorrect after addition");
$output->removeOutput('dogbees');
test_null($output->getOutput('dogbees'), "output failed to remove key");

// TODO: some encoding testing here, one day
