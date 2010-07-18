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

		this.textbox = TEXTAREA({"id" : "editpage-editarea",
					"style" : 'width: 99%; height: 500px;',
					"value" : "" });
		appendChildNodes($("edit-mode"), this.textbox);

		this._iea = new ide_editarea("editpage-editarea");
	}

	// Show the edit page
	this._show = function() {
		setStyle($("edit-mode"), {"display" : "block"});

		this._iea.show();
	}

	// Hide the edit page
	this._hide = function() {
		this._iea.hide();

		setStyle($("edit-mode"), {"display" : "none"});
	}

	//Is the given file open?
	this.is_open = function( file ) {
		return this._open_files[file] != null;
	}

	// Open the given file and switch to the tab
	// or if the file is already open, just switch to the tab
	this.edit_file = function( team, project, path, rev , mode) {
		// TODO: We don't support files of the same path being open in
		// different teams at the moment.
		var etab = this._file_get_etab( path );

		if( etab == null ) {
			etab = this._new_etab( team, project, path, rev, mode );
		}

		tabbar.switch_to( etab.tab );
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
		for ( i in this._open_files ) {	//find which are modified and close the others
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

	// The revision number the specific file being edited was last modified
	this.file_rev = 0;
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
	this._selection_start = 0;
	this._selection_end = 0;

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
		} else {
			// Existing file
			this._load_contents();
			$("check-syntax").disabled = false;
		}
	}

	// Start load the file contents
	this._load_contents = function() {
		var d = loadJSONDoc("./filesrc", { team : this.team,
						   file : this.path,
						   revision : this.rev});

		d.addCallback( bind(this._recv_contents, this));
		d.addErrback( bind(this._recv_contents_err, this));
	}

	// Handler for the reception of file contents
	this._recv_contents = function(nodes) {
		if( this._stat_contents != null ) {
			this._stat_contents.close();
			this._stat_contents = null;
		}

		if(this._mode == 'AUTOSAVE')
			this.contents = nodes.autosaved_code;
		else
			this.contents = nodes.code;
		this._original = nodes.code;
		this._autosaved = nodes.autosaved_code;
		this._isNew = false;
		this.rev = nodes.revision;
		this.file_rev = nodes.file_rev;

		this._update_contents();
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
		this._capture_code();

		//throw the contents to the backend, if needed
		if(this._original != this.contents)
			opts = {'alert' : true, 'code' : this.contents }
		else
			opts = {'alert' : true}

		//get the errors page to run the check
		errorspage.check(this.path, opts);
	}

	this._diff = function() {
		//tell the log and grab the latest contents
		log( "Showing diff of " + this.path );
		this._capture_code();

		//throw the contents to the diff page, if changed
		if(this._original != this.contents)
			diffpage.diff(this.path, this.rev, this.contents);
		else
			status_msg("File not modified!", LEVEL_WARN);

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
		}
	}

	//ajax event handler for saving to server
	this._receive_repo_save = function(nodes){
		projpage.flist.refresh();

		switch(nodes.success){
			case "True":
				status_msg("File "+this.path+" Saved successfully (Now at r"+nodes.new_revision+")", LEVEL_OK);
				this._original = this.contents;
				this.tab.set_label(this.path);
				this._autosaved = "";
				this._isNew = false;
				this.rev = nodes.new_revision;
				this.file_rev = nodes.new_revision;
				$("check-syntax").disabled = false;
 				this._update_contents();
				break;
			case "AutoMerge":
				status_msg("File "+this.path+" Automatically merged with latest revision (Now at r"+nodes.new_revision+")", LEVEL_OK);
				this.contents = nodes.code;
				this.tab.set_label(this.path);
				this._original = this.contents;
				this._autosaved = "";
				this._isNew = false;
				this.rev = nodes.new_revision;
				this.file_rev = nodes.new_revision;
				$("check-syntax").disabled = false;
 				this._update_contents();
				break;
			case "Merge":
				status_msg("File "+this.path+" Merge required, please check and try again (Now at r"+nodes.new_revision+")", LEVEL_ERROR);
				this.contents = nodes.code;
				this._isNew = false;
				this.rev = nodes.new_revision;
				$("check-syntax").disabled = false;
				this._update_contents();
				break;
			case "Error creating new directory":
				status_msg("Error creating new directory (New Revision: "+nodes.new_revision+")", LEVEL_ERROR);
				break;
			case "Invalid filename" :
				status_msg("Save operation failed, Invalid filename (New Revision: "+nodes.new_revision+")", LEVEL_ERROR);
				break;
		}
	}

	//ajax event handler for saving to server
	this._error_receive_repo_save = function() {
		status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this._repo_save, this));
	}

	//save file contents to server as new revision
	this._repo_save = function() {
		var d = postJSONDoc("./savefile", {
					queryString : { team : team,
						filepath : this.path,
						rev : this.rev,
						message : this._commitMsg },
					sendContent : {code : this.contents}
				});

		d.addCallback( bind(this._receive_repo_save, this));
		d.addErrback( bind(this._error_receive_repo_save, this));
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

	this._autosave = function() {
		this._timeout = null;
		//do an update and check to see if we need to autosave
		this._capture_code();
		if(this.contents == this._original || this.contents == this._autosaved)
			return;

		logDebug('EditTab: Autosaving '+this.path)

		var d = postJSONDoc("./autosave/savefile", {
					queryString : { team : team,
						path : this.path,
						rev : this.rev },
					sendContent : {content : this.contents}
				});

		d.addCallback( bind(this._receive_autosave, this));
		d.addErrback( bind(this._on_keydown, this, 'auto'));	//if it fails then set it up to try again
	}

	//ajax event handler for autosaving to server, based on the one for commits
	this._receive_autosave = function(reply){
		if(typeof reply.date != 'undefined') {
			this.autosaved = reply.code;
			projpage.flist.refresh();
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
			this._signals.push( connect( $("check-syntax"),
					     "onclick",
					     bind( this._check_syntax, this ) ) );
		}
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
					     bind( this._change_revision, this, false ) ) );
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
		//if we don't have focus then don't try to change things - we'll get called again when we get focus
		if(!this.tab.has_focus())
			return;

		this._iea.setValue( this.contents );
		this._iea.setSelectionRange( this._selection_start, this._selection_end );

	 	this._get_revisions();

		// Display file path
		var t = this.path;
		if( this.file_rev != 0 )
			t = t + " - r" + this.file_rev;
		replaceChildNodes( $("tab-filename"), t );
	}

	//call this to update this.contents with the current contents of the edit area and to grab the current cursor position
	this._capture_code = function() {
		this.contents = this._iea.getValue();

		var selection = this._iea.getSelectionRange();
		this._selection_start = selection.start;
		this._selection_end = selection.end;
	}

	this._change_revision = function(override) {
		switch($("history").value) {
		case "-2":
			var d = new Log(this.path);
			break;
		case "-1":
			break;
		default:
			this._capture_code();
			if( override != true && this.contents != this._original )
				status_button(this.path+" has been modified!", LEVEL_WARN, "Go to Revision "+$("history").value+" Anyway", bind(this._change_revision, this, true));
			else {
				this.rev = $("history").value;
				status_msg("Opening history .."+$("history").value, LEVEL_OK);
				this._load_contents();
				break;
			}
		}
	}

	this._receive_revisions = function(nodes) {
		if(nodes.history.length == 0) {
			replaceChildNodes("history", OPTION({'value' : -1}, "No File History!"));
		} else {
			replaceChildNodes("history", OPTION({'value' : -1}, "Select File Revision"));
			for(var i=0; i < nodes.history.length; i++)
				appendChildNodes("history", OPTION( {'value' : nodes.history[i].rev,
								     'title' : nodes.history[i].message },
								    "r" + nodes.history[i].rev + " " + nodes.history[i].date + " [" +nodes.history[i].author + "]"));

			appendChildNodes("history", OPTION({'value' : -2}, "--View Full History--"));
		}
	}

	this._error_receive_revisions = function() {
		status_msg("Couldn't retrieve file history", LEVEL_ERROR);
	}

	this._get_revisions = function() {
		logDebug("retrieving file history");
		var d = loadJSONDoc("./gethistory", { team : team,
						      file : this.path,
						      user : null,
						      offset : 0});
		d.addCallback( bind(this._receive_revisions, this));
		d.addErrback( bind(this._error_receive_revisions, this));
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
	//  - show() -- won't break horribly if the editarea hasn't finished loading (load safe)
	//  - hide() -- load safe hide()
	//  - getSelectionRange() -- get the cursor selection range, no need to pass the id, load safe
	//  - setSelectionRange() -- set the cursor selection range, no need to pass the id, load safe

	// Public properties:
	this.loaded = false;

	this._id = id;
	this._init_started = false;
	this._visible = false;
	this._open_queue = [];
	this._close_queue = [];
	this._value = null;

	// Start initialising the editarea
	this._init_start = function() {
		//initialize new instance of editArea
		editAreaLoader.init({
			id : this._id,
			syntax : "python",
			language : "en",
			start_highlight : true,
			allow_toggle : false,
			allow_resize : "no",
			display : 'onload',
			replace_tab_by_spaces : 4,
			plugins: "SRkeydown",
			show_line_colors: true,
			keyup_callback: "ea_keyup",
			EA_load_callback: "ea_loaded"
 		});

		connect( window, "ea_init_done",
			 bind( this._on_init_done, this ) );

		this._init_started = true;
	}

	this._on_init_done = function() {
		logDebug( "ide_editarea: editarea has finished loading" );
		this.loaded = true;

		if( this._visible )
			this.show();
		else
			this.hide();

		this._proc_open_queue();
		this._proc_close_queue();
		//focus to the top of the file
		this.setSelectionRange(0,0);

		signal( this, "onload" );
	}

	this._proc_open_queue = function() {
		// Open the files that we've been waiting to load
		logDebug( "iea funnelling " + this._open_queue.length + " queued things into editarea" );
		for( var i in this._open_queue ) {
			logDebug( this._open_queue[i].id + " -- " + this._open_queue[i].text  );
			editAreaLoader.openFile( this._id,
						 this._open_queue[i] );
		}
		this._open_queue = [];
	}

	this.show = function() {
		logDebug( "iea show" );
		this._visible = true;

		if( this.loaded ) {
			editAreaLoader.show( this._id );
			this._proc_open_queue();
			this._proc_close_queue();
			this._set_queued_value();
		}
		else if( !this._init_started )
			this._init_start();
	}

	this.hide = function() {
		this._visible = false;

		if( this.loaded )
			editAreaLoader.hide( this._id );
	}

	this.getSelectionRange = function() {
		if( this.loaded ) {
			log('editArea: getting selection');
			return editAreaLoader.getSelectionRange( this._id );
		} else
			return {start : 0, end : 0};
	}

	this.setSelectionRange = function(start, end) {
		if( this.loaded ) {
			editAreaLoader.setSelectionRange( this._id, start, end);
			logDebug('editArea: setting selection - start:'+start+':end:'+end+':');
		}
	}

	this.openFile = function( inf ) {
		if( !this.loaded || !this._visible )
			this._open_queue.push( inf );
		else
			editAreaLoader.openFile( this._id, inf );
	}

	this._proc_close_queue = function() {
		for( var i in this._close_queue )
			editAreaLoader.closeFile( this._id,
						  this._close_queue[i] );
		this._close_queue = [];
	}

	this.closeFile = function( id ) {
		if( !this.loaded )
			this._close_queue.push( id );
		else
			editAreaLoader.closeFile( this._id, id );
	}

	this.setValue = function( contents ) {
		if( !this.loaded )
			this._value = contents;
		else
			editAreaLoader.setValue( this._id, contents );
	}

	this._set_queued_value = function() {
		if( this._value != null )
			editAreaLoader.setValue( this._id, this._value );

		this._value = null;
	}

	this.getValue = function() {
		return editAreaLoader.getValue( this._id );
	}
}

// Called when the editarea has finished loading
function ea_loaded() {
	ea_has_loaded = true;

	// Rebroadcast the signal
	signal(window, "ea_init_done");
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
