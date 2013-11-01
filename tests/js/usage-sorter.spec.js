
var sort = require('../../web/javascript/usage-sorter.js');

describe("The usage sorter", function() {
	it("should be defined", function() {
		expect(sort.UsageSorter).toBeDefined();
	});
	it("should allow notifications", function() {
		var s = new sort.UsageSorter([], function(){});
		s.notify_use();
	});
	it("should allow sort", function() {
		var s = new sort.UsageSorter([], function(){});
		s.sort([]);
	});
	it("should sort by most recent used", function() {
		var items = ['a', 'b', 'c'];
		var s = new sort.UsageSorter([], function(){});
		s.notify_use('a');
		s.notify_use('b');
		s.notify_use('c');
		var result = s.sort(items);
		expect(result).toEqual(['c', 'b', 'a']);
	});
	it("should not modify the original list of entries directly", function() {
		var items = ['a', 'b', 'c'];
		var s = new sort.UsageSorter(items, function(){});
		s.notify_use('c');
		s.notify_use('a');
		s.notify_use('b');
		expect(items).toEqual(['a', 'b', 'c']);
	});
	it("should include any unknown items at the end, in their original order", function() {
		var items = ['a', 'b', 'c', 'd', 'e'];
		var s = new sort.UsageSorter([], function(){});
		s.notify_use('d');
		s.notify_use('c');
		var result = s.sort(items);
		expect(result).toEqual(['c', 'd', 'a', 'b', 'e']);
	});
	it("should not modify the original list requested be sorted", function() {
		var items = ['a', 'b', 'c'];
		var s = new sort.UsageSorter([], function(){});
		s.notify_use('d');
		s.notify_use('c');
		s.sort(items);
		expect(items).toEqual(['a', 'b', 'c']);
	});
	it("should not include items that have been used, but are not requested", function() {
		var items = ['a', 'b', 'c'];
		var s = new sort.UsageSorter([], function(){});
		s.notify_use('d');
		s.notify_use('c');
		var result = s.sort(items);
		expect(result).toEqual(['c', 'a', 'b']);
	});
	it("should save the list when notified of a new item, with that item at the end of the list", function() {
		var actual_list = [];
		var saver = function(list) {
			actual_list = list;
		}
		var s = new sort.UsageSorter(['a'], saver);
		s.notify_use('bacon');
		expect(actual_list).toEqual(['a', 'bacon']);
	});
});
