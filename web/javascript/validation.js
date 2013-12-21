
var Validation = {
	'is_url': function(text) {
		return true;
	},
	'is_feed': function(text) {
		return true;
	}
}

// node require() based exports.
if (typeof(exports) != 'undefined') {
	exports.Validation = Validation;
}
