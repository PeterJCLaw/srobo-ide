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

		this.textbox = getElement('editpage-acebox');
		connect( window, 'onresize', bind(this._window_resize, this) );

		this._iea = new ide_editarea('editpage-acebox');
	};

	// Resize the edit box to cope.
	this._window_resize = function() {
		// only if there's an edit box shown
		var dispStyle = getStyle('edit-mode', 'display');
		if (dispStyle == 'none') {
			return;
		}

		// prevElem should be the menu bar
		var prevElem = getElement('editpage-menu-bar');

		var dims = getElementDimensions(prevElem);
		var pos = getElementPosition(prevElem);
		var marginBottom = getStyle(prevElem, 'margin-bottom');
		marginBottom = parseInt(marginBottom);

		var aboveHeight = pos.y + dims.h + marginBottom;
		setStyle(this.textbox, {'top': aboveHeight + 'px'});
	};

	// Show the edit page
	this._show = function() {
		showElement('edit-mode');
		this._window_resize();
	};

	// Hide the edit page
	this._hide = function() {
		hideElement('edit-mode');
	};

	// Mark the given errors in the named file
	this.mark_errors = function( file, errors ) {
		if (!this.is_open(file)) {
			return;
		}

		var etab = this._file_get_etab(file);
		etab.mark_errors(errors);
	};

	// Clear all given errors from the named file
	this.clear_errors = function( file ) {
		if (!this.is_open(file)) {
			return;
		}

		var etab = this._file_get_etab(file);
		etab.clear_errors();
	};

	//Is the given file open?
	this.is_open = function( file ) {
		return this._open_files[file] != null;
	};

	// Get a list of files in the given project which are open and have modifications
	this.get_modified_files = function( project ) {
		var modified_files = [];
		for (var filename in this._open_files) {
			var etab = this._open_files[filename];
			if (etab.project == project && etab.is_modified()) {
				modified_files.push(filename);
			}
		}
		return modified_files;
	};

	// Focus on any of the given array of files, if one of them is already
	// focussed then do nothing.
	// Returns: whether or not a suitable file was focussed.
	this.focus_any = function(files) {
		var live_tabs = [];
		for (var i=0; i<files.length; i++) {
			var etab = this._open_files[files[i]];
			if (etab) {
				if (etab.tab.has_focus()) {
					return true;
				} else {
					live_tabs.push(etab);
				}
			}
		}
		// none were the current file -- focus on the first in the list
		if (live_tabs.length > 0) {
			tabbar.switch_to( live_tabs[0].tab );
			return true;
		} else {
			return false;
		}
	};

	// Open the given file and switch to the tab
	// or if the file is already open, just switch to the tab
	this.edit_file = function( team, project, path, rev, mode ) {
		// TODO: We don't support files of the same path being open in
		// different teams at the moment.
		var etab = this._file_get_etab( path );
		var newTab = false;

		if( etab == null ) {
			var readOnly = projpage.project_readonly(project);
			etab = this._new_etab( team, project, path, rev, readOnly, mode );
			newTab = true;
		}

		tabbar.switch_to( etab.tab );

		// If they've specified a revision then change to it
		// NB: we need to do this *after* switching to the tab so that it's shown, otherwise editarea explodes
		if ( !newTab && rev != null ) {
			etab.open_revision(rev, false);
		}

		RecordAccess("function:edit_file(...," + mode + ")");

		return etab;
	};

	// Create a new tab with a new file open in it
	this.new_file = function() {
		if (!validate_team()) {
			return;
		}
		if (!projpage.projects_exist()) {
			status_msg("You must create a project before creating a file", LEVEL_ERROR);
			return;
		}
		this._new_count ++;
		var fname = "New File " + this._new_count;
		var etab = this._new_etab( team, null, fname, 0, false );
		tabbar.switch_to( etab.tab );
	};

	this.rename_tab = function(old, New) {
		this._open_files[New] = this._open_files[old];
		this._open_files[old] = null;
		this._open_files[New].tab.set_label( New );
	};

	//close a tab, if it's open, return true if it's closed, false otherwise
	this.close_tab = function(name, override) {
		if (this.is_open(name)) {
			return this._open_files[name].close(override);
		} else {
			return true;
		}
	};

	this.close_all_tabs = function(override) {
		mod_count = 0;
		for (var i in this._open_files) {	//find which are modified and close the others
			if (this._open_files[i] !== null) {
				logDebug('checking '+i);
				if (this._open_files[i].is_modified() && override != true) {
					logDebug(i+' is modified, logging');
					mod_count += 1;
					mod_file = i;
				} else {
					logDebug('closing '+i);
					this._open_files[i].close(override);
				}
			}
		}
		if (mod_count > 0) {
			if (mod_count == 1) {
				this._open_files[mod_file].close(false);
			} else {
				status_button(mod_count+' files have been modified!', LEVEL_WARN, 'Close Anyway', bind(this.close_all_tabs, this, true));
			}
			return false;
		} else {
			return true;
		}
	};

	// Create a new tab that's one of ours
	// Doesn't load the tab
	this._new_etab = function(team, project, path, rev, isReadOnly, mode) {
		var etab = new EditTab(this._iea, team, project, path, rev, isReadOnly, mode);

		connect( etab, "onclose", bind(this._on_tab_close, this) );

		this._open_files[path] = etab;
		return etab;
	};

	// Return the tab for the given file path
	// returns null if not open
	this._file_get_etab = function(path) {
		for (var i in this._open_files) {
			if (i == path && this._open_files[i] !== null) {
				return this._open_files[i];
			}
		}
		return null;
	};

	// Handler for when the tab has been closed
	this._on_tab_close = function(etab) {
		// Remove tab from our list
		for (var i in this._open_files) {
			if (this._open_files[i] === etab) {
				this._open_files[i] = null;
				break;
			}
		}
	};

	this._tab_switch = function(fromtab, totab) {
		if (!this._is_edit(totab)) {
			this._hide();
			return;
		}

		if (!this._is_edit(fromtab)) {
			this._show();
		}
	};

	// Return true if the given tab is an edit tab
	this._is_edit = function(tab) {
		if (tab !== null && tab !== undefined && tab.__edit === true) {
			return true;
		}
		return false;
	};

	this._init();
}
