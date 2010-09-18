<?php

system("rm -rf /tmp/beedog-tyres-also-the-game");

mkdir_full("/tmp/beedog-tyres-also-the-game/marblecake/ninjas");
test_true(file_exists("/tmp/beedog-tyres-also-the-game") &&
               is_dir("/tmp/beedog-tyres-also-the-game"), "failed to create lowest level dir");
test_true(file_exists("/tmp/beedog-tyres-also-the-game/marblecake") &&
               is_dir("/tmp/beedog-tyres-also-the-game/marblecake"), "failed to create mid-dir");
test_true(file_exists("/tmp/beedog-tyres-also-the-game/marblecake/ninjas") &&
               is_dir("/tmp/beedog-tyres-also-the-game/marblecake/ninjas"), "failed to create main dir");
