
import urllib2
import Cookie
import json
import random
import string

import config

loginEndPoint = "auth/authenticate"

def extractToken(response):
	info = response.info()
#	print "info:", info
	cookie = info['Set-Cookie']
	cookie = Cookie.BaseCookie(cookie)
#	print "cookie:", cookie
	token = cookie['token']
#	print "token:", token.value
	return token.value

_token = None
def getCurrentToken():
	global _token
	if _token is None:
		pass
		# TODO: go get it from somewhere?
	return _token

def saveCurrentToken(token):
	global _token
	_token = token
	if _token is None:
		raise Exception('No token to save!')
	# TODO: save it more than this?

def getURLForEnpoint(endPoint):
	url = config.URL + "control.php/" + endPoint
	return url

def raiseOnRequestError(response_data):
	if response_data.has_key('error'):
		raise Exception('Server error: %s' % response_data['error'][1])

def makeIDERequest(endPoint, data = None):

	auth_token = getCurrentToken()

	if data is None:
		data = dict()
	data_string = json.dumps(data)

	url = getURLForEnpoint(endPoint)

	req = urllib2.Request(url, data_string)
	if auth_token is not None:
		req.add_header('Cookie', 'token=%s' % auth_token)

	response = urllib2.urlopen(req)

	response_data = json.load(response)
	#print 'response_data:', json.dumps(response_data, sort_keys = True, indent = 4)
	raiseOnRequestError(response_data)

	auth_token = extractToken(response)
	saveCurrentToken(auth_token)

	return response_data

### Random

def idGenerator(size=6, chars=string.ascii_uppercase + string.digits):
	return ''.join(random.choice(chars) for x in range(size))

### Pretty Printing

def printDict(data):
	print json.dumps(data, sort_keys = True, indent = 4)

### Test Helper

def skip():
	print '___SKIP_TEST'
	exit(0)

### Nicer Assertions than pyUnit

def prepMessage(expected, actual, message, params):
	if isinstance(message, type('')):
		if isinstance(params, type(())) and len(params) > 0:
			message = message % params
	else:
		message = ''

	return message + "\nExpected: %s\n  Actual: %s" % (expected, actual)

def prepMessagePrefix(prefix, expected, actual, message, params):
	return prepMessage('%s %s' % (prefix, expected), actual, message, params)

def prepMessageNot(expected, actual, message, params):
	return prepMessagePrefix('not', expected, actual, message, params)


def assertEqual(expected, actual, message = None, *params):
	message = prepMessage(expected, actual, message, params)
	assert expected == actual, message

def assertNotEqual(expected, actual, message = None, *params):
	message = prepMessageNot(expected, actual, message, params)
	assert expected != actual, message

def assertIs(expected, actual, message = None, *params):
	message = prepMessage(expected, actual, message, params)
	assert expected is actual, message

def assertIsNot(expected, actual, message = None, *params):
	message = prepMessageNot(expected, actual, message, params)
	assert expected is not actual, message

def assertIsInstance(expected, actual, message = None, *params):
	message = prepMessagePrefix('instance of', expected, actual, message, params)
	assert isinstance(expected, actual), message

def assertIsNotInstance(expected, actual, message = None, *params):
	message = prepMessagePrefix('instance not of', expected, actual, message, params)
	assert not isinstance(expected, actual), message

assertNotIsInstance = assertIsNotInstance


def assertIsNone(actual, message = None, *params):
	assertIs(None, actual, message, *params)

def assertIsNotNone(actual, message = None, *params):
	assertIsNot(None, actual, message, *params)

def assertTrue(actual, message = None, *params):
	assertIs(True, bool(actual), message, *params)

def assertFalse(actual, message = None, *params):
	assertIs(False, bool(actual), message, *params)


def assertIn(needle, haystack, message = None, *params):
	message = prepMessagePrefix('collection containing', needle, haystack, message, params)
	assert needle in haystack, message

def assertNotIn(needle, haystack, message = None, *params):
	message = prepMessagePrefix('collection not containing', needle, haystack, message, params)
	assert needle not in haystack, message


def assertGreaterThan(reference, actual, message = None, *params):
	message = prepMessagePrefix('value greater than', reference, actual, message, params)
	assert actual > reference, message

def assertLessThan(reference, actual, message = None, *params):
	message = prepMessagePrefix('value less than', reference, actual, message, params)
	assert actual < reference, message
