"""
Check that we can upload images to the Team Status area.
"""

# External
import os.path
from poster.encode import multipart_encode
from poster.streaminghttp import register_openers
import unittest
import urllib2

# Local
import util
import config

class UploadTests(unittest.TestCase):

	_team = None

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

		register_openers()

	def get_imageData(self, image):
		root_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
		images_dir = os.path.join(root_dir, 'web/images')
		image_path = os.path.join(images_dir, image)
		return open(image_path, 'rb')

	def do_upload(self, image):
		values = dict(team = self._team, _command = 'team/status-put-image')
		values['team-status-image-input'] = self.get_imageData(image)

		data, headers = multipart_encode(values)

		url = config.URL + 'upload.php'
		resp = util.makeIDERequest2(url, data, headers)

		expected_image_path = os.path.join(config.team_status_image_dir, self._team + '.png')
		exists = os.path.exists(expected_image_path)
		assert exists, "Uploaded file '%s' should exist" % expected_image_path

	def test_canUploadPNG(self):
		self.do_upload('static.png')

	def test_canUploadGif(self):
		self.do_upload('anim.gif')

if __name__ == '__main__':
	unittest.main(buffer=True)
