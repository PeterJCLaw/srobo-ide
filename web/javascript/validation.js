
var Validation = {
	'is_url': function(text) {
		return /^https?:\/\//.test(text);
	},
	'is_feed': function(text) {
		return /^(https?|feed):\/\//.test(text);
	}
}

// node require() based exports.
if (typeof(exports) != 'undefined') {
	exports.Validation = Validation;
}
