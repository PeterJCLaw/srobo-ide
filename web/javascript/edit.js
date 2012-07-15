// vim: noexpandtab
// The class for the EditPage
function EditPage() {

	// Member functions:
	// Public functions:
	//  - edit_file: Open the given team, project and file path.
	//  - new_file: Create a new edit tab with no filename.
	//  - rename_tab: Rename a tab's parent object.
	//  - close_all_tabs: Close all the tabs, prompting for a save if needed.

	// Private functions:
	//  - _init: Initialises the edit page (called on instantiation)
	//  - _show: Show the edit page.
	//	     Triggers initialisation of the editarea if necessary
	//  - _hide: Hide the edit page.
	//  - _new_etab: Creates a new instance of an EditTab and wire it
	//		 up to a Tab
	//		 TODO: Can we get EditTab to do this for us?
	//  - _file_get_etab: Given a file path, returns the tab for it.
	//		      If the file isn't currently open, return null.
	//  - _tab_switch: Handler for the onswitch event of the tab bar.
	//		   TODO: Make this remove the tab from our list.
	//  - _is_edit: Returns try if the given tab is an edit tab

	// Private properties:
	// Dict of open files.  Keys are paths, values are EditTab instances.
	this._open_files = {};

	this.textbox = null;
	this._iea = null;

	// The number of new files so far
	this._new_count = 0;

	// Initialise the edit page
	this._init = function() {
		connect( tabbar, "onswitch", bind( this._tab_switch, this ) );

		this.textbox = DIV({"id" : "editpage-editarea",
				    "style" : 'width: 100%; height: 90%; position:absolute; text-align: left' });
		appendChildNodes($("edit-mode"), this.textbox);

		this._iea = new ide_editarea("editpage-editarea");
	}

	// Show the edit page
	this._show = function() {
		setStyle($("edit-mode"), {"display" : "block"});
	}

	// Hide the edit page
	this._hide = function() {
		setStyle($("edit-mode"), {"display" : "none"});
	}

	//Is the given file open?
	this.is_open = function( file ) {
		return this._open_files[file] != null;
	}

	// Open the given file and switch to the tab
	// or if the file is already open, just switch to the tab
	this.edit_file = function( team, project, path, rev, mode ) {
		// TODO: We don't support files of the same path being open in
		// different teams at the moment.
		var etab = this._file_get_etab( path );
		var newTab = false;

		if( etab == null ) {
			etab = this._new_etab( team, project, path, rev, mode );
			newTab = true;
		}

		tabbar.switch_to( etab.tab );

		// If they've specified a revision then change to it
		// NB: we need to do this *after* switching to the tab so that it's shown, otherwise editarea explodes
		if ( !newTab && rev != null ) {
			etab.open_revision(rev, false);
		}
		return etab;
	}

	// Create a new tab with a new file open in it
	this.new_file = function() {
		if(!projpage.projects_exist()) {
			status_msg("You must create a project before creating a file", LEVEL_ERROR);
			return;
		}
		this._new_count ++;
		var fname = "New File " + this._new_count;
		var etab = this._new_etab( team, null, fname, 0 );
		tabbar.switch_to( etab.tab );
	}

	this.rename_tab = function(old, New) {
		this._open_files[New] = this._open_files[old];
		this._open_files[old] = null;
		this._open_files[New].tab.set_label( New );
	}

	//close a tab, if it's open, return true if it's closed, false otherwise
	this.close_tab = function(name, override) {
		if(this.is_open(name))
			return this._open_files[name].close(override);
		else
			return true;
	}

	this.close_all_tabs = function(override) {
		mod_count	= 0;
		for ( var i in this._open_files ) {	//find which are modified and close the others
			if(this._open_files[i] !== null) {
				logDebug('checking '+i);
				if(this._open_files[i].is_modified() && override != true) {
					logDebug(i+' is modified, logging');
					mod_count	+= 1;
					mod_file	= i;
				} else {
					logDebug('closing '+i);
					this._open_files[i].close(override);
				}
			}
		}
		if(mod_count > 0) {
			if(mod_count == 1)
				this._open_files[mod_file].close(false);
			else
				status_button(mod_count+' files have been modified!', LEVEL_WARN, 'Close Anyway', bind(this.close_all_tabs, this, true));
			return false;
		} else
			return true;
	}

	// Create a new tab that's one of ours
	// Doesn't load the tab
	this._new_etab = function(team, project, path, rev, mode) {
		var etab = new EditTab(this._iea, team, project, path, rev, mode);

		connect( etab, "onclose", bind( this._on_tab_close, this ) );

		this._open_files[path] = etab;
		return etab;
	}

	// Return the tab for the given file path
	// returns null if not open
	this._file_get_etab = function( path ) {
		for( var i in this._open_files ) {
			if( i == path && this._open_files[i] !== null )
				return this._open_files[i];
		}
		return null;
	}

	// Handler for when the tab has been closed
	this._on_tab_close = function( etab ) {
		// Remove tab from our list
		for( var i in this._open_files ) {
			if( this._open_files[i] === etab ) {
				this._open_files[i] = null;
				break;
			}
		}
	}

	this._tab_switch = function( fromtab, totab ) {
		if( !this._is_edit( totab ) ) {
			this._hide();
			return;
		}

		if( !this._is_edit( fromtab ) )
			this._show();
	}

	// Return true if the given tab is an edit tab
	this._is_edit = function(tab) {
		if( tab !== null && tab !== undefined
		    && tab.__edit === true )
			return true;
		return false;
	}

	this._init();

}

// Represents a tab that's being edited
// Managed by EditPage -- do not instantiate outside of EditPage
function EditTab(iea, team, project, path, rev, mode) {
	// Member functions:
	// Public:
	//  - close: Handler for when the tab is closed: check the contents of the file then close
	//  - is_modified: are the contents of the file modified compared to the original.
	// Private:
	//  - _init: Constructor.
	//  - _check_syntax: Handler for when the "check syntax" button is clicked
	//  - _update_contents: Update the contents of the edit area.
	//  - _capture_code: Store the contents of the edit area.

	//  ** File Contents Related **
	//  - _load_contents: Start the file contents request.
	//  - _recv_contents: Handler for file contents reception.
	//  - _recv_contents_err: Handler for file contents reception errors.

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
	if(rev == null || rev == undefined)
		this.rev = 0;
	else
		this.rev = rev;

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
	//The commit message
	this._commitMsg = "Default Commit Message";
	//the original contents (before edits)
	this._original = "";
	// All our current signal connection idents
	this._signals = [];
	// The "Failed to load contents" of file status message:
	this._stat_contents = null;
	// the ide_editarea instance
	this._iea = iea;
	//this will host the delay for the autosave
	this._timeout = null;
	//the time in seconds to delay before saving
	this._autosave_delay = 25;
	this._autosave_retry_delay = 7;
	//the contents at the time of the last autosave
	this._autosaved = "";
	// whether we're loading from the vcs repo or an autosave
	this._mode = mode;
	//the cursor selection
	this._selection_range = null;
	// whether or not we've loaded the file contents yet.
	this._loaded = false;

	this._init = function() {
		this.tab = new Tab( this.path );
		tabbar.add_tab( this.tab );

		// Mark the tab as a edit tab
		this.tab.__edit = true;

		// Link ourselves to the tab so the EditPage can find us
		this.tab.__etab = this;

		connect( this.tab, "onfocus", bind( this._onfocus, this ) );
		connect( this.tab, "onblur", bind( this._onblur, this ) );
		connect( this.tab, "onclickclose", bind( this.close, this, false ) );

		if( this.project == null ) {
			// New file
			this._isNew = true;
			this.contents = "";
			this._original = "";
			$("check-syntax").disabled = true;
			this._loaded = false;
		} else {
			// Existing file
			this._load_contents();
			$("check-syntax").disabled = false;
		}
	}

	// Start load the file contents
	this._load_contents = function() {
		IDE_backend_request("file/get", { team : this.team,
		                   project: this.project,
						   path : IDE_path_get_file(this.path),
						   rev : this.rev },
						   bind(this._recv_contents, this),
						   bind(this._recv_contents_err, this));
	}

	// Handler for the reception of file contents
	this._recv_contents = function(nodes) {
		this._isNew = false;
		this._original = nodes.original;
		this._autosaved = nodes.autosaved || null;

		if(this._mode == 'AUTOSAVE') {
			this.contents = this._autosaved;
		} else {
			this.contents = this._original;
		}

		this._loaded = true;

		this._update_contents();
		this._show_modified();
	}

	// Handler for errors in receiving the file contents
	this._recv_contents_err = function() {
		this._stat_contents = status_button( "Failed to load contents of file " + this.path,
						     LEVEL_ERROR,
						     "retry", bind( this._load_contents, this ) );
	}

	this._check_syntax = function() {
		//tell the log and grab the latest contents
		logDebug( "Checking syntax of " + this.path );

		// get the errors page to run the check, after autosaving the file.
		this._autosave(
			bind( errorspage.check, errorspage, this.path, { alert: true },true ),
			partial( status_button, "Unable to check syntax", LEVEL_WARN,
				"retry", bind(this._check_syntax, this) )
		);
	}

	this._diff = function() {
		//tell the log and grab the latest contents
		log( "Showing diff of " + this.path );
		this._capture_code();

		//throw the contents to the diff page, if changed
		if(this._original != this.contents)
			diffpage.diff(this.path, this.rev, this.contents);
		else
			status_msg("File not modified", LEVEL_OK);

	}

	this._receive_new_fname = function(fpath, commitMsg) {
		var a = fpath.split( "/", 2 );

		if (a.length == 2 ) {
			editpage.rename_tab(this.path, fpath);
			this.project = a[1];
			this.path = fpath;
			this._commitMsg = commitMsg;
			this._repo_save();
		} else
			status_msg( "No project specified", LEVEL_ERROR );
	}

	this._receive_commit_msg = function(commitMsg) {
		this._commitMsg = commitMsg;
		this._repo_save();
	}

	this._save = function() {
		//do an update
		this._capture_code();

		//no point saving an unmodified file
		if(this._original == this.contents) {
			log('File not modified!');
			status_msg("File not modified!", LEVEL_WARN);
			return;
		}

		//if new file	-- TODO
		if(this._isNew) {
			var fileBrowser = new Browser(bind(this._receive_new_fname, this), {'type' : 'isFile'});
		} else {
			var fileBrowser = new Browser(bind(this._receive_commit_msg, this), {'type' : 'isCommit'});
			fileBrowser.showDiff(this.path, this.rev, this.contents);
		}
	}

	//ajax event handler for saving to server
	this._receive_repo_save = function(nodes){
		projpage.flist.refresh();

		if (nodes.merges.length == 0) {
			status_msg("File "+this.path+" Saved successfully (Now at "+nodes.commit+")", LEVEL_OK);
			this._original = this.contents;
			this.tab.set_label(this.path);
			this._autosaved = "";
			if (user.get_setting('save.autoerrorcheck') != false)
			{
				errorspage.check(this.path, { alert: true, quietpass: true }, false);
			}
		} else {
			status_msg("File "+this.path+" Merge required, please check and try again (Now at "+nodes.commit+")", LEVEL_ERROR);
			this.contents = nodes.code;
		}
		this._isNew = false;
		this.rev = nodes.commit;
		$("check-syntax").disabled = false;
		this._update_contents();
	}

	//ajax event handler for saving to server
	this._error_receive_repo_save = function() {
		status_button("Could not save file", LEVEL_ERROR, "retry", bind(this._repo_save, this));
	}

	//save file contents to server as new revision
	this._repo_save = function() {
		IDE_backend_request("file/put", {team: team,
		                                 project: IDE_path_get_project(this.path),
		                                 path: IDE_path_get_file(this.path),
		                                 data: this.contents},
		                    bind(function() {
		                    	IDE_backend_request("proj/commit", {team: team,
		                    	                                 project: IDE_path_get_project(this.path),
                                                                 paths: [IDE_path_get_file(this.path)],
		                    	                                 message: this._commitMsg},
		                    	                                 bind(this._receive_repo_save, this),
		                    	                                 bind(this._error_receive_repo_save, this));

		                    }, this),
		                    bind(this._error_receive_repo_save, this));
	}

	this._on_keydown = function(ev) {
		//since this call could come from EditArea we have to disregard mochikit nicities
		if(ev != 'auto' && typeof ev._event == 'object')
			var e = ev._event;
		else
			var e = ev;

		//Ctrl+s or Cmd+s: do a save
		if( e != 'auto' && (e.ctrlKey || e.metaKey) && e.keyCode == 83 ) {
			this._save();
			// try to prevent the browser doing something else
			kill_event(ev);
		}

		//any alpha or number key or a retry: think about autosave
		if( e == 'auto' || (e.keyCode >= 65 && e.keyCode <= 90) || (e.keyCode >= 48 && e.keyCode <= 57) ) {
			if( this._timeout != null )
				this._timeout.cancel();
			if( e == 'auto' )
				var delay = this._autosave_retry_delay;
			else
				var delay = this._autosave_delay;
			this._timeout = callLater(delay, bind(this._autosave, this));
		}
	}

	//called when the code in the editarea changes. TODO: get the autosave to use this interface too
	this._on_keyup = function() {
		this._show_modified();
	}

	this._show_modified = function() {
		//Handle modified notifications
		if( this.is_modified() ) {
			this.tab.set_label("*" + this.path);
		} else {
			this.tab.set_label(this.path);
		}
	}

	this._autosave = function(cb, errCb) {
		var cb = cb || null;
		var errCb = errCb || bind(this._on_keydown, this, 'auto');
		this._timeout = null;
		// do an update (if we have focus) and check to see if we need to autosave
		if(this.tab.has_focus())
		{
			this._capture_code();
		}

		// If there's no change and no callback then bail
		if( (this.contents == this._original || this.contents == this._autosaved)
		  && cb == null )
		{
			return;
		}

		logDebug('EditTab: Autosaving '+this.path);

		IDE_backend_request("file/put", {team: team,
		                                 project: this.project,
		                                 path: IDE_path_get_file(this.path),
		                                 rev: this.rev,
		                                 data: this.contents},
		                                 bind(this._receive_autosave, this, this.contents, cb),
		                                 errCb);
	}

	//ajax event handler for autosaving to server, based on the one for commits
	this._receive_autosave = function(code, cb){
		this._autosaved = code;
		projpage.flist.refresh('auto');
		if (typeof cb == 'function') {
			cb();
		}
	}

	this.is_modified = function() {
		if(this.tab.has_focus())	//if we have focus update the code
			this._capture_code();

		if(this.contents != this._original)	//compare to the original
			return true;
		else
			return false;
	}

	//try to close a file, checking for modifications, return true if it's closed, false if not
	this.close = function(override) {
		if( override != true && this.is_modified() ) {
			tabbar.switch_to(this.tab);
			status_button(this.path+" has been modified!", LEVEL_WARN, "Close Anyway", bind(this._close, this));
			return false;
		} else {
			this._close();
			return true;
		}
	}

	//actually close the tab
	this._close = function() {
		signal( this, "onclose", this );
		this.tab.close();
		disconnectAll(this);
		status_hide();
	}

	// Handler for when the tab receives focus
	this._onfocus = function() {
		// Close handler
		this._signals.push( connect( $("close-edit-area"),
					     "onclick",
					     bind( this.close, this, false ) ) );
		// Check syntax handler
		if(this._isNew) {
			$("check-syntax").disabled = true;
		} else {
			$("check-syntax").disabled = false;
		}
		this._signals.push( connect( $("check-syntax"),
					     "onclick",
					     bind( this._check_syntax, this ) ) );

		// Diff view handler
		this._signals.push( connect( $("edit-diff"),
					     "onclick",
					     bind( this._diff, this ) ) );
		// Save handler
		this._signals.push( connect( $("save-file"),
					     "onclick",
					     bind( this._save, this ) ) );
		// change revision handler
		this._signals.push( connect( "history",
					     "onclick",
					     bind( this._change_revision, this ) ) );
		// keyboard shortcuts when the cursor is inside editarea
		this._signals.push( connect( window,
					    "ea_keydown",
					    bind( this._on_keydown, this ) ) );
		// keyup handler
		this._signals.push( connect( window,
					    "ea_keyup",
					    bind( this._on_keyup, this ) ) );
		// keyboard shortcuts
		this._signals.push( connect( document,
					    "onkeydown",
					    bind( this._on_keydown, this ) ) );
		this._update_contents();
		this._iea.focus();
	}

	// Handler for when the tab loses focus
	this._onblur = function() {
		// Disconnect all the connected signal
		map( disconnect, this._signals );
		this._signals = [];

		//don't loose changes to file content
		this._capture_code();
	}

	this._update_contents = function() {
		logDebug('_update_contents');
		// if we don't have focus or aren't loaded, then don't try to change things - we'll get called again when we get focus
		if(!this.tab.has_focus() || !this._loaded)
			return;

		this._iea.setValue( this.contents );
		this._iea.setSelectionRange( this._selection_range );

	 	this._get_revisions();

		// Display file path
		var t = this.path;
		if( this.rev != 0 )
			t = t + " - " + IDE_hash_shrink(this.rev);
		replaceChildNodes( $("tab-filename"), t );
	}

	//call this to update this.contents with the current contents of the edit area and to grab the current cursor position
	this._capture_code = function() {
		this.contents = this._iea.getValue();

		this._selection_range = this._iea.getSelectionRange();
	}

	this._change_revision = function() {
		var rev = $("history").value;
		switch(rev) {
		case "-2":
			var d = new Log(this.path);
			break;
		case "-1":
			break;
		default:
			this.open_revision(rev, false)
		}
	}

	this.open_revision = function(rev, override) {
		this._capture_code();
		if( override != true && this.contents != this._original ) {
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
	}

	this._receive_revisions = function(nodes) {
		var histDate = function(which) {
			var stamp = nodes.log[which].time;
			var d = new Date(stamp*1000);
			return d.toDateString();
		}

		if(nodes.log.length == 0) {
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
	}

	this._error_receive_revisions = function() {
		status_msg("Couldn't retrieve file history", LEVEL_ERROR);
	}

	this._get_revisions = function() {
		logDebug("retrieving file history");
		this._receive_revisions({log: []});
		// Don't even try to get revisions if we know that it's a new file
		if(this._isNew) {
			return;
		}
        IDE_backend_request('file/log', {
                team: team,
             project: IDE_path_get_project(this.path),
                path: IDE_path_get_file(this.path)
            },
            bind(this._receive_revisions, this),
            bind(this._error_receive_revisions, this)
        );
    }

	//initialisation
	this._init();
}

// A fractionally nicer interface to the editarea
// Cleans up the loading interface in particular.
// Things don't explode as much if you try to do something to it before the
// editarea has loaded, or when it's invisible.

// Emits these signals:
//  - onload: Emitted when the editarea has finished loading
function ide_editarea(id) {
	// Public functions:
	//  - getSelectionRange() -- get the cursor selection range, no need to pass the id, load safe
	//  - setSelectionRange() -- set the cursor selection range, no need to pass the id, load safe

	// Public properties:
	this._id = id;
	this._open_queue = [];
	this._close_queue = [];
	this._value = null;
	this._ace = null;

	this._init = function() {
		this._ace = ace.edit( this._id );
		var PythonMode = require( "ace/mode/python" ).Mode;
		this._ace.getSession().setMode( new PythonMode );

		//focus to the top of the file
		this.setSelectionRange(0,0);

		signal( this, "onload" );
	}

	this.getSelectionRange = function() {
		// return this._ace.getSelectionRange();
	}

	this.setSelectionRange = function( range ) {
		if( range != null ) {
			// this._ace.getSelection().setSelectionRange( range );
		}
	}

	this.setValue = function( contents ) {
		this._ace.getSession().setValue( contents );
	}

	this.getValue = function() {
		return this._ace.getSession().getValue();
	}

	this.focus = function() {
		return this._ace.focus();
	}

	this._init();
}

// Called when the editarea changes
function ea_keyup(e) {
	// Rebroadcast the signal
	signal(this, "ea_keyup", e);
}

// Called when the editarea is due for an autosave
function ea_autosave(e) {
	// Rebroadcast the signal
	on_doc_keydown(e);
	signal(this, "ea_keydown", e);
}
