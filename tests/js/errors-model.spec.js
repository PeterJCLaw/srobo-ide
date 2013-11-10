
var model = require('../../web/javascript/errors-model.js');

describe("The errors model:", function() {
	it("should be defined", function() {
		expect(model.ErrorsModel).toBeDefined();
	});

	var brwr, pgf, pgp, helpers = null;
	var prepForCheck = function() {
		brwr = jasmine.createSpy('backend_request_with_retry');
		pgf = jasmine.createSpy('path_get_file').andReturn('robot.py');
		pgp = jasmine.createSpy('path_get_project').andReturn('project');
		helpers = {
			'backend_request_with_retry': brwr,
			'path_get_file': pgf,
			'path_get_project': pgp,
		};
	};

	beforeEach(prepForCheck);

	it('should allow checks to be done', function() {
		var expectedArgs = {
			team: 'ABC',
			project: 'project',
			path: 'robot.py',
			rev: 'rev',
			autosave: 'auto',
		};

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check, null, 'auto', 'rev');

		expect(pgf).toHaveBeenCalledWith(file_to_check);
		expect(pgp).toHaveBeenCalledWith(file_to_check);

		expect(brwr).toHaveBeenCalledWith('file/lint', expectedArgs, jasmine.any(Function), 'Failed to check code', jasmine.any(Function));
	});
	it('should notify the caller of failures to check the code', function() {
		var fake_callback = jasmine.createSpy('fake_callback');

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check, fake_callback);

		var fail_cb = brwr.calls[0].args[4];

		fail_cb();

		expect(fake_callback).toHaveBeenCalledWith('checkfail');
	});
	it('should notify the caller if the code passes', function() {
		var fake_callback = jasmine.createSpy('fake_callback');

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check, fake_callback);

		var success_cb = brwr.calls[0].args[2];

		success_cb({ 'errors': [] });

		expect(fake_callback).toHaveBeenCalledWith('pass', file_to_check);
	});
	it('should notify the caller of any issues with the code', function() {
		var fake_callback = jasmine.createSpy('fake_callback');

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check, fake_callback);

		var success_cb = brwr.calls[0].args[2];

		var errors = [
			{ 'file': 'robot.py', 'msg': 1 },
			{ 'file': 'bacon.py', 'msg': 2 },
			{ 'file': 'robot.py', 'msg': 3 },
		];
		success_cb({ 'errors': errors });

		var files = {
			'/project/robot.py': [ errors[0], errors[2] ],
			'/project/bacon.py': [ errors[1] ],
		};

		var info = {
			'total': 3,
			'details': files,
		};

		expect(fake_callback).toHaveBeenCalledWith('codefail', info);
	});
	it('should notify subscribers if the code passes', function() {
		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check);

		var fake_subscriber = jasmine.createSpy('fake_subscriber');
		em.subscribe(fake_subscriber);

		var success_cb = brwr.calls[0].args[2];

		success_cb({ 'errors': [] });

		var expected_seen = {};
		expected_seen[file_to_check] = [];
		expect(fake_subscriber).toHaveBeenCalledWith(expected_seen);
	});
	it('should notify subscribers of any issues with the code', function() {
		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check);

		var fake_subscriber = jasmine.createSpy('fake_subscriber');
		em.subscribe(fake_subscriber);

		var success_cb = brwr.calls[0].args[2];

		var errors = [
			{ 'file': 'robot.py', 'msg': 1 },
			{ 'file': 'bacon.py', 'msg': 2 },
			{ 'file': 'robot.py', 'msg': 3 },
		];
		success_cb({ 'errors': errors });

		var files = {
			'/project/robot.py': [ errors[0], errors[2] ],
			'/project/bacon.py': [ errors[1] ],
		};

		expect(fake_subscriber).toHaveBeenCalledWith(files);
	});
	it('should not notify subscribers that have unsubscribed', function() {
		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check);

		var fake_subscriber = jasmine.createSpy('fake_subscriber');
		var handle = em.subscribe(fake_subscriber);
		em.unsubscribe(handle);

		var success_cb = brwr.calls[0].args[2];

		var errors = [
			{ 'file': 'robot.py', 'msg': 1 },
			{ 'file': 'bacon.py', 'msg': 2 },
			{ 'file': 'robot.py', 'msg': 3 },
		];
		success_cb({ 'errors': errors });

		expect(fake_subscriber).not.toHaveBeenCalled();
	});
	it('should not do anything after it\'s been disposed', function() {
		var em = new model.ErrorsModel(helpers, 'ABC');

		em.dispose();

		var file_to_check = '/project/robot.py';
		em.check(file_to_check);

		expect(brwr).not.toHaveBeenCalled();
	});
	it('should not notify anyone after it\'s been disposed', function() {
		var fake_callback = jasmine.createSpy('fake_callback');

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check, fake_callback);

		var fake_subscriber = jasmine.createSpy('fake_subscriber');
		var handle = em.subscribe(fake_subscriber);

		em.dispose();

		var success_cb = brwr.calls[0].args[2];

		var errors = [
			{ 'file': 'robot.py', 'msg': 1 },
			{ 'file': 'bacon.py', 'msg': 2 },
			{ 'file': 'robot.py', 'msg': 3 },
		];
		success_cb({ 'errors': errors });

		expect(fake_callback).not.toHaveBeenCalled();
		expect(fake_subscriber).not.toHaveBeenCalled();
	});
	it('should know about errors from previous checks', function() {
		var fake_callback = jasmine.createSpy('fake_callback');

		var em = new model.ErrorsModel(helpers, 'ABC');
		var file_to_check = '/project/robot.py';
		em.check(file_to_check);

		var success_cb = brwr.calls[0].args[2];

		var errors = [
			{ 'file': 'robot.py', 'msg': 1 },
			{ 'file': 'bacon.py', 'msg': 2 },
			{ 'file': 'robot.py', 'msg': 3 },
		];
		success_cb({ 'errors': errors });

		var robot_errors = em.get_current('/project/robot.py');
		expect(robot_errors).toEqual([ errors[0], errors[2] ]);

		var bacon_errors = em.get_current('/project/bacon.py');
		expect(bacon_errors).toEqual([ errors[1] ]);
	});
	it('should not know about files that haven\'t been checked.', function() {
		var em = new model.ErrorsModel(helpers, 'ABC');
		var errors = em.get_current('whatever.py');
		expect(errors).toEqual(null);
	});
});
