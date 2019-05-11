"""
Very simple test that we can login to the IDE, and get a non-empty token.
"""

# External
import unittest

# Local
from . import util
from . import config

class AuthTests(unittest.TestCase):

	def test_canLogin(self):
		data = dict(username = config.username, password = config.password)
		resp = util.makeIDERequest(util.loginEndPoint, data)

		util.assertEqual(resp['display-name'], config.username, "Got the wrong display name")

		token = util.getCurrentToken()
		util.assertIsNotNone(token, "No token stored from server response")

if __name__ == '__main__':
	unittest.main(buffer=True)
