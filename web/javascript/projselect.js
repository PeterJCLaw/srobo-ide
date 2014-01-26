// vim: noexpandtab

// The project selector.
// Arguments:
//  - plist: The project list (ProjList)
//  - elem: The DOM node for the select box.
function ProjSelect(plist, elem) {
	this._elem = elem;
	this._jqElem = jQuery(elem);
	this._plist = plist;

	// The project that's selected
	// Empty string means none selected
	this.project = "";
	// Project to transition to when the projlist changes
	this.trans_project = "";

	// The 'Select a project' option
	this._tmp_select = null;

	// The team that we're currently listing
	this._team = null;

	// The 'please select a project' prompt
	this._prompt = null;

	// The project list sorter
	this._usage_sorter = new UsageSorter([], function(){});

	// Signals:
	//  - onchange: when the project selection changes.
	//              Handler passed the name of the new project.

	// Member functions:
	// Public:
	// - select(project): Selects the given project, if it exists.

	// Private:
	//  - _init: Initialisation.
	//  - _onchange: Handler for the select box onchange event.
	//  - _plist_onchange: Handler for when the list of projects changes.

	this._init();
}

ProjSelect.prototype._init = function() {
	this._jqElem.chosen({ width: '190px' }).change( bind( this._onchange, this ) );
	connect( this._plist, "onchange", bind( this._plist_onchange, this ) );

	// If the list is already loaded when we're called, force update
	if( this._plist.loaded )
		this._plist_onchange();
}

ProjSelect.prototype._init_sorter = function(team) {
	var recent = user.get_raw_setting('project.recent');
	var team_list = []
	if (recent != null && recent[team] != null) {
		team_list = recent[team];
	}
	this._usage_sorter = new UsageSorter(team_list , function(list) {
			var recent = user.get_raw_setting('project.recent');
			if (recent == null) {
				recent = {};
			}
			recent[team] = list;
			user.set_settings({'project.last':list[list.length - 1], 'project.recent':recent});
		});
}

// Called when the project list changes
ProjSelect.prototype._plist_onchange = function(team) {
	logDebug( "ProjSelect._plist_onchange" );
	var startproj = this.project;
	var startteam = this._team;
	var items = [];
	this._tmp_select = null;

	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}

	var projects = this._plist.projects;

	// optionally sort them by usage, rather the default alphabetical
	if (user.get_setting('project.list-sort') == 'usage') {
		this._init_sorter(team);
		projects = this._usage_sorter.sort(projects);
	}

	// Find the project to select
	if( this.trans_project != ""
	    && this._plist.project_exists( this.trans_project ) ) {
		this.project = this.trans_project;

		// Clear the transition default
		this.trans_project = "";

	} else if( this.project == ""
	    || !this._plist.project_exists( this.project )
	    || team != this._team ) {
		this.project = "";

		var dp = this._get_default();
		if( dp == null ) {
			// Add "Please select..."
			this._prompt = status_msg( "Please select a project", LEVEL_INFO );
			var opts = { value: -1, selected: "selected" };
			this._tmp_select = OPTION(opts, "Select a project.");
			items.unshift(this._tmp_select);
		} else
			this.project = dp;
	}
	this._team = team;

	// Rebuild the select box options
	for( var i=0; i < projects.length; i++ ) {
		var pname = projects[i];
		var props = { "value" : pname };

		if( pname == this.project )
			props["selected"] = "selected";
		items[items.length] = ( OPTION( props, pname ) );
	}

	replaceChildNodes( this._elem, items );
	this._jqElem.trigger('chosen:updated');

	logDebug( "ProjList._plist_onchange: Now on project " + this._team + "." + this.project );

	if( startproj != this.project
	    || startteam != this._team )
		signal( this, "onchange", this.project, this._team );
}

// Handler for the onchange event of the select element
ProjSelect.prototype._onchange = function(ev) {
	//hide the status bar - anything there is now obselete
	status_hide();

	this.select(this._elem.value);
}

/**
 * Select the given project.
 * This is expected to be used when triggering from a user action that
 * isn't necessarily on the project page, but also provides separation
 * between the onchange handler and its actions.
 * @param project: The name of the project to switch to.
 */
ProjSelect.prototype.select = function(project) {
	if (!this._plist.project_exists(project)) {
		return;
	}

	if (this._tmp_select != null) {
		// Don't allow switching _to_ the 'Please select' option.
		if (project == this._tmp_select.value) {
			return;
		}
		removeElement(this._tmp_select);
		this._jqElem.trigger('chosen:updated');
		this._tmp_select = null;
	}

	if (project != this.project) {
		this.project = project;
		this._elem.value = project;
		signal(this, "onchange", project, this._team);
		// record the selection
		this._usage_sorter.notify_use(project);
		// do this after signalling the usage sorter, since that also
		// records this we thus avoid a duplicate http request.
		user.set_settings({'project.last':project});
	}
}

ProjSelect.prototype._get_default = function() {
	if( this._plist.projects.length == 1 )
		return this._plist.projects[0];

	var p_autoload = user.get_setting( 'project.autoload' );
	var dp = user.get_setting( p_autoload );

	if( dp != undefined
	    && this._plist.project_exists( dp ) )
		return dp;

	return null;
}
