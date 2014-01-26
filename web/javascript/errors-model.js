
/**
 * A class which checks for errors in source files & caches the results.
 * Offers publish/subscription for long-term clients interested in all
 * checks, and also allows for check-specific callbacks.
 * Deliberately doesn't attempt to do any UI.
 * @param helpers: A dictionary of helper methods (ide.js:Helpers).
 * @param team: The current team. This value cannot be changed, and a new
 *              instance should be created if it needs to.
 */
function ErrorsModel(helpers, team) {
	// Map of file to list of errors know for that file.
	this._map = {};

	// List of things that want to be notified of all changes.
	this._subscribers = [];

	// Whether or not this model has been disposed.
	// This usually happens when the team changes.
	this._disposed = false;

	var that = this;

	this.dispose = function() {
		this._map = this._subscribers = null;
		this._disposed = true;
	};

	this.subscribe = function(callback) {
		if (this._disposed) { return; }

		this._subscribers.push(callback);
		return this._subscribers.length - 1;
	};

	this.unsubscribe = function(index) {
		if (this._disposed) { return; }

		this._subscribers[index] = null;
	};

	this.get_current = function(file) {
		if (this._disposed) { return; }

		var errors = this._map[file];
		if (errors) {
			errors = errors.concat([]);
		}
		return errors;
	};

	this.check = function(file, callback, autosave, revision) {
		if (this._disposed) { return; }

		var project = helpers.path_get_project(file);
		var opts = opts || {};
		var args = { 'team': team
				   , 'project': project
				   , 'path': helpers.path_get_file(file)
				   , 'rev': revision
				   , 'autosave': autosave
				   };

		var success = function(nodes) {
			done_check(project, file, callback, nodes);
		};
		var fail = function() {
			if (callback != null) {
				callback('checkfail');
			}
		};
		var retry_msg = 'Failed to check code';
		helpers.backend_request_with_retry("file/lint", args, success, retry_msg, fail);
	};

	var build_path = function(project, file) {
		return '/' + project + '/' + file;
	};

	var done_check = function(project, file, callback, nodes) {
		if (that._disposed) { return; }

		var errors = nodes.errors;
		var seen_files = {};
		// TODO: have the backend give a list of files it looked in
		// clear the current file's list
		seen_files[file] = that._map[file] = [];
		for (var i=0; i < errors.length; i++) {
			var error = errors[i];
			var err_file = build_path(project, error.file);
			if (!seen_files[err_file]) {
				seen_files[err_file] = that._map[err_file] = [];
			}
			that._map[err_file].push(error);
		}
		if (callback) {
			if (errors.length == 0) {
				callback('pass', file);
			} else {
				var info = { 'details': seen_files, 'total': errors.length };
				callback('codefail', info);
			}
		}
		publish(seen_files);
	};

	var publish = function(new_map) {
		for (var i=0; i < that._subscribers.length; i++) {
			var cb = that._subscribers[i];
			if (cb) {
				cb(new_map);
			}
		}
	};
}

ErrorsModel.GetInstance = function() {
	if (ErrorsModel.Instance == null) {
		var init = function(team) {
			if (ErrorsModel.Instance) {
				ErrorsModel.Instance.dispose();
			}
			// relies on ide.js:Helpers global
			ErrorsModel.Instance = new ErrorsModel(Helpers, team);
		};
		connect(team_selector, 'onchange', init);
		// relies on window.team global
		init(team);
	}
	return ErrorsModel.Instance;
};

// node require() based exports.
if (typeof(exports) != 'undefined') {
	exports.ErrorsModel = ErrorsModel;
}
