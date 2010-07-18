// vim: noexpandtab
// The project page
function ProjPage() {
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

	this.flist = null;
	this.project = "";

	this.last_updated	= new Date();

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
	this._selector = new ProjSelect(this._list, $("project-select"));
	connect( this._selector, "onchange", bind( this._on_proj_change, this ) );

	this.flist = new ProjFileList();
	// Update the file list when the project changes
	connect( this._selector, "onchange", bind( this.flist.update, this.flist ) );

	this._calendar = new Calendar();
	// Refresh the calendar when the project changes
	connect(this._selector, "onchange", bind( this._calendar.change_proj, this._calendar ) );

	// Connect up the project management buttons
	connect("new-project",		'onclick', bind(this.clickNewProject, this));
	connect("archive-project",	'onclick', bind(this.clickArchiveProject, this));
	connect("copy-project",		'onclick', bind(this.clickCopyProject, this));
	connect("check-code",		'onclick', bind(this.clickCheckCode, this));
//	The simulator isn't ready yet, and this fails anyway:
//	connect("simulate-project",	'onclick', bind(this.clickSimulateProject, this));
	connect("export-project",	'onclick', bind(this.clickExportProject, this));

	// We have to synthesize the first "onchange" event from the ProjSelect,
	// as these things weren't connected to it when it happened
	this._on_proj_change( this._selector.project );
	this.flist.update( this._selector.project, this._selector._team );

	this._initted = true;
}

ProjPage.prototype.show = function() {
	logDebug( "Projpage.show: Current project is \"" + this.project + "\"" );
	this._init();

	setStyle('projects-page', {'display':'block'});
}

ProjPage.prototype.hide = function() {
	logDebug( "Hiding the projects page" );
	setStyle('projects-page', {'display':'none'});
}

ProjPage.prototype.hide_filelist = function() {
	logDebug( "Hiding the file list" );
	this.flist._hide();
}

ProjPage.prototype.project_exists = function(pname) {
	return this._list.project_exists(pname);
}

ProjPage.prototype.projects_exist = function() {
	if(this._list.projects.length > 0)
		return true;
	else
		return false;
}

ProjPage.prototype._on_proj_change = function(proj, team) {
	logDebug( "ProjPage._on_proj_change(\"" + proj + "\", " + team + ")" );
	this.project = proj;

	if( proj == "" )
		this._rpane_hide();
	else {
		$("proj-name").innerHTML = "Project " + this.project;
		this._rpane_show();
	}

}

ProjPage.prototype.set_team = function(team) {
	logDebug( "ProjPage.set_team( " + team + " )" );
	if( team == null || team == 0 )
		return;

	this._init();

	// Start the chain of updates
 	this._list.update(team);
	// The selector and filelist are connected to onchange on the list,
	// so they will update when it's updated
}

// ***** Project Page Right Hand pane *****
ProjPage.prototype._rpane_hide = function() {
	setStyle( "proj-rpane", {'display':'none'} );
}

ProjPage.prototype._rpane_show = function() {
	setStyle( "proj-rpane", {'display':''} );
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

	var d = loadJSONDoc("./copyproj", { 'team' : team,
				'src' : '/'+this.project,
				'dest' : '/'+newProjName
			});
	d.addCallback( bind( partial(this._CopyProjectSuccess, newProjName), this));
	d.addErrback( bind( function() {
		status_button( "Copy Project: Error contacting server", LEVEL_ERROR, "retry",
			bind(this.CreateCopyProject, this, newProjName) );
	}, this ) );
}

ProjPage.prototype._CopyProjectSuccess = function(newProjName, nodes) {
	if(nodes.status > 0)
		status_msg("ERROR COPYING: "+nodes.message, LEVEL_ERROR);
	else {
		status_msg("Project copy successful", LEVEL_OK);
		if(projtab.has_focus()) {
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

	var d = loadJSONDoc("./createproj",{ name : newProjName, team : team });
	d.addCallback(bind(this._createProjectSuccess, this, newProjName));
	d.addErrback(bind(this._createProjectFailure, this));
}

ProjPage.prototype._createProjectSuccess = function(newProjName) {
	status_msg("Created project successfully", LEVEL_OK);
	update(team)
	// Transition to the new project once the project list has loaded
	this._selector.trans_project = newProjName;
	this._list.update(team);
}

ProjPage.prototype._createProjectFailure = function() {
	/* XXX - check for preexisting projects perhaps */
	status_msg('Create project failed', LEVEL_ERROR);
}

/*** The simulator is nowhere near ready, so hide this for the moment
ProjPage.prototype.clickSimulateProject = function() {
	if( $('projlist-tmpitem') != null && $('projlist-tmpitem').selected == true ) {
		status_msg( "No project selected, please select a project", LEVEL_ERROR );
		return;
	}

	if( !this.flist.robot ) {	//if there's no robot.py script then it's going to fail
		status_msg( "A robot.py file is required for project simulation", LEVEL_ERROR );
		return false;
	}
	simpage.load(this.project);
}
*/

ProjPage.prototype.clickExportProject = function() {
	if( $('projlist-tmpitem') != null && $('projlist-tmpitem').selected == true ) {
		status_msg( "No project selected, please select a project", LEVEL_ERROR );
		return;
	}

	if( !this.flist.robot ) {	//if there's no robot.py script then it's going to fail
		status_msg( "A robot.py file is required for project code export", LEVEL_ERROR );
		return false;
	}

	errorspage.check("/"+this.project+"/robot.py", {'switch_to':true, 'alert':true, 'quietpass':true, 'callback':bind(this._exportProjectCheckResult, this)});
}

ProjPage.prototype._exportProjectCheckResult = function(result, num_errors) {
	if(result == 'pass') {
		this._exportProject();
		return;
	}
	if(result == 'codefail') {	//bad code
		var message = num_errors+" errors found";
	} else if(result == 'checkfail') {	//the check failed
		var message = "Failed to check code";
	}
	log('_exportProjectCheckResult:'+message);
	status_options( message, LEVEL_WARN,
				[{text:"retry", callback:bind( this.clickExportProject, this )},
				 {text:"export anyway", callback:bind( this._exportProject, this )}]
			);
}

ProjPage.prototype._exportProject = function() {
	if( this._iframe == null ) {
		this._iframe = $('robot-zip');
	}

	this._iframe.src = "./checkout?team=" + team + "&project=" + this.project;
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

	errorspage.check("/"+this.project+"/robot.py", {'switch_to':true});
}

// ***** Project Page File Listing *****
function ProjFileList() {
	this._project = "";
	this._team = null;

	//allow for an auto refresh
	this._timeout = null;
	//how often to check to see if it's needed, in seconds
	this._refresh_delay = 7;
	//when was it 'born', milliseconds since epoch
	this._birth = new Date().valueOf();
	//how old do we let it get before updating
	this._refresh_freq = 25 * 1000;	//milliseconds

	//prompt when it errors during an update
	this._err_prompt = null;

	// the project revision we're displaying
	// can be integer or "HEAD"
	this.rev = "HEAD";

	// whether or not there's a robot.py file
	this.robot = false;

	// The files/folders that are currently selected
	this.selection = [];

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

	//do we want to setup another one?
	if( projtab.has_focus() && this.selection.length > 0	//on projpage and something's selected
		|| this._birth + this._refresh_freq > new Date().valueOf()	//already new enough
		|| 'no_proj' == projpage.flist.refresh(true)	//no project set, direct failure sets another anyway
	)
		this._prepare_auto_refresh();
}

ProjFileList.prototype.refresh = function(auto) {
	log('Doing a file list refresh');
	if( this._project == "" )
		return 'no_proj';

	//kill the error message, if it exists
	if( this._err_prompt != null ) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this._timeout = null;
	var d = loadJSONDoc("./filelist", { 'team' : this._team,
					'project' : this._project,
					'rev' : this.rev,
					'date' : this._birth } );

	d.addCallback( bind( this._received, this ) );

	if(!auto)	//if it's an automatic call don't interrupt the user - just setup another
		d.addErrback( bind( function (){
			this._err_prompt = status_button( "Error retrieving the project file listing", LEVEL_ERROR,
					   "retry", bind( this.refresh, this ) );
		}, this ) );
	else
		d.addErrback( bind( this._prepare_auto_refresh, this ) );
}

ProjFileList.prototype._hide = function() {
	setStyle( "proj-filelist", {"display":"none"} );
}

ProjFileList.prototype._show = function() {
	setStyle( "proj-filelist", {"display":""} );
}

//compare filelist items for use in sorting it
function flist_cmp(a,b) {
	if(a.name.toLowerCase() > b.name.toLowerCase())
		return 1;
	return -1;
}

// Handler for receiving the file list
ProjFileList.prototype._received = function(nodes) {
	this.selection = new Array();
	this._birth = new Date().valueOf();
	log( "filelist received" );
	this._prepare_auto_refresh();
	this.robot = false;	//test for robot.py: reset before a new filelist is loaded

	swapDOM( "proj-filelist",
		 UL( { "id" : "proj-filelist",
		       "style" : "display:none" },
		     map( bind(this._dir, this, 0), nodes.tree.sort(flist_cmp) ) ) );

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
	var link = A( { "href" : "#",
			"ide_path" : node.path,
			"ide_kind" : node.kind },
		this._nested_divs( level, node.name + (node.kind == "FOLDER"?"/":"") ) );
	connect( link, "onclick", bind( this._onclick, this ) );

	// Assemble links to available autosave, if there is one
	var autosave_link = this._autosave_link( node, level );

	if( node.kind == "FILE" ) {
		var path_arr = node.path.split('/');
		if( path_arr[path_arr.length-1] == 'robot.py' && level == 0 )
			this.robot = true;
		var n = LI( null, link , autosave_link );
		return n;
	} else
		var n = LI( null, [ link,
			UL( { "class" : "flist-l" },
			map( bind(this._dir, this, level + 1), node.children.sort(flist_cmp) ) ) ] );
	return n;
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
				'AutoSave (r'+node.autosave.revision+' at '+node.autosave.date+')' );
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
	var files = $('proj-filelist').getElementsByTagName('li');
	for(var i=0; i<files.length; i++) {
		this._select_path(getNodeAttribute(files[i].firstChild, "ide_path"), files[i]);
	}
}

ProjFileList.prototype.select_none = function() {
	this.selection = [];
	var selected = getElementsByTagAndClassName('li', "selected", $('proj-filelist'));
	for(var i=0; i<selected.length; i++)
		removeElementClass( selected[i], "selected" );
}

ProjFileList.prototype._is_file_selected = function( path ) {
	for( var i in this.selection )
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
	for( var i in ul.childNodes ) {
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
	if(typeof team == 'string')
		team = parseInt(team);
	if(typeof team == 'number')
		this._team = team;

	//kill the error message, if it exists
	if( this._err_prompt != null ) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this.loaded = false;

	var d = loadJSONDoc("./projlist", { 'team' : this._team });

	d.addCallback( bind( this._got_list, this ) );

	d.addErrback( bind( function() {
		this._err_prompt = status_button( "Error retrieving the project list", LEVEL_ERROR,
			       "retry", bind( this._grab_list, this) );
	}, this ) );
}

ProjList.prototype._got_list = function(resp) {
 	this.projects = resp["projects"];
 	this.loaded = true;

	signal( this, "onchange", this._team );
}

ProjList.prototype.project_exists = function(pname) {
	logDebug('Finding '+pname+' in '+this.projects+' : '+(findValue(this.projects, pname) > -1) );
	return findValue(this.projects, pname) > -1;
}

// The project selector.
// Arguments:
//  - plist: The project list (ProjList)
//  - elem: The DOM object for the select box.
function ProjSelect(plist, elem) {
	this._elem = elem;
	this._plist = plist;

	// The project that's selected
	// Empty string means none selected
	this.project = "";
	// Project to transition to when the projlist changes
	this.trans_project = "";

	// The team that we're currently listing
	this._team = null;

	// Signals:
	//  - onchange: when the project selection changes.
	//              Handler passed the name of the new project.

	// Member functions:
	// Public:

	// Private:
	//  - _init: Initialisation.
	//  - _onchange: Handler for the select box onchange event.
	//  - _plist_onchange: Handler for when the list of projects changes.

	this._init();
}

ProjSelect.prototype._init = function() {
	connect( this._elem, "onchange", bind( this._onchange, this ) );
	connect( this._plist, "onchange", bind( this._plist_onchange, this ) );

	// If the list is already loaded when we're called, force update
	if( this._plist.loaded )
		this._plist_onchange();
}

// Called when the project list changes
ProjSelect.prototype._plist_onchange = function(team) {
	logDebug( "ProjSelect._plist_onchange" );
	var startproj = this.project;
	var startteam = this._team;
	var items = [];

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
			status_msg( "Please select a project", LEVEL_INFO );
			items.unshift( OPTION( { "id" : "projlist-tmpitem",
						 "selected" : "selected" }, "Select a project." ) );
		} else
			this.project = dp;
	}
	this._team = team;

	// Rebuild the select box options
	for( var p in this._plist.projects ) {
		var pname = this._plist.projects[p];
		var props = { "value" : pname };

		if( pname == this.project )
			props["selected"] = "selected";
		items[items.length] = ( OPTION( props, pname ) );
	}

	replaceChildNodes( this._elem, items );

	logDebug( "ProjList._plist_onchange: Now on project " + this._team + "." + this.project );

	if( startproj != this.project
	    || startteam != this._team )
		signal( this, "onchange", this.project, this._team );
}

// Handler for the onchange event of the select element
ProjSelect.prototype._onchange = function(ev) {
	//hide the status bar - anything there is now obselete
	status_hide();

	var src = ev.src();

	// Remove the "select a project" item from the list
	var tmp = $("projlist-tmpitem");
	if( tmp != null && src != tmp )
		removeElement( tmp );

	if( src != tmp ) {
		var proj = src.value;

		if( proj != this.project ) {
			this.project = proj;
			signal( this, "onchange", this.project, this._team );
		}
	}
}

ProjSelect.prototype._get_default = function() {
	if( this._plist.projects.length == 1 )
		return this._plist.projects[0];

	var dp = user.get_setting( "project.last" );

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


	this.init = function() {
		//connect up operations
		for(var i=0; i < this.ops.length; i++) {
			this.ops[i].event = connect(this.ops[i].handle, 'onclick', this.ops[i].action);
		}
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
			var d = loadJSONDoc("./newdir", { team : team,
						  path : new_name,
						  msg : new_msg});

			d.addCallback( this.receive_newfolder);
			d.addErrback( this.error_receive_newfolder, new_name, new_msg);
		}
	}

	this._mv_success = function(nodes) {
		logDebug("_mv_success()");
		logDebug(nodes.status);
		if(nodes.status == 0) {
			status_msg("Move successful!", LEVEL_OK);
			projpage.flist.refresh();
		} else {
			status_msg(nodes.message, LEVEL_ERROR);
		}
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

		var d = loadJSONDoc("./move", {team : team,
				   src : src, dest : dest, msg : cmsg});

		d.addCallback( bind( this._mv_success, this) );

		d.addErrback( bind( function (){
			status_button( "Error moving files/folders", LEVEL_ERROR,
				       "retry", bind( this._mv_cback, this, dest, cmsg ) );
		}, this ) );
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

	this._cp_callback1 = function(nodes) {
		if(nodes.status > 0) {
			status_msg("ERROR COPYING: "+nodes.message, LEVEL_ERROR);
		} else {
			status_msg("Successful Copy: "+nodes.message, LEVEL_OK);
			projpage.flist.refresh();
		}
	}
	this._cp_callback2 = function(fname, cmsg) {
		logDebug("copying "+projpage.flist.selection[0]+" to "+fname);

		if(fname == null || fname=="")
			return;

		var d = loadJSONDoc("./copy", {team : team,
				   src : projpage.flist.selection[0],
				   dest : fname,
				   msg : cmsg,
				   rev : 0  });
		d.addCallback( bind(this._cp_callback1, this));
		d.addErrback( bind( function() {
			status_button("Error contacting server", LEVEL_ERROR, "retry",
				bind(this._cp_callback2, this, fname, cmsg));
		} ), this );
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
		for( var i in projpage.flist.selection ) {
			death_list.push(projpage.flist.selection[i].substr(projpage.project.length+2))
		};
		death_list = death_list.join(',');

		logDebug("will delete: "+death_list);

		var d = loadJSONDoc("./delete", { "team" : team,
						  "project" : projpage.project,
						  "files" : death_list,
						  "kind" : 'ALL' });
		d.addCallback( function(nodes) {
			status_msg(nodes.Message, LEVEL_OK)
			projpage.flist.refresh();
		});

		d.addErrback(function() { status_button("Error contacting server",
				LEVEL_ERROR, "retry", bind(this.rm, this, true));});
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
		for( var i in projpage.flist.selection ) {
			death_list.push(projpage.flist.selection[i].substr(projpage.project.length+2))
		};
		death_list = death_list.join(',');

		log("Will delete autosaves: "+death_list);

		var d = loadJSONDoc("./delete", { "team" : team,
				    "project" : projpage.project,
				    "files" : death_list,
				    "kind" : 'AUTOSAVES' });

		d.addCallback( function(nodes) {
			status_msg(nodes.Message, LEVEL_OK);
			projpage.flist.refresh();
		});

		d.addErrback( function() { status_button("Error contacting server",
			    LEVEL_ERROR, "retry", bind(this.rm_autosaves, this, true));});
	}

	this._undel_callback = function(nodes) {
		num_success = nodes.success.split(',').length
		if(nodes.status > 0) {
			status_msg(' '+nodes.status+' files could not be undeleted, '+num_success+' succeeded', LEVEL_ERROR);
		} else {
			status_button("Successfully undeleted "+num_success+' file(s)',
			LEVEL_OK, 'goto HEAD', bind(projpage.flist.change_rev, projpage.flist, 'HEAD'));
		}
	}
	this.undel = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for undeletion", LEVEL_ERROR);
			return;
		}

		var files = projpage.flist.selection.join(',');
		var d = loadJSONDoc("./revert", {
					team : team,
					files : files,
					torev : projpage.flist.rev,
					message : 'Undelete '+files+'to r'+projpage.flist.rev
				});
		d.addCallback( bind(this._undel_callback, this));
		d.addErrback(function() { status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.undel, this, true));});
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

	this.ops.push({ "name" : "Select None",
			"action" : bind(projpage.flist.select_none, projpage.flist),
			"handle" : $("proj-select-none"),
			"event" : null});

	this.ops.push({ "name" : "Select All",
			"action" : bind(projpage.flist.select_all, projpage.flist),
			"handle": $("proj-select-all"),
			"event" : null});

	this.ops.push({ "name" : "New File",
			"action" : bind(editpage.new_file, editpage),
			"handle" : $("op-newfile"),
			"event" : null});

	this.ops.push({ "name" : "New Directory",
			"action" : bind(this.new_folder, this, null, null),
			"handle": $("op-mkdir"),
			"event" : null});

	this.ops.push({ "name" : "Move",
			"action" : bind(this.mv, this),
			"handle": $("op-mv"),
			"event" : null });

	this.ops.push({ "name" : "Copy",
			"action" : bind(this.cp, this),
			"handle": $("op-cp"),
			"event" : null });

	this.ops.push({ "name" : "Delete",
			"action" : bind(this.rm, this, false),
			"handle": $("op-rm"),
			"event" : null });

	this.ops.push({ "name" : "Undelete",
			"action" : bind(this.undel, this),
			"handle": $("op-undel"),
			"event" : null });

	this.ops.push({ "name" : "Delete AutoSaves",
			"action" : bind(this.rm_autosaves, this, false),
			"handle": $("op-rm_autosaves"),
			"event" : null });

	this.ops.push({ "name" : "Check Files' Code",
			"action" : bind(this.check_code, this),
			"handle": $("op-check"),
			"event" : null });

	this.ops.push({ "name" : "View Log",
			"action" : bind(this.view_log, this),
			"handle": $("op-log"),
			"event" : null });

	this.init();
}
