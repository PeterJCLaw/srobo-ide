"""
Tests file interactions.
"""

# External
import unittest
import threading

# Local
import util
import config

class FilesTests(unittest.TestCase):

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

		cls._team = teams.keys()[0]

	def setUp(self):
		# Create a project
		self._projName = 'FilesTests-' + util.idGenerator()
		data = dict(team = self._team, project = self._projName)
		util.makeIDERequest('proj/new', data)

	def tearDown(self):
		# TODO: find a way to remove the projects we're done with
		pass

	def test_1058(self):
		data = dict(team = self._team, project = self._projName, path = 'robot.py')
		data['paths'] = [data['path']]
		data['message'] = 'A dummy commit'
		data['data'] = 'Some dummy content for a dummy commit'

		# Generate some data so that the requests have something to do
		util.makeIDERequest('file/put', data)
		util.makeIDERequest('proj/commit', data)

		def fileTreeRequest():
			global fileTreeResult
			data2 = dict(data)
			data2['path'] = '.'
			fileTreeResult = util.makeIDERequest('file/compat-tree', data2)

		def lintRequest():
			global lintResult
			lintResult = util.makeIDERequest('file/lint', data)
		#	util.printDict(lintResult)

		def logRequest():
			global logResult
			logResult = util.makeIDERequest('file/log', data)
		#	util.printDict(logResult)

		threads = []
		for req in [ fileTreeRequest, lintRequest, logRequest ]:
			t = threading.Thread( target = req )
			t.start()
			threads.append(t)

		for t in threads:
			t.join()

		util.raiseOnRequestError(fileTreeResult)
		util.raiseOnRequestError(lintResult)
		util.raiseOnRequestError(logResult)

if __name__ == '__main__':
	unittest.main(buffer=True)
