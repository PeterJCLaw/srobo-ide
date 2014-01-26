// vim: noexpandtab

// The project page
function ProjPage(team_selector) {
	// Whether _init has run
	this._initted = false;

	// The signals that we've set up for this object
	// signals that are for DOM objects that weren't created in this instance
	this._connections = [];

	// The project list -- a ProjList instance
	this._list = null;
	// The project selector -- a ProjSelect instance
	this._selector = null;
	// The calendar
	this._calendar = null;

	this._iframe = null;

	this._poll = null;

	// The selection operations
	this.selection_operations = null;

	//  The Projects tab
	this._tab = null;

	this.flist = null;
	this.project = "";

	// records the current revision of the project we're looking at
	this._projectRev = "";

	this.last_updated	= new Date();

	this._read_only = false;

	// listen for changes in the selected team
	connect( team_selector, "onchange", bind(this.set_team, this) );

	// Member functions (declared below)
	// Public:
	//  - show: Show and activate the projects page
	//  - hide: Hide the project page
	//  - hide_filelist: Hide the file list
	//  - projects_exist: check that one, or more, projects exist
	// Private:
	//  - _init: Initialises members of the project page
	//  - _rpane_show: Show the right-hand pane
	//  - _rpane_hide: Hide the right-hand pane
	//  - _on_proj_change: Handler for when the selected project changes
	//                     Hides/shows the right-hand pane as necessary
}

// Initialise the project page -- but don't show it
ProjPage.prototype._init = function() {
	if( this._initted )
		return;

	// Hide the right-hand whilst we're loading
	this._rpane_hide();

	// The list of projects
	this._list = new ProjList();

	// The selection box for selecting a project
	this._selector = new ProjSelect(this._list, getElement("project-select"));
	connect( this._selector, "onchange", bind( this._on_proj_change, this ) );

	// Selection operations
	this.selection_operations = new ProjOps();

	this.flist = new ProjFileList();
	// Update the file list when the project changes
	connect( this._selector, "onchange", bind( this.flist.update, this.flist ) );

	this._calendar = new Calendar();
	// Refresh the calendar when the project changes
	connect(this._selector, "onchange", bind( this._calendar.change_proj, this._calendar ) );

	var searchpage = SearchPage.GetInstance();
	var proj_searcher = new ProjectNameSearchProvider(this, this._selector);
	searchpage.add_provider(proj_searcher);

	var file_searcher = new FileNameSearchProvider(this, this._selector);
	searchpage.add_provider(file_searcher);

	// Connect up the project management buttons
	connect("new-project",		'onclick', bind(this.clickNewProject, this));
//	Archive doesn't do anything yet!
//	connect("archive-project",	'onclick', bind(this.clickArchiveProject, this));
	connect("copy-project",		'onclick', bind(this.clickCopyProject, this));
	connect("check-code",		'onclick', bind(this.clickCheckCode, this));
	connect("export-project",	'onclick', bind(this.clickExportProject, this));

	// We have to synthesize the first "onchange" event from the ProjSelect,
	// as these things weren't connected to it when it happened
	this._on_proj_change( this._selector.project );
	this.flist.update( this._selector.project, this._selector._team );

	this._createTab();

	this._setupPolling();

	this._initted = true;

	// This triggers the tab to be shown, our hanlder for which (correctly)
	// verifies that we're inited, so we need to do this after setting the flag.
	tabbar.switch_to( this._tab );
}

ProjPage.prototype._createTab = function() {
	// Projects tab
	this._tab = new Tab( "Projects", { can_close: false } );
	connect( this._tab, "onfocus", bind( this.show, this ) );
	connect( this._tab, "onblur", bind( this.hide, this ) );
	tabbar.add_tab( this._tab );
}

ProjPage.prototype.has_focus = function() {
	return this._tab != null && this._tab.has_focus();
}

ProjPage.prototype.switch_to = function() {
	this._init();
	// only actually needed if we're not inited, but it's simpler to just call both
	tabbar.switch_to(this._tab);
}

ProjPage.prototype._setupPolling = function() {
	if ( this._poll != null ) {
		this._poll.cancel();
	}
	// 30 second delay, retry once.
	this._poll = new Poll('poll/poll', { team: team }, 30, 1);
	connect(this._poll, 'onchange', bind(this._pollChanged, this));
}

ProjPage.prototype._pollChanged = function(nodes) {
	logDebug('projpage: Poll changed');

	var projects = keys(nodes.projects);
	if (projects.length != this._list.projects.length)
	{
		// TODO: don't assume that this is a good enough check.
		this._list.projects = projects;
		signal(this._list, 'onchange', team);
	}

	if ( IDE_string_empty(this.project) ) {
		return;
	}

	var nodesRev = nodes.projects[this.project];
	// The project we're looking at has been changed on the server
	if (nodesRev != this._projectRev) {
		this._projectRev = nodesRev;
		// Update the calendar
		this._calendar.getDates();
		this.flist.mark_dirty();
	}
}

ProjPage.prototype.show = function() {
	logDebug( "Projpage.show: Current project is \"" + this.project + "\"" );
	this._init();

	showElement('projects-page');
}

ProjPage.prototype.hide = function() {
	logDebug( "Hiding the projects page" );
	hideElement('projects-page');
}

ProjPage.prototype.hide_filelist = function() {
	logDebug( "Hiding the file list" );
	this.flist._hide();
}

ProjPage.prototype.project_readonly = function(pname) {
	return this._read_only;
}

ProjPage.prototype.project_exists = function(pname) {
	return this._list != null && this._list.project_exists(pname);
}

ProjPage.prototype.projects_exist = function() {
	if (this.list_projects().length > 0)
		return true;
	else
		return false;
}

ProjPage.prototype.list_projects = function() {
	if (this._list == null || this._list.projects == null) {
		return [];
	}
	return this._list.projects;
}

ProjPage.prototype._got_proj_info = function(nodes) {
	getElement('proj-info').innerHTML = nodes.repoUrl;
}

ProjPage.prototype._on_proj_change = function(proj, team) {
	logDebug( "ProjPage._on_proj_change(\"" + proj + "\", " + team + ")" );
	this.project = proj;

	if( proj == "" )
		this._rpane_hide();
	else {
		IDE_backend_request('proj/info',
		                    { team: team, project: proj },
		                    bind(this._got_proj_info, this),
		                    function() {}
		                   );
		getElement('proj-info').innerHTML = '';
		getElement("proj-name").innerHTML = "Project " + this.project;
		this._rpane_show();
	}
}

ProjPage.prototype.set_team = function(team) {
	logDebug( "ProjPage.set_team( " + team + " )" );
	if( team == null || team == 0 )
		return;

	this._rpane_hide();
	this._init();
	// reset polling for the new team
	this._setupPolling();

	// Start the chain of updates
 	this._list.update(team);
	// The selector and filelist are connected to onchange on the list,
	// so they will update when it's updated

	var teamInfo = user.get_team(team);
	this._set_readonly(teamInfo.readOnly == true);
}

ProjPage.prototype._set_readonly = function(isReadOnly) {
	if (this._read_only != isReadOnly) {
		getElement('new-project').disabled = isReadOnly;
		getElement('copy-project').disabled = isReadOnly;
		this.selection_operations.set_readonly(isReadOnly);
	}
	this._read_only = isReadOnly;
	setReadOnly('projects-page', isReadOnly);
}

// ***** Project Page Right Hand pane *****
ProjPage.prototype._rpane_hide = function() {
	hideElement('proj-rpane');
}

ProjPage.prototype._rpane_show = function() {
	showElement('proj-rpane');
}

ProjPage.prototype.clickArchiveProject = function() {
	return;
}

ProjPage.prototype.clickCopyProject = function() {
	if( this.project == null || this.project == "" )
		status_msg( "Please select a project to copy", LEVEL_INFO );
	else
		var b = new Browser(bind(this.CreateCopyProject, this), {'type' : 'isProj', 'title' : 'Copy Project'});
}

ProjPage.prototype.CreateCopyProject = function(newProjName) {
	cMsg = 'Copying project '+this.project+' to '+newProjName;
	log(cMsg);

    IDE_backend_request("proj/copy",
                        {
                            "team":team,
                            "project":this.project,
                            "new-name":newProjName
                        },
                        bind( partial(this._CopyProjectSuccess, newProjName), this),
	                    bind( function() {
                		    status_button( "Copy Project: Error contacting server", LEVEL_ERROR, "retry",
                			bind(this.CreateCopyProject, this, newProjName) );
                    	}, this ) );


}

ProjPage.prototype._CopyProjectSuccess = function(newProjName, nodes) {
	if(nodes.status > 0)
		status_msg("ERROR COPYING: "+nodes.message, LEVEL_ERROR);
	else {
		status_msg("Project copy successful", LEVEL_OK);
		if (this.has_focus()) {
			log('Project Copied, need to update the list...');
			// Transition to the new project once the project list has loaded
			this._selector.trans_project = newProjName;
			this._list.update(team);
		}
	}
}

ProjPage.prototype.clickNewProject = function() {
	var b = new Browser(bind(this.CreateNewProject, this), {'type' : 'isProj'});
}

ProjPage.prototype.CreateNewProject = function(newProjName) {
	/* Postback to create a new project - then what? */


	IDE_backend_request("proj/new", {team: team, project: newProjName},
		bind(this._createProjectSuccess, this, newProjName),
		bind(this._createProjectFailure, this, newProjName)
	);
}

ProjPage.prototype._createProjectSuccess = function(newProjName) {
	status_msg("Created project successfully", LEVEL_OK);
	update(team)
	// Transition to the new project once the project list has loaded
	this._selector.trans_project = newProjName;
	this._list.update(team);
}

ProjPage.prototype._createProjectFailure = function(newProjName) {
	/* XXX - check for preexisting projects perhaps */
	status_button('Error creating project', LEVEL_ERROR, 'retry',
		bind(this.CreateNewProject, this, newProjName)
	);
}

ProjPage.prototype.clickExportProject = function() {
	var tmpItem = getElement('projlist-tmpitem');
	if( tmpItem != null && tmpItem.selected == true ) {
		status_msg( "No project selected, please select a project", LEVEL_ERROR );
		return;
	}

	if( !this.flist.robot ) {	//if there's no robot.py script then it's going to fail
		status_msg( "A robot.py file is required for project code export", LEVEL_ERROR );
		return false;
	}

	var options = {'switch_to':true, 'alert':false, 'quietpass':true, 'callback':bind(this._exportProjectCheckResult, this)};
	errorspage.check("/"+this.project+"/robot.py", options, false, this.flist.rev);
}

ProjPage.prototype._exportProjectCheckResult = function(result, num_errors) {
	if(result == 'pass') {
		this._exportProject();
		return;
	}

	var exportAnyway = {text:"export anyway", callback:bind( this._exportProject, this )};

	// check has failed
	if(result == 'checkfail') {
		var message = "Failed to check code";
		status_options( message, LEVEL_WARN,
				[{text:"retry", callback:bind( this.clickExportProject, this )},
				 exportAnyway]
		);
	}

	// user's code has failed
	if(result == 'codefail') {
		var message = num_errors + " errors found!";
		status_options( message, LEVEL_WARN, [exportAnyway]);
	}

	log('_exportProjectCheckResult:'+message);
}

ProjPage.prototype._exportProject = function() {
	var fail_retry = bind(function() {
		status_button('Error exporting project', LEVEL_ERROR, 'retry',
			bind(this._exportProject, this)
		);
	}, this);
	Checkout.GetInstance().checkout(team, this.project, this.flist.rev, function() {}, fail_retry);
}

ProjPage.prototype.clickCheckCode = function() {
	if( this.project == null || this.project == "" ) {	//if there's no project selected what's the point
		status_msg( "Please select a project for code checking", LEVEL_INFO );
		return false;
	}

	if( !this.flist.robot ) {	//if there's no robot.py script then it's going to fail
		status_msg( "A robot.py file is required for project code checking", LEVEL_ERROR );
		return false;
	}

	errorspage.check("/"+this.project+"/robot.py", {'switch_to':true}, false, this.flist.rev);
}
