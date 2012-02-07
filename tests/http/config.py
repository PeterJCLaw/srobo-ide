
# The base path to the version of the IDE to test.
# You'll almost definitely want to override this one -- see below for details.
URL = "https://www.studentrobotics.org/ide/"

# The user details to login with
username = 'py-test'
password = 'py-test'

# You can add a file called localconfig.py next to this one and override
# or add any options that you want.
try:
	from localconfig import *
except ImportError:
	pass
