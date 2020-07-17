# Student Robotics CyanIDE

[![Travis CI](https://travis-ci.org/PeterJCLaw/srobo-ide.svg?branch=master)](https://travis-ci.org/PeterJCLaw/srobo-ide)
[![CircleCI](https://circleci.com/gh/PeterJCLaw/srobo-ide.svg?style=svg)](https://circleci.com/gh/PeterJCLaw/srobo-ide)

**CyanIDE** is a web-based IDE for developing code for Student Robotics robots.

Here's some basic info about the make targets:

`dev`: Sets up the base folders you'll need for the repos etc.

`docs`: Builds the docs according to the doxyfile.
        Currently this means html docs in html/ and latex docs in latex/

`lint-venv-config`: Creates a Python virtualenv at `lint-venv` with the linting
        requirements in and configures the IDE to use those for linting.

`package`: Creates a .deb that installs all the dependencies for srobo-ide

`clean`: Removes all of the above.

You can run tests on CyanIDE by running `./run-tests`.

## Dependencies

 * A web server (the PHP development one is fine for development)
 * PHP 7+ (likely works on PHP 5)
 * PHP-GD # for uploaded image resizing
 * PHP-LDAP # if using LDAP authentication
 * Git
 * doxygen # for building the docs.
 * PHP CLI # for running the tests.
 * python-requests # for running HTTP tests.
 * python-yaml # for building the export ZIPs
 * pylint  # syntax checker
 * php-ldap
 * NodeJS         # for running JS tests
 * Jasmine Node   # for running JS tests

In Ubuntu these can be achieved by installing the following packages:
 `php5 php5-gd doxygen php5-cli git-core nodejs`

While `pylint` can be installed globally, it is often preferable to install it
from PyPI to ensure you have a recent version. The easiest way to do this is
using a virtualenv. In Ubuntu this can be done by installing `python-virtualenv`
and then running `make lint-venv-config`.

Jasmine Node can be installed via npm:
 `npm install jasmine-node -g`

## Development Setup

You'll need to run `make dev` before the IDE will run correctly.

The PHP [development server][php-web-server] can be run via `php -S localhost:8000`.

By default you can login with any non-empty username and password.

[php-web-server]: https://www.php.net/manual/en/features.commandline.webserver.php

### Apache HTTPD

Apache HTTPD is currently used for deployment, though it is expected that the
IDE will move to using a standalone PHP server (likely php-fpm directly behind
NGINX) as Apache is complicated to configure.

It is possible to use Apache HTTPD for development, however this is not encouraged.

## Authentication backends

There are three auth backends, configurable via the `auth_module` config key:

- `auto`: you are automatically logged in; there is only one user at a time,
  configured by `user.default`
- `single`: any non-empty username & password will log you in
- `ldap`: uses an LDAP server for authentication; configure using the various
  `ldap.*` configuration variables (see [`config/config.ini`](./config/config.ini)
  for configuration details).

  The easiest way to get a suitable LDAP server is to run either the
  [sr-dev-ldap][sr-dev-ldap] docker image or a local instance of the SR puppeted
  [volunteer services VM][server-puppet].

[server-puppet]: https://github.com/srobo/server-puppet/
[sr-dev-ldap]: https://hub.docker.com/r/peterjclaw/sr-dev-ldap

## Useful links

 * [CONTRIBUTING.md](./CONTRIBUTING.md)
 * [DESIGN.md](./DESIGN.md) (backend design notes)
 * [IDE research: putting objects in databases](https://groups.google.com/forum/#!topic/srobo-devel/vvKaEUQVOXo/discussion) (experimentation towards a MySQL backend for the git repos)


## Bee

```
                                  ...vvvv)))))).
       /~~\               ,,,c(((((((((((((((((/
      /~~c \.         .vv)))))))))))))))))))\``
          G_G__   ,,(((KKKK//////////////'
        ,Z~__ '@,gW@@AKXX~MW,gmmmz==m_.
       iP,dW@!,A@@@@@@@@@@@@@@@A` ,W@@A\c
       ]b_.__zf !P~@@@@@*P~b.~+=m@@@*~ g@Ws.
          ~`    ,2W2m. '\[ ['~~c'M7 _gW@@A`'s
            v=XX)====Y-  [ [    \c/*@@@*~ g@@i
           /v~           !.!.     '\c7+sg@@@@@s.
          //              'c'c       '\c7*X7~~~~
         ]/                 ~=Xm_       '~=(Gm_.

    i'm covered in beeeees and ldap
```
