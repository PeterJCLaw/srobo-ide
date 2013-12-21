
var using = require('./jasmine-data-provider/spec/SpecHelper.js').using;

var validation = require('../../web/javascript/validation.js');

describe("The validation helper", function() {
	it("should be defined", function() {
		expect(validation.Validation).toBeDefined();
	});
	it("should have a function for urls", function() {
		expect(validation.Validation.is_url).toBeDefined();
	});
	it("should have a function for feeds", function() {
		expect(validation.Validation.is_feed).toBeDefined();
	});

	var valid_urls = ['http://bacon.com', 'https://srobo.org'];
	using('valid url', valid_urls, function(url) {
		it("should accept valid urls", function() {
			expect(validation.Validation.is_url(url)).toBe(true);
		});
	});

	var invalid_urls = ['bacon.com', 'www.srobo.org'];
	using('invalid url', invalid_urls, function(url) {
		it("should reject invalid urls", function() {
			expect(validation.Validation.is_url(url)).toBe(false);
		});
	});

	var valid_feeds = ['http://bacon.com', 'https://srobo.org', 'feed://somewhere.com',
	                   'http://bacon.com/feeds/default/atom'];
	using('valid feed', valid_feeds, function(feed) {
		it("should accept valid feeds", function() {
			expect(validation.Validation.is_feed(feed)).toBe(true);
		});
	});

	var invalid_feeds = ['bacon.com', 'www.srobo.org'];
	using('invalid feed', invalid_feeds, function(feed) {
		it("should reject invalid feeds", function() {
			expect(validation.Validation.is_feed(feed)).toBe(false);
		});
	});
});
