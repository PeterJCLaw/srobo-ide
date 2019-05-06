"""
Tests project actions.
"""

# External
import unittest

# Local
from . import util
from . import config

class ProjTests(unittest.TestCase):

	_team = None
	_projName = None

	@classmethod
	def setUpClass(cls):
		# Log In
		data = dict(username = config.username, password = config.password)
		util.makeIDERequest(util.loginEndPoint, data)

		token = util.getCurrentToken()
		util.assertIsNotNone(token, "No token stored from server response")

		# Get a team
		resp = util.makeIDERequest('user/info')
		teams = resp['teams']
		util.assertGreaterThan(0, len(teams), "User must be in a team to test the IDE")

		cls._team = teams[0]['id']

	def tearDown(self):
		data = dict(team = self._team, project = self._projName)
		util.makeIDERequest('proj/del', data)

	def test_projCreate(self):
		# Create a project
		self._projName = 'FilesTests-' + util.idGenerator()
		data = dict(team = self._team, project = self._projName)
		util.makeIDERequest('proj/new', data)

if __name__ == '__main__':
	unittest.main(buffer=True)
