#!/bin/bash
make clean
make
lighttpd -f launch.conf -D
