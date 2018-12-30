## Contributing

[Peter](http://github.com/PeterJCLaw) <plaw@studentrobotics.org> is the maintainer for this.
Pull requests on GitHub are the best way to submit changes.

### Include tests

Wherever possible, you should create a test (or more!) for the functionality
that you're working on and commit this to your branch before you commit the
changes that make it pass.
Tests that are expected to fail can be marked as `XFAIL` in the schedule for this purpose.
This is hinting at being [Test-driven development](https://en.wikipedia.org/wiki/Test-driven_development)
but also ensures that any bug-fixes come with a regression test.

You should also run all the tests against your branch before sending a pull
request, to ensure the changes haven't broken anything.

## Code Style

The backend currently expects tabs for indentation and spaces for alignment.
This may change in the future, but for the moment please respect what is there.
Trailing whitespace should be avoided, ideally being removed before committing.

Variables that are going to be used for passing out to a command line must be prefixed with `$s_` giving the form `$s_my_variable`.
There is a test for this.

Once upon a time, in a temporal nexus that has since been removed, [someone](http://teaisaweso.me/), that shall remain nameless, committed a vast quantity of binary files to the IDE repo so that we could use the selenium testing suite.
This was not the right way to include this, and was fixed by rewriting the history.
Unfortunately, as happens when you mess with time, all was not well until we eventually deleted all clones of the repo that contained the commit.
Do not do this.
Even for laughs.
The correct way to have included this would have been to use a [submodule](https://book.git-scm.com/book/en/v2/Git-Tools-Submodules).
There is also a test that this commit has not been reintroduced into the source tree.
