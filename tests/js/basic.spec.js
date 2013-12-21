
var jdp = require('./jasmine-data-provider/spec/SpecHelper.js');
var using = jdp.using;

describe("A suite that ought to pass", function() {
	it("A single spec that checks things", function() {
		expect(true).toBe(true);
		expect(false).toBe(false);
	});
	it("should be able to see the jasmine-data-provider", function() {
		expect(jdp.using).toBeDefined();
	});
	var lastNumber = 0;
	using("some numbers", [1, 2, 3, 42], function(value) {
		it("should not equal the last number", function() {
			expect(value).not.toEqual(lastNumber);
			lastNumber = value;
		});
	});
	it("should have stored the last number", function() {
		expect(lastNumber).toEqual(42);
	});
});
