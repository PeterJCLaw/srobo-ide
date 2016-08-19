// vim: noexpandtab

// Represents a tab that's being edited
// Managed by EditPage -- do not instantiate outside of EditPage
function EditTab(iea, team, project, path, rev, isReadOnly) {
	// Member functions:
	// Public:
	//  - close: Handler for when the tab is closed: check the contents of the file then close
	//  - is_modified: are the contents of the file modified compared to the original.
	// Private:
	//  - _init: Constructor.
	//  - _check_syntax: Handler for when the "check syntax" button is clicked
	//  - _update_contents: Update the contents of the edit area.
	//  - _show_contents: Update that edit page such that this tab's content is shown
	//  - _capture_code: Store the contents of the edit area.

	//  ** File Contents Related **
	//  - _load_contents: Start the file contents request.
	//  - _recv_contents: Handler for file contents reception.
	//  - _recv_contents_err: Handler for file contents reception errors.
	//  - _file_put: Send the file contents to the server.

	//  ** Save Related **
	//  - _save: Handler for when the save button is clicked.
	//  - _receive_new_fname: Handler for save dialog.
	//  - _receive_commit_msg: Handler for save dialog.
	//  - _repo_save: Save the file to the server.
	//  - _receive_repo_save: Handler for successfully sending to server.
	//  - _error_receive_repo_save: Handler for when a save fails.

	//  ** Tab related **
	//  - _close: Actually close the tab.
	//  - _onfocus: Handler for when the tab receives focus.
	//  - _onblur: Handler for when the tab is blurred.

	// *** Public Properties ***
	if (rev == null || rev == undefined) {
		this.rev = 'HEAD';
	} else {
		this.rev = rev;
	}

	// The team
	this.team = team;
	// The project
	this.project = project;
	// The path
	this.path = path;
	// The current contents
	this.contents = "";
	// The tab representing us
	this.tab = null;

	// *** Private Properties ***
	//true if file is new (unsaved)
	this._isNew = false;
	//the original contents (before edits)
	this._original = "";
	// All our current signal connection idents
	this._signals = [];
	// the ide_editarea instance
	this._iea = iea;
	// the ace session for this tab
	this._session = this._iea.newSession();
	// are we currently setting the session document value?
	// used to avoid responding to change notifications during updates
	this._settingValue = false;
	// Whether the file is opened read-only
	this._read_only = isReadOnly;

	// Have we completed our initial load of the file contents?
	this._loaded = false;
	this._after_load_actions = [];

	this._init = function() {
		this.tab = new Tab( this.path );
		tabbar.add_tab( this.tab );

		// Mark the tab as a edit tab
		this.tab.__edit = true;

		connect( this.tab, "onfocus", bind( this._onfocus, this ) );
		connect( this.tab, "onblur", bind( this._onblur, this ) );
		connect( this.tab, "onclickclose", bind( this.close, this, false ) );

		// change events from our editor
		this._session.on( 'change', bind( this._on_change, this ) );

		if (this.project == null) {
			// New file
			this._isNew = true;
			this._loaded = true;
			this.contents = "";
			this._original = "";
			getElement("check-syntax").disabled = true;
			getElement("edit-diff").disabled = true;
			// force default to python for new files
			this._update_highlight('new.py');
		} else {
			// Existing file
			this._load_contents();
			getElement("check-syntax").disabled = !this._can_check_syntax();
			getElement("edit-diff").disabled = false;
			this._update_highlight();
		}
	};

	this._update_highlight = function(path) {
		// update the syntax highlight if needed
		var mode = this._iea.getTextModeForPath(path || this.path);
		this._session.setMode(mode);
	};

	this._can_check_syntax = function() {
		return !this._isNew && errorspage.can_check(this.path);
	};

	// Start load the file contents
	this._load_contents = function() {
		var args = {
			team: this.team,
			project: this.project,
			path: IDE_path_get_file(this.path),
			rev: this.rev
		};
		IDE_backend_request("file/get", args,
		                    bind(this._recv_contents, this),
		                    bind(this._recv_contents_err, this));
	};

	// Handler for the reception of file contents
	this._recv_contents = function(nodes) {
		var first_load = !this._loaded;
		this._loaded = true;
		this._isNew = false;
		this._original = nodes.original;
		this.contents = this._original;

		this._update_contents();
		this._show_contents();
		this._show_filename();
		this._show_modified();

		if (first_load) {
			for (var i=0; i < this._after_load_actions.length; i++) {
				var action = this._after_load_actions[i];
				action();
			}
			this._after_load_actions = null;
		}
	};

	// Handler for errors in receiving the file contents
	this._recv_contents_err = function() {
		status_button( "Failed to load contents of file " + this.path,
		               LEVEL_ERROR, "retry", bind(this._load_contents, this) );
	};

	this._check_syntax = function() {
		if (!this._can_check_syntax()) {
			return;
		}
		//tell the log and grab the latest contents
		logDebug( "Checking syntax of " + this.path );

		// get the errors page to run the check, after autosaving the file.
		if(this.tab.has_focus()) {
			this._capture_code();
		}
		this._file_put(
			bind( errorspage.check, errorspage, this.path, { alert: true },true ),
			partial( status_button, "Unable to check syntax", LEVEL_WARN,
			         "retry", bind(this._check_syntax, this) )
		);
	};

	this._diff = function() {
		//tell the log and grab the latest contents
		log( "Showing diff of " + this.path );
		this._capture_code();

		//throw the contents to the diff page, if changed
		if (this._original != this.contents) {
			diffpage.diff(this.path, this.rev, this.contents);
		} else {
			status_msg("File not modified", LEVEL_OK);
		}
	};

	this._receive_new_fname = function(fpath, commitMsg) {
		var a = fpath.split( "/", 2 );

		if (a.length == 2 ) {
			editpage.rename_tab(this.path, fpath);
			this.project = a[1];
			this.path = fpath;
			this._repo_save(commitMsg);
			this._update_highlight();
		} else {
			status_msg( "No project specified", LEVEL_ERROR );
		}
	};

	this._receive_commit_msg = function(commitMsg) {
		this._repo_save(commitMsg);
	};

	this._save = function() {
		//do an update
		this._capture_code();

		//no point saving an unmodified file
		if (this._original == this.contents) {
			log('File not modified!');
			status_msg("File not modified!", LEVEL_WARN);
			return;
		}

		//if new file	-- TODO
		if (this._isNew) {
			var fileBrowser = new Browser(bind(this._receive_new_fname, this), {'type' : 'isFile'});
		} else {
			var fileBrowser = new Browser(bind(this._receive_commit_msg, this), {'type' : 'isCommit'});
			fileBrowser.showDiff(this.path, this.rev, this.contents);
		}
	};

	//ajax event handler for saving to server
	this._receive_repo_save = function(nodes) {
		projpage.flist.refresh();

		if (nodes.merges.length == 0) {
			status_msg("File "+this.path+" Saved successfully (Now at "+nodes.commit+")", LEVEL_OK);
			this._original = this.contents;
			this.tab.set_label(this.path);
			if (user.get_setting('save.autoerrorcheck') != false && this._can_check_syntax()) {
				errorspage.check(this.path, { alert: true, quietpass: true }, false);
			}
		} else {
			status_msg("File "+this.path+" Merge required, please check and try again (Now at "+nodes.commit+")", LEVEL_ERROR);
			this.contents = nodes.code;
		}
		this.rev = nodes.commit;
		if (this._isNew) {
			this._show_filename();
			this._isNew = false;
		}
		this._get_revisions();
		getElement("check-syntax").disabled = !this._can_check_syntax();
		getElement("edit-diff").disabled = false;
		this._update_contents();
		this._iea.focus();
	};

	//ajax event handler for saving to server
	this._error_receive_repo_save = function() {
		status_button("Could not save file", LEVEL_ERROR, "retry", bind(this._repo_save, this));
	};

	//save file contents to server as new revision
	this._repo_save = function(commit_message) {
		var put_success = bind(function() {
			var args = {
				team: team,
				project: IDE_path_get_project(this.path),
				paths: [IDE_path_get_file(this.path)],
				message: commit_message
			};
			IDE_backend_request("proj/commit", args,
				bind(this._receive_repo_save, this),
				bind(this._error_receive_repo_save, this));
		}, this);
		this._file_put(put_success, bind(this._error_receive_repo_save, this));
	};

	//send the content of the file to the backend in preparation for another action
	this._file_put = function(success_cb, error_cb) {
		var args = {
			team: team,
			project: IDE_path_get_project(this.path),
			path: IDE_path_get_file(this.path),
			data: this.contents
		};
		IDE_backend_request("file/put", args, success_cb, error_cb);
	};

	this._on_keydown = function(ev) {
		//since this call could come from EditArea we have to disregard mochikit nicities
		var e;
		if (ev != 'auto' && typeof ev._event == 'object') {
			e = ev._event;
		} else {
			e = ev;
		}

		//Ctrl+s or Cmd+s: do a save
		if ( ((e.ctrlKey && !e.altKey) || e.metaKey)  && e.keyCode == 83 ) {
			this._save();
			// try to prevent the browser doing something else
			kill_event(ev);
		}
	};

	this._on_change = function(e) {
		if (!this._settingValue) {
			this._show_modified();
		}
	};

	//called when the code in the editarea changes.
	this._on_keyup = function() {
		this._show_modified();
	};

	this._show_modified = function() {
		//Handle modified notifications
		if( this.is_modified() ) {
			this.tab.set_label("*" + this.path);
		} else {
			this.tab.set_label(this.path);
		}
	};

	this.is_modified = function() {
		if (this.tab.has_focus()) {	//if we have focus update the code
			this._capture_code();
		}

		if (this.contents != this._original) {	//compare to the original
			return true;
		} else {
			return false;
		}
	};

	// Search the files' buffer for the given query.
	// Returns an array of objects for each line where the query item was found.
	// The objects will have two members: "line" and "text".
	// The array will be empty if no matches were found.
	this.search = function(query) {
		var matchLines = [];
		var document = this._session.getDocument();
		var lines = document.getAllLines();
		for (var i=0; i<lines.length; i++) {
			var text = lines[i];
			if (text.indexOf(query) >= 0) {
				matchLines.push({ line: i, text: text });
			}
		};
		return matchLines;
	};

	//try to close a file, checking for modifications, return true if it's closed, false if not
	this.close = function(override) {
		if (override != true && this.is_modified()) {
			tabbar.switch_to(this.tab);
			status_button(this.path+" has been modified!", LEVEL_WARN, "Close Anyway", bind(this._close, this));
			return false;
		} else {
			this._close();
			return true;
		}
	};

	//actually close the tab
	this._close = function() {
		signal( this, "onclose", this );
		this.tab.close();
		this._session.removeAllListeners('change');
		disconnectAll(this);
		status_hide();
	};

	// Handler for when the tab receives focus
	this._onfocus = function() {
		// Close handler
		this._signals.push( connect( "close-edit-area",
					     "onclick",
					     bind( this.close, this, false ) ) );
		// Check syntax handler
		var checkSyntaxElem = getElement("check-syntax");
		checkSyntaxElem.disabled = !this._can_check_syntax();
		this._signals.push( connect( checkSyntaxElem,
					     "onclick",
					     bind( this._check_syntax, this ) ) );

		// Diff view handler
		var diffElem = getElement('edit-diff');
		diffElem.disabled = this._read_only || this._isNew;
		if (!this._read_only) {
			this._signals.push( connect(diffElem,
			                            'onclick',
			                            bind(this._diff, this))
			                  );
		}
		// Save handler
		var saveElem = getElement('save-file');
		saveElem.disabled = this._read_only;
		if (!this._read_only) {
			this._signals.push( connect(saveElem,
			                            'onclick',
			                            bind(this._save, this))
			                  );
		}
		// change revision handler
		this._signals.push( connect( "history",
					     "onclick",
					     bind( this._change_revision, this ) ) );

		// keyboard shortcuts
		this._signals.push( connect( document,
					    "onkeydown",
					    bind( this._on_keydown, this ) ) );

		this._show_contents();
		this._show_filename();
		this._iea.setReadOnly(this._read_only);
		this._iea.focus();
	};

	// Handler for when the tab loses focus
	this._onblur = function() {
		// Disconnect all the connected signal
		map( disconnect, this._signals );
		this._signals = [];

		//don't loose changes to file content
		this._capture_code();
	};

	this._update_contents = function() {
		// setting the content often moves the caret,
		// so don't do it if we don't need to
		if (this.contents == this._session.getValue()) {
			return;
		}

		this._settingValue = true;
		this._session.setValue( this.contents );
		this._settingValue = false;
	};

	this._show_contents = function() {
		this._receive_revisions({log: []});
		this._get_revisions();

		this._iea.setSession( this._session );

		this._show_filename();
	};

	this._show_filename = function() {
		// Display file path
		var t = this.path;
		if (this.rev != 0) {
			t = t + " - " + IDE_hash_shrink(this.rev);
		}
		if (this._read_only) {
			t += ' [read-only]';
		}
		replaceChildNodes( "tab-filename", t );
	};

	//call this to update this.contents with the current contents of the edit area and to grab the current cursor position
	this._capture_code = function() {
		this.contents = this._session.getValue();
	};

	this._change_revision = function() {
		var rev = getElement("history").value;
		switch(rev) {
			case "-2":
				var d = new Log(this.path);
				break;
			case "-1":
				break;
			default:
				this.open_revision(rev, false);
		}
	};

	this.open_revision = function(rev, override) {
		this._capture_code();
		if (override != true && this.contents != this._original) {
			status_button(this.path + " has been modified!", LEVEL_WARN,
				"Go to revision " + IDE_hash_shrink(rev) + " anyway",
				bind(this.open_revision, this, rev, true)
			);
		} else {
			this._mode = 'REPO';
			this.rev = rev;
			status_msg("Opening history .. " + rev, LEVEL_OK);
			this._load_contents();
		}
	};

	this._receive_revisions = function(nodes) {
		var histDate = function(which) {
			var stamp = nodes.log[which].time;
			var d = new Date(stamp*1000);
			return d.toDateString();
		};

		if (nodes.log.length == 0) {
			replaceChildNodes("history", OPTION({'value' : -1}, "No File History!"));
		} else {
			replaceChildNodes("history", OPTION({'value' : -1}, "Select File Revision"));
			for(var i=0; i < nodes.log.length; i++) {
				var author = nodes.log[i].author;
				var pos = author.lastIndexOf('<');
				if (pos >= 0) {
					author = author.substring(0, pos-1);
				}
				appendChildNodes("history",
					OPTION( { value: nodes.log[i].hash, title: nodes.log[i].message },
						IDE_hash_shrink(nodes.log[i].hash) +
						" " + histDate(i) + " [" + author + "]"
					)
				);
			}

			appendChildNodes("history", OPTION({'value' : -2}, "--View Full History--"));
		}
	};

	this._error_receive_revisions = function() {
		status_msg("Couldn't retrieve file history", LEVEL_ERROR);
	};

	this._get_revisions = function() {
		logDebug("retrieving file history");
		// Don't even try to get revisions if we know that it's a new file
		// or if it's not loaded yet (we'll be called again once it is)
		if (this._isNew || !this._loaded) {
			return;
		}
		var args = {
		    team: team,
		 project: IDE_path_get_project(this.path),
		    path: IDE_path_get_file(this.path)
		};
		IDE_backend_request('file/log', args,
			bind(this._receive_revisions, this),
			bind(this._error_receive_revisions, this)
		);
	};

	// Sets the selection in the editor as a line relative position.
	// If length is -1 this is treated as the end of the line.
	// If length would push the selection onto multiple lines its value is truncated to the end of the current line.
	// Note that lines are indexed from 1.
	this.setSelectionRange = function(lineNumber, startIndex, length) {
		if (this._loaded) {
			this._setSelectionRange(lineNumber, startIndex, length);
		} else {
			this._after_load_actions.push(bind(this._setSelectionRange, this, lineNumber, startIndex, length));
		}
	};

	this._setSelectionRange = function(lineNumber, startIndex, length) {
		var Range = require("ace/range").Range;
		lineNumber -= 1;
		var endIndex = startIndex + length;
		if (endIndex == -1) {
			var lineText = this._session.getLine(lineNumber);
			if (lineText != null) {
				endIndex = lineText.length;
			}
		}
		var range = new Range(lineNumber, startIndex, lineNumber, endIndex);
		this._iea.setSelectionRange(range);
	};

	// Marks the given set of errors in the editor.
	this.mark_errors = function(errors) {
		if (this._loaded) {
			this._mark_errors(errors);
		} else {
			this._after_load_actions.push(bind(this._mark_errors, this, errors));
		}
	};

	this._mark_errors = function(errors) {
		var annotations = [];
		for (var i = 0; i < errors.length; i++) {
			var error = errors[i];
			annotations.push({ row: error.lineNumber - 1,
			                column: 0,
			                  text: error.message,
			                  type: error.level });
		}
		this._session.setAnnotations(annotations);
	};

	// Clears all the marked errors from the editor
	this.clear_errors = function() {
		this._session.clearAnnotations();
	};

	//initialisation
	this._init();
}
