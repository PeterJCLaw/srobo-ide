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
	Checkout.GetInstance().checkout(team, this.project, this.flist.rev, function() {}, function(errno, errcode) {
		alert("checkout-induced death: " + errcode);
	});
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

// ***** Project Page File Listing *****
function ProjFileList() {
	this._project = "";
	this._team = null;

	//allow for an auto refresh
	this._timeout = null;
	//how often to retry an auto update -- this only occurs if an update failed, in seconds
	this._refresh_delay = 3;
	//when was it 'born', milliseconds since epoch
	this._birth = new Date().valueOf();

	//prompt when it errors during an update
	this._err_prompt = null;

	// the project revision we're displaying
	// can be integer or "HEAD"
	this.rev = "HEAD";

	// whether or not there's a robot.py file
	this.robot = false;

	// The files/folders that are currently selected
	this.selection = [];
	// Files/folders that have tried to be selected while we were updating the list
	this._deferred_selection = [];

	// Member functions:
	// Public:
	//  - change_rev: change the file list revision, uses current project and team info to update
	//  - update: Update the file list to the given project and team
	// Private:
	//  - _received: handler for receiving the file list
	//  - _nested_divs: Returns N nested divs.
	//  - _dir: Returns the DOM object for a directory entry
	//  - _onclick: The onclick handler for a line in the listing
	//  - _hide: Hide the filelist
	//  - _show: Show the filelist
}

/// Tell the file list that it's out of date.
ProjFileList.prototype.mark_dirty = function() {
	this._auto_refresh();
}

ProjFileList.prototype.change_rev = function(revision) {
	this.update(this._project, this._team, revision);
}

// Request and update the project file listing
ProjFileList.prototype.update = function( pname, team, rev ) {
	logDebug( "ProjFileList.update( \"" + pname + "\", " + team + ", "+rev+" )" );
	if( pname == "" ) {
		// No project selected.
		this._hide();
		return;
	}

	var curr_rev = this.rev;

	if(rev == undefined || rev == null) {
		this.rev = "HEAD";
	} else {
		this.rev = rev;
	}

	if( pname != this._project || team != this._team || rev != curr_rev ) {
		// Hide the list whilst we're loading it
		swapDOM( "proj-filelist",
			 DIV( {"id": "proj-filelist",
			       "class" : "loading"},
			      "Loading project file listing..." ) );
	}
	if (pname != this._project || team != this._team) {
		this.select_none();
	}

	this._project = pname;
	this._team = team;
	this.refresh();
}

ProjFileList.prototype._prepare_auto_refresh = function() {
	log('Preparing an automatic file list refresh');
	if( this._timeout != null )
		this._timeout.cancel();

	if( this.rev != "HEAD" && this.rev != 0 && this.rev != null )	//not showing HEAD
		return;

	this._timeout = callLater(this._refresh_delay, bind(this._auto_refresh, this));
}

ProjFileList.prototype._auto_refresh = function() {
	//do we want to run a refresh?
	if( this.rev != "HEAD" && this.rev != 0 && this.rev != null )	//not showing HEAD
		return;

	// this will bail if there's no project selected,
	// but that's not something we need to worry about,
	// because we'll get setup again if the user selects a project.
	this.refresh(true);
}

ProjFileList.prototype.refresh = function(auto) {
	log('Doing a file list refresh');
	if( IDE_string_empty(this._project) )
		return 'no_proj';

	//kill the error message, if it exists
	if( this._err_prompt != null ) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this._timeout = null;
	var err_handler;
	if(!auto)	//if it's an automatic call don't interrupt the user - just setup another
		err_handler = bind( function (){
			this._err_prompt = status_button( "Error retrieving the project file listing", LEVEL_ERROR,
					   "retry", bind( this.refresh, this ) );
		}, this );
	else
		err_handler = bind( this._prepare_auto_refresh, this );
	IDE_backend_request("file/compat-tree", {team:    this._team,
	                                         project: this._project,
	                                         rev:     this.rev,
	                                         path:    "."},
	                                         bind(this._received, this), err_handler);
}

ProjFileList.prototype._hide = function() {
	hideElement('proj-filelist');
}

ProjFileList.prototype._show = function() {
	showElement('proj-filelist');
}

//compare filelist items for use in sorting it
function flist_cmp(a,b) {
	if(a.name.toLowerCase() > b.name.toLowerCase())
		return 1;
	return -1;
}

// Handler for receiving the file list
ProjFileList.prototype._received = function(nodes) {
	this._deferred_selection = this.selection;
	this.selection = new Array();
	this._birth = new Date().valueOf();
	log( "filelist received" );
	this.robot = false;	//test for robot.py: reset before a new filelist is loaded

	swapDOM( "proj-filelist",
		 UL( { "id" : "proj-filelist",
		       "style" : "display:none" },
		     map( bind(this._dir, this, 0), nodes.tree.sort(flist_cmp) ) ) );

	this._deferred_selection = [];

	this._show();
}

// Produce an object consisted of "level" levels of nested divs
// the final div contains the DOM object inner
ProjFileList.prototype._nested_divs = function( level, inner ) {
	if (level == 0)
		return inner;

	if (level > 1)
		return DIV( null, this._nested_divs( level-1, inner ) );

	return DIV( null, inner );
}

// Returns a DOM object for the given node
ProjFileList.prototype._dir = function( level, node ) {
	// Assemble the link with divs in it
	var attrs = { href: '#', ide_path: node.path, ide_kind: node.kind };
	var is_file = node.kind == "FILE";
	var children = this._nested_divs(level, node.name + (is_file ? '' : '/'));
	var link = A(attrs, children);
	connect( link, "onclick", bind(this._onclick, this) );

	var node_li = LI(attrs, link);
	if (is_file) {
		// Assemble links to available autosave, if there is one
		var autosave_link = this._autosave_link( node, level );
		if (level == 0 && node.path.endsWith('/robot.py'))
			this.robot = true;
		appendChildNodes(node_li, autosave_link);
	} else {
		var children = map(bind(this._dir, this, level + 1), node.children.sort(flist_cmp));
		appendChildNodes(node_li, UL({ "class" : "flist-l" }, children));
	}

	// Should it be selected?
	if (findValue(this._deferred_selection, node.path) != -1) {
		this._select_path(node.path, node_li);
	}

	return node_li;
}

// Returns a DOM link for the given node's autosave, if it exists
ProjFileList.prototype._autosave_link = function( node, level ) {
	if( node.kind != "FILE" || node.autosave == 0 )
		return null;

	// Assemble the link with divs in it
	var link = A( { "href" : "#",
				"class" : 'autosave',
				"ide_path" : node.path,
				"ide_kind" : 'AUTOSAVE' },
				'Load autosave ('+(new Date(node.autosave * 1000)).toDateString()+')' );
	connect( link, "onclick", bind( this._onclick, this ) );
	return link;
}

// The onclick event for the filelist items
ProjFileList.prototype._onclick = function(ev) {
	// Prevent the browser doing something when someone clicks on this
	ev.preventDefault();
	ev.stopPropagation();

	var mods = ev.modifier();

	var src = ev.src();
	var kind = getNodeAttribute( src, "ide_kind" );
	var path = getNodeAttribute( src, "ide_path" );

	if( mods["ctrl"] || mods["meta"] ) {	//meta is the mac command button, which they use like Win/Linux uses ctrl
		if( !this._is_file_selected(path) )
			this._select_path( path, src.parentNode );
		else
			this._deselect_path( path, src.parentNode );

	} else {
		if( kind == "FOLDER" ) {
			this._toggle_dir( src );
		} else if( kind == 'AUTOSAVE' ) {
			editpage.edit_file( this._team, this._project, path, this.rev, 'AUTOSAVE' );
			//do something special
		} else {
			editpage.edit_file( this._team, this._project, path, this.rev, 'REPO' );
		}
	}
}

ProjFileList.prototype.select_all = function() {
	var files = getElement('proj-filelist').getElementsByTagName('li');
	for(var i=0; i<files.length; i++) {
		this._select_path(getNodeAttribute(files[i].firstChild, "ide_path"), files[i]);
	}
}

ProjFileList.prototype.select = function(path) {
	var root = getElement('proj-filelist');
	if (hasElementClass(root, 'loading')) {
		this.selection.push(path);
	}
	var allNodes = root.getElementsByTagName('li');
	for(var i=0; i < allNodes.length; i++) {
		var node = allNodes[i];
		if (getNodeAttribute(node.firstChild, 'ide_path') == path) {
			this._select_path(path, node);
			break;
		}
	}
}

ProjFileList.prototype.select_none = function() {
	this.selection = [];
	var selected = getElementsByTagAndClassName('li', "selected", getElement('proj-filelist'));
	for(var i=0; i<selected.length; i++)
		removeElementClass( selected[i], "selected" );
}

ProjFileList.prototype._is_file_selected = function( path ) {
	for( var i=0; i < this.selection.length; i++ )
		if( this.selection[i] == path )
			return true;
	return false;
}

ProjFileList.prototype._select_path = function(path, node) {
	addElementClass( node, "selected" );
	this.selection.push( path );
}

ProjFileList.prototype._deselect_path = function(path, node) {
	removeElementClass( node, "selected" );
	var i = findValue( this.selection, path );
	if( i >= 0 ) {
		// Remove from the listn
		this.selection.splice( i, 1 );
	}
}

// Toggles the display of the contents of a directory
ProjFileList.prototype._toggle_dir = function(src) {
	// Get a handler on its children
	var dir_contents = getFirstElementByTagAndClassName( "UL", null, src.parentNode );

	display = "";
	if( getStyle( dir_contents, "display" ) != "none" ) {
		display = "none";

		var nc = this._ul_get_num_children( dir_contents );

		var c = " child";
		if( nc != 1 )
			c = c + "ren";

		var div = this._get_innerdiv( src );
		appendChildNodes( div,
				  SPAN({"class":"proj-filelist-dir-collapse"},
				       " [ " + nc + c + " hidden ]"));

	} else {
		removeElement( getFirstElementByTagAndClassName( "SPAN", null, src ) );
	}

	setStyle( dir_contents, {"display" : display} );
}

// Returns the innermost DIV within in given element
// Assumes that there's only one DIV per level
ProjFileList.prototype._get_innerdiv = function(elem) {
	var d = getFirstElementByTagAndClassName( "DIV", null, elem );
	if ( d == null )
		return elem;
	else
		return this._get_innerdiv( d );
}

ProjFileList.prototype._ul_get_num_children = function(ul) {
	var count = 0;
	for( var i=0; i < ul.childNodes.length; i++ ) {
		if( ul.childNodes[i].tagName == "LI" )
			count++;
	}
	return count;
}

// Object that grabs the project list
// Signals:
//  - onchange: when the projects list changes.
//              First argument is the team number
function ProjList() {
	// Array of project names (strings)
	this.projects = [];
	// Whether we've loaded
	this.loaded = false;
	// The team
	this._team = null;

	//prompt when it errors while grabing the list
	this._err_prompt = null;

	// Member functions:
	// Public:
	//  - project_exists: Returns true if the given project exists.
	//  - update: mask of _grab_list
	// Private:
	//  - _init
	//  - _grab_list: Grab the project list and update.
	//  - _got_list: Handler for the project list response.
}

// Update the list to the given team
ProjList.prototype.update = function(team) {
	this._grab_list(team);
}

ProjList.prototype._grab_list = function(team) {
	this._team = team;

	//kill the error message, if it exists
	if( this._err_prompt != null ) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this.loaded = false;

	IDE_backend_request("team/list-projects", {team: team}, bind(this._got_list, this),
	                    bind( function() {
			this._err_prompt = status_button( "Error retrieving the project list", LEVEL_ERROR,
			       "retry", bind( this._grab_list, this) );
	}, this ) );
}

ProjList.prototype._got_list = function(resp) {
 	this.projects = resp["team-projects"];
 	this.loaded = true;

	signal( this, "onchange", this._team );
}

ProjList.prototype.project_exists = function(pname) {
	logDebug('Checking project existence: '+pname+' in '+this.projects+' : '+(findValue(this.projects, pname) > -1) );
	return findValue(this.projects, pname) > -1;
}

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

//handles all 'selection operations' in sidebar of project page
function ProjOps() {

	//view_log()                    for each item selected in file list it will attempt to open a new log tab
	//receive_newfolder([])         ajax success handler
	//error_receive_newfolder()     ajax fail hanlder
	//newfolder()                   gets folder name & location and instigates new folder on server

	//list of operations
	this.ops = new Array();

	this._read_only = false;

	this.init = function() {
		//connect up operations
		for(var i=0; i < this.ops.length; i++) {
			var action = bind(this._handler, this, this.ops[i]);
			this.ops[i].event = connect(this.ops[i].handle, 'onclick', action);
		}
	}

	this.set_readonly = function(isReadOnly) {
		if (this._read_only != isReadOnly) {
			var setClass = isReadOnly ? addElementClass : removeElementClass;
			for (var i=0; i < this.ops.length; i++) {
				var op = this.ops[i];
				if (op.isWrite) {
					setClass(op.handle, 'disabled');
				}
			}
		}
		this._read_only = isReadOnly;
	}

	this._handler = function(operation, ev) {
		if (operation.isWrite && this._read_only) {
			kill_event(ev);
			return false;
		}
		operation.action();
	}

	this.view_log = function() {
		//for every file that is selected:
		if(projpage.flist.selection.length == 0) {
			status_msg("No file/folders selected", LEVEL_WARN);
			return;
		}
		for(var i = 0; i < projpage.flist.selection.length; i++) {
			//try to find log file in tabbar
			var exists = map(function(x) {
				if(x.label == "Log: "+projpage.flist.selection[i]) {
					return true;}
				else { return false; }
			}, tabbar.tabs);
			var test = findValue(exists, true);
			//if already present, flash it but don't open a new one
			if(test > -1) {
				tabbar.tabs[test].flash();
			} else { //not present, open it
				var cow = new Log(projpage.flist.selection[i], projpage.project);
			}
		}
	}

	this.receive_newfolder = function(nodes) {
		logDebug("Add new folder: ajax request successful");
		switch(nodes.success) {
			case 1:
				status_msg(nodes.feedback, LEVEL_OK);
				projpage.flist.refresh();
				break;
			case 0:
				status_msg(nodes.feedback, LEVEL_ERROR);
				break;
		}
	}

	this.error_receive_newfolder = function(new_name, new_message) {
		logDebug("Add new folder: ajax request failed");
		status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.new_folder, this, new_name, new_msg) );
	}

	this.new_folder = function(new_name, new_msg) {
		if(!projpage.projects_exist()) {
			status_msg("You must create a project before creating a folder", LEVEL_ERROR);
			return;
		}
		logDebug("Add new folder: "+new_name+" ...contacting server");
		if(new_name == null || new_name == undefined) {
			var browser = new Browser(bind(this.new_folder, this), {'type' : 'isDir'});
		} else {
			IDE_backend_request("file/mkdir",
                                { team : team,
						          path : IDE_path_get_file(new_name),
                                  project:IDE_path_get_project(new_name)
						        },
                                    bind(this.receive_newfolder,this),
                                    bind(this.error_receive_newfolder, new_name, new_msg,this)
                                );

		}
	}

	this._mv_success = function(nodes) {
		logDebug("_mv_success()");
		status_msg("Move successful!", LEVEL_OK);
		projpage.flist.refresh();
	}

	this._mv_cback = function(dest, cmsg) {
		var src = projpage.flist.selection[0];
		var type = null;

		//is it a file or a folder?
		if(src.indexOf(".") < 0) { type = 'isDir'; }
		else { type = 'isFile'; }

		//do we already have a move to location?
		logDebug("type "+type);
		if(dest == "" || dest == null) {
			logDebug("launch file browser to get move destination");
			var b = new Browser(bind(this._mv_cback, this), {'type' : 'isFile'});
			return;
		} else {
			//do some sanity checking
			switch(type) {
				case 'isFile' :
					if(dest.indexOf(".") < 0) {
						status_msg("Move destination file must have an extension", LEVEL_ERROR);
						return;
					}
					break;
				case 'isDir' :
					if(dest[dest.length-1] == "/") {
						dest = dest.slice(0, dest.length-2);
					}
					if(dest.indexOf(".") > 0) {
						status_msg("Move destination must be a folder", LEVEL_ERROR);
						return;
					}
					break;
			}
		}

		status_msg("About to do move..."+src+" to "+dest, LEVEL_OK);

		IDE_backend_request("file/mv", {
				 "project": IDE_path_get_project(src),
				    "team": team,
				 "message": cmsg,
				"old-path": IDE_path_get_file(src),
				"new-path": IDE_path_get_file(dest)
			},
            //on move success, do a commit
			bind( function() {IDE_backend_request("proj/commit",{
                    team:team,
                    project:IDE_path_get_project(src),
                    paths:[IDE_path_get_file(src),IDE_path_get_file(dest)],
                    message:cmsg
                  },
                  //bind commit success to _mv_success
                  bind(this._mv_success,this),
                  //begin binding commit failure to move failure callback
			      bind( function () {
				            status_button( "Error moving files/folders", LEVEL_ERROR, "retry",
			                bind( this._mv_cback, this, dest, cmsg ) );
			            }, this
                  )
                  //end binding commit failure to move failure callback
                 )},
               this),
            //end move success bind
            //on move failure, show an error
			bind( function () {
					status_button( "Error moving files/folders", LEVEL_ERROR, "retry",
					                bind( this._mv_cback, this, dest, cmsg )
                                 );
                  }, this
                )
		);
	}

	this.mv = function() {
		//we can only deal with one file/folder at a time, so ignore all but the first
		if(projpage.flist.selection.length == 0 || projpage.flist.selection.length > 1) {
			status_msg("You must select a single file/folder", LEVEL_ERROR);
			return;
		}

		//the file must be closed!
		if(!editpage.close_tab( projpage.flist.selection[0] )) {
			log('Cannot move open file: '+projpage.flist.selection[0]);
			return;
		}

		var b = new Browser(bind(this._mv_cback, this), {'type' : 'isFile'});
		return;

	}

	this._cp_callback1 = function() {
			status_msg("Successful Copy", LEVEL_OK);
			projpage.flist.refresh();
	}
	this._cp_callback2 = function(fname, cmsg) {
		logDebug("copying "+projpage.flist.selection[0]+" to "+fname);
		//logDebug("team is" + team + " project is " + project);

		if(fname == null || fname=="")
			return;

		IDE_backend_request("file/cp", {
				 "project": IDE_path_get_project(projpage.flist.selection[0]),
				    "team": team,
				 "message": cmsg,
				"old-path": IDE_path_get_file(projpage.flist.selection[0]),
				"new-path": IDE_path_get_file(fname)
			},
			bind(function() {
                    logDebug("in ide backend proj commit");
                    IDE_backend_request("proj/commit", {
                                                        team:team,
                                                        project:IDE_path_get_project(projpage.flist.selection[0]),
                                                        message:cmsg,
                                                        paths:[IDE_path_get_file(fname)]
                                                      },
                                            bind(
                                                this._cp_callback1
                                                ,this
                                            ),

                                            bind( function() {
                                                status_button("Error contacting server", LEVEL_ERROR, "retry",
                                                bind(this._cp_callback2, this, fname, cmsg));
                                                }, this
                                            )

                 )}, this
                ),
			bind( function() {
					status_button("Error contacting server", LEVEL_ERROR, "retry",
					bind(this._cp_callback2, this, fname, cmsg));
				  }, this
                )
		);
	}
	this.cp = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected to copy", LEVEL_ERROR);
			return;
		}
		if(projpage.flist.selection.length > 1) {
			status_msg("Multiple files selected!", LEVEL_ERROR);
			return;
		}
		var b = new Browser(bind(this._cp_callback2, this), {'type' : 'isFile'});
		return;
	}
	this.rm = function(override) {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for deletion", LEVEL_ERROR);
			return;
		}
		if(override == false) {
			status_button("Are you sure you want to delete "+projpage.flist.selection.length+" selected files/folders", LEVEL_WARN, "delete", bind(this.rm, this, true));
			return;
		}

		var death_list = new Array();
		var selection = projpage.flist.selection;
		var proj_path_len = projpage.project.length + 2;
		for( var i=0; i < selection.length; i++ ) {
			death_list.push(selection[i].substr(proj_path_len))
		};

		logDebug("will delete: "+death_list);

        IDE_backend_request("file/del",
                            { "team" : team,
        				      "project" : projpage.project,
		        			  "files" : death_list
                            },
                            bind(function() {
                                                IDE_backend_request("proj/commit",
                                                                    {
                                                                        team:team,
                                                                        project:projpage.project,
                                                                        paths:death_list,
                                                                        message:"File deletion"
                                                                    },
                                                                    bind(function(){
                                                                        status_msg("files deleted succesfully", LEVEL_OK);
			                                                            projpage.flist.refresh();
                                                                    },this),
                                                                    bind(function() {
                                                                        status_button("Error contacting server",
                                                        				LEVEL_ERROR, "retry", bind(this.rm, this, true));
                                                                                    }
                                                                    ,this)
                                                                   )
                                            },this),
                                bind(function() {
                                                  status_button("Error contacting server",
                                                  LEVEL_ERROR, "retry", bind(this.rm, this, true));
                                                },this)
                           );
	}

	this.rm_autosaves = function(override) {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for deletion", LEVEL_ERROR);
			return;
		}
		if(override == false) {
			status_button("Are you sure you want to delete "+projpage.flist.selection.length+" selected Autosaves",
						LEVEL_WARN, "delete", bind(this.rm_autosaves, this, true));
			return;
		}

		var death_list = new Array();
		var selection = projpage.flist.selection;
		var proj_path_len = projpage.project.length + 2;
		for( var i=0; i < selection.length; i++ ) {
			death_list.push(selection[i].substr(proj_path_len))
		};

		log("Will delete autosaves: "+death_list);

        IDE_backend_request("file/co",
                    { "team" : team,
				      "project" : projpage.project,
				      "files" : death_list,
                      "revision":0
                    },

		            bind(function(nodes) {
            			status_msg("Deleted Autosaves", LEVEL_OK);
	    	      	    projpage.flist.refresh();
            		}),
                    bind(function() {
                        status_button("Error contacting server",
			            LEVEL_ERROR, "retry", bind(this.rm_autosaves, this, true));
                    })
        );
    }

	this._undel_callback = function(nodes) {
		status_button("Successfully undeleted file(s)",
		LEVEL_OK, 'goto HEAD', bind(projpage.flist.change_rev, projpage.flist, 'HEAD'));
	}
	this.undel = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for undeletion", LEVEL_ERROR);
			return;
		}

		var files = projpage.flist.selection
        var project = projpage.project

        for (var i = 0; i < files.length; i++) {
            files[i] = IDE_path_get_file(files[i])
        }

        IDE_backend_request("file/co",
                            {
                                team:team,
                                project:project,
                                files:files,
                                revision:projpage.flist.rev
                            },
                            bind(
                                function() {
                                    IDE_backend_request("proj/commit",
                                                        {
                                                            team:team,
                                                            project:project,
					                                        message : 'Undelete '+files+' to '+IDE_hash_shrink(projpage.flist.rev),
                                                            paths:files
                                                        },
                                                        bind(this._undel_callback,this),
                                                        bind(function() {
                                                                status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.undel, this, true)
                                                                             );
                                                                        }
                                                            ,this)
                                                       )
                                }
                            ,this),
                            bind(function() { status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.undel, this, true));},this)
        )

	}

	this.check_code = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files selected for checking", LEVEL_ERROR);
			return;
		}

		for( var i=0; i<projpage.flist.selection.length; i++) {
			if(projpage.flist.selection[i].substr(projpage.flist.selection[i].length-3) == '.py')
				errorspage.check(projpage.flist.selection[i], {switch_to : true, projpage_multifile : true});
			else
				status_msg("Please select valid individual files, not folders", LEVEL_WARN);
		}
	}

	// Don't use bind on external items in case they don't exist yet.
	this.ops.push({ "name" : "Select None",
			"action" : function() { projpage.flist.select_none(); },
			"handle" : getElement("proj-select-none"),
			'isWrite' : false,
			"event" : null});

	this.ops.push({ "name" : "Select All",
			"action" : function() { projpage.flist.select_all(); },
			"handle": getElement("proj-select-all"),
			'isWrite' : false,
			"event" : null});

	this.ops.push({ "name" : "New File",
			"action" : function() { editpage.new_file(); },
			"handle" : getElement("op-newfile"),
			'isWrite' : true,
			"event" : null});

	this.ops.push({ "name" : "New Directory",
			"action" : bind(this.new_folder, this, null, null),
			"handle": getElement("op-mkdir"),
			'isWrite' : true,
			"event" : null});

	this.ops.push({ "name" : "Move",
			"action" : bind(this.mv, this),
			"handle": getElement("op-mv"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Copy",
			"action" : bind(this.cp, this),
			"handle": getElement("op-cp"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Delete",
			"action" : bind(this.rm, this, false),
			"handle": getElement("op-rm"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Undelete",
			"action" : bind(this.undel, this),
			"handle": getElement("op-undel"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Delete AutoSaves",
			"action" : bind(this.rm_autosaves, this, false),
			"handle": getElement("op-rm_autosaves"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Check Files' Code",
			"action" : bind(this.check_code, this),
			"handle": getElement("op-check"),
			'isWrite' : false,
			"event" : null });

	this.ops.push({ "name" : "View Log",
			"action" : bind(this.view_log, this),
			"handle": getElement("op-log"),
			'isWrite' : false,
			"event" : null });

	this.init();
}
