<?php

$ret = shell_exec('git rev-list HEAD | grep efc74120df33c96cd5892bc56f911f511db1f64e');
test_null($ret, 'Current HEAD has the selenium fail as an ancestor!');
