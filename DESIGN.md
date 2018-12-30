## Backend Design Notes

CyanIDE is architected by presenting a single query point to the frontend.
This point is aware of a number of modules and each module has a number of commands installed on it.

When something wants to interact with cyanide it makes a HTTP POST request to `/module/command` and encodes the request in a JSON dictionary.

Each module subclasses an abstract model class, and commands are installed into
modules as pairs of `(String commandName, function command)`. Most of the time
these occur as closures with the class.

Commands should do *one thing* each, for example `/file/put` only writes file
contents and does no interaction with the back-end repositories.

Authentication to CyanIDE is handled by a flexible authentication system. We
have an abstract `Auth` class and all authentication objects handle requests via a
single interface that the abstract `Auth` class presents.

In order to make authentication stateful we use an auth token which is passed
from the backend to the frontend using a `TokenStrategy`. There are currently
two of these (encode in request and use a cookie) and these are swappable
implementations.
