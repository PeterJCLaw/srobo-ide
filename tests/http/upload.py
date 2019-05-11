"""
Check that we can upload images to the Team Status area.
"""

# External
import os.path
import unittest

import requests

# Local
from . import util
from . import config

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

	def get_imageData(self, image):
		root_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
		images_dir = os.path.join(root_dir, 'web/images')
		image_path = os.path.join(images_dir, image)
		return open(image_path, 'rb')

	def do_upload(self, image):
		data = dict(team = self._team, _command = 'team/status-put-image')
		files = {'team-status-image-input': self.get_imageData(image)}

		url = config.URL + 'upload.php'
		resp = util.makeIDERequest2(url, data, files=files)

		expected_image_path = os.path.join(config.team_status_image_dir, self._team + '.png')
		exists = os.path.exists(expected_image_path)
		assert exists, "Uploaded file '%s' should exist" % expected_image_path

	def test_canUploadPNG(self):
		self.do_upload('static.png')

	def test_canUploadGif(self):
		self.do_upload('anim.gif')

if __name__ == '__main__':
	unittest.main(buffer=True)
