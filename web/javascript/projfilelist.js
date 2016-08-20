// vim: noexpandtab

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
};

ProjFileList.prototype.change_rev = function(revision) {
	this.update(this._project, this._team, revision);
};

// Request and update the project file listing
ProjFileList.prototype.update = function( pname, team, rev ) {
	logDebug( "ProjFileList.update( \"" + pname + "\", " + team + ", "+rev+" )" );
	if (pname == "") {
		// No project selected.
		this._hide();
		return;
	}

	var curr_rev = this.rev;

	if (rev == undefined || rev == null) {
		this.rev = "HEAD";
	} else {
		this.rev = rev;
	}

	if (pname != this._project || team != this._team || rev != curr_rev) {
		// Hide the list whilst we're loading it
		var new_list = DIV( {"id": "proj-filelist", "class": "loading"},
		                    "Loading project file listing..." );
		swapDOM( "proj-filelist", new_list);
	}
	if (pname != this._project || team != this._team) {
		this.select_none();
	}

	this._project = pname;
	this._team = team;
	this.refresh();
};

ProjFileList.prototype._prepare_auto_refresh = function() {
	log('Preparing an automatic file list refresh');
	if (this._timeout != null) {
		this._timeout.cancel();
	}

	if (!this._showing_head()) {
		return;
	}

	this._timeout = callLater(this._refresh_delay, bind(this._auto_refresh, this));
};

ProjFileList.prototype._auto_refresh = function() {
	//do we want to run a refresh?
	if (!this._showing_head()) {
		return;
	}

	// this will bail if there's no project selected,
	// but that's not something we need to worry about,
	// because we'll get setup again if the user selects a project.
	this.refresh(true);
};

ProjFileList.prototype._showing_head = function() {
	// TODO: check and see if we can remove the 'or 0' part of this.
	return this.rev == "HEAD" || this.rev == null || this.rev == 0;
};

ProjFileList.prototype.refresh = function(auto) {
	log('Doing a file list refresh');
	if (IDE_string_empty(this._project)) {
		return 'no_proj';
	}

	//kill the error message, if it exists
	if (this._err_prompt != null) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this._timeout = null;
	var err_handler;
	if (!auto) {	//if it's an automatic call don't interrupt the user - just setup another
		err_handler = bind( function (){
			this._err_prompt = status_button( "Error retrieving the project file listing", LEVEL_ERROR,
					   "retry", bind( this.refresh, this ) );
		}, this );
	} else {
		err_handler = bind( this._prepare_auto_refresh, this );
	}
	IDE_backend_request("file/compat-tree", {team:    this._team,
	                                         project: this._project,
	                                         rev:     this.rev,
	                                         path:    "."},
	                                         bind(this._received, this), err_handler);
};

ProjFileList.prototype._hide = function() {
	hideElement('proj-filelist');
};

ProjFileList.prototype._show = function() {
	showElement('proj-filelist');
};

//compare filelist items for use in sorting it
function flist_cmp(a, b) {
	if (a.name.toLowerCase() > b.name.toLowerCase()) {
		return 1;
	}
	return -1;
}

// Handler for receiving the file list
ProjFileList.prototype._received = function(nodes) {
	this._deferred_selection = this.selection;
	this.selection = [];
	this._birth = new Date().valueOf();
	log( "filelist received" );
	this.robot = false;	//test for robot.py: reset before a new filelist is loaded

	var children = map( bind(this._dir, this, 0), nodes.tree.sort(flist_cmp) );
	var new_list = UL({ "id": "proj-filelist", "style": "display:none" }, children);
	swapDOM("proj-filelist", new_list);

	this._deferred_selection = [];

	this._show();
};

// Produce an object consisted of "level" levels of nested divs
// the final div contains the DOM object inner
ProjFileList.prototype._nested_divs = function(level, inner) {
	if (level == 0) {
		return inner;
	}

	if (level > 1) {
		return DIV( null, this._nested_divs(level - 1, inner) );
	}

	return DIV( null, inner );
};

// Returns a DOM object for the given node
ProjFileList.prototype._dir = function(level, node) {
	// Assemble the link with divs in it
	var attrs = { href: '#', ide_path: node.path, ide_kind: node.kind };
	var is_file = node.kind == "FILE";
	var children = this._nested_divs(level, node.name + (is_file ? '' : '/'));
	var link = A(attrs, children);
	connect( link, "onclick", bind(this._onclick, this) );

	var node_li = LI(attrs, link);
	if (is_file) {
		// Assemble links to available autosave, if there is one
		var autosave_link = this._autosave_link(node);
		if (level == 0 && node.path.endsWith('/robot.py')) {
			this.robot = true;
		}
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
};

// Returns a DOM link for the given node's autosave, if it exists
ProjFileList.prototype._autosave_link = function(node) {
	if (node.kind != "FILE" || node.autosave == 0) {
		return null;
	}

	// Assemble the link with divs in it
	var props = {
		"href": "#",
		"class": 'autosave',
		"ide_path": node.path,
		"ide_kind": 'AUTOSAVE'
	};
	var label = 'Load autosave ('+(new Date(node.autosave * 1000)).toDateString()+')';
	var link = A(props, label);
	connect( link, "onclick", bind( this._onclick, this ) );
	return link;
};

// The onclick event for the filelist items
ProjFileList.prototype._onclick = function(ev) {
	// Prevent the browser doing something when someone clicks on this
	ev.preventDefault();
	ev.stopPropagation();

	var mods = ev.modifier();

	var src = ev.src();
	var kind = getNodeAttribute( src, "ide_kind" );
	var path = getNodeAttribute( src, "ide_path" );

	if (mods.ctrl || mods.meta) {	//meta is the mac command button, which they use like Win/Linux uses ctrl
		if (!this._is_file_selected(path)) {
			this._select_path( path, src.parentNode );
		} else {
			this._deselect_path( path, src.parentNode );
		}
	} else {
		if (kind == "FOLDER") {
			this._toggle_dir( src );
		} else if (kind == 'AUTOSAVE') {
			editpage.edit_file( this._team, this._project, path, this.rev, 'AUTOSAVE' );
			//do something special
		} else {
			editpage.edit_file( this._team, this._project, path, this.rev, 'REPO' );
		}
	}
};

ProjFileList.prototype.select_all = function() {
	var files = getElement('proj-filelist').getElementsByTagName('li');
	for (var i=0; i<files.length; i++) {
		this._select_path(getNodeAttribute(files[i].firstChild, "ide_path"), files[i]);
	}
};

ProjFileList.prototype.select = function(path) {
	var root = getElement('proj-filelist');
	if (hasElementClass(root, 'loading')) {
		this.selection.push(path);
	}
	var allNodes = root.getElementsByTagName('li');
	for (var i=0; i < allNodes.length; i++) {
		var node = allNodes[i];
		if (getNodeAttribute(node.firstChild, 'ide_path') == path) {
			this._select_path(path, node);
			break;
		}
	}
};

ProjFileList.prototype.select_none = function() {
	this.selection = [];
	var selected = getElementsByTagAndClassName('li', "selected", getElement('proj-filelist'));
	for (var i = 0; i<selected.length; i++) {
		removeElementClass(selected[i], "selected");
	}
};

ProjFileList.prototype._is_file_selected = function( path ) {
	for (var i=0; i < this.selection.length; i++) {
		if (this.selection[i] == path) {
			return true;
		}
	}
	return false;
};

ProjFileList.prototype._select_path = function(path, node) {
	addElementClass( node, "selected" );
	this.selection.push( path );
};

ProjFileList.prototype._deselect_path = function(path, node) {
	removeElementClass( node, "selected" );
	var i = findValue( this.selection, path );
	if (i >= 0) {
		// Remove from the listn
		this.selection.splice( i, 1 );
	}
};

// Toggles the display of the contents of a directory
ProjFileList.prototype._toggle_dir = function(src) {
	// Get a handler on its children
	var dir_contents = getFirstElementByTagAndClassName( "UL", null, src.parentNode );

	display = "";
	if (getStyle(dir_contents, "display") != "none") {
		display = "none";

		var nc = this._ul_get_num_children(dir_contents);

		var c = " child";
		if (nc != 1) {
			c = c + "ren";
		}

		var div = this._get_innerdiv( src );
		appendChildNodes( div,
				  SPAN({"class":"proj-filelist-dir-collapse"},
				       " [ " + nc + c + " hidden ]"));

	} else {
		removeElement( getFirstElementByTagAndClassName( "SPAN", null, src ) );
	}

	setStyle(dir_contents, {"display": display});
};

// Returns the innermost DIV within in given element
// Assumes that there's only one DIV per level
ProjFileList.prototype._get_innerdiv = function(elem) {
	var d = getFirstElementByTagAndClassName( "DIV", null, elem );
	if (d == null) {
		return elem;
	} else {
		return this._get_innerdiv(d);
	}
};

ProjFileList.prototype._ul_get_num_children = function(ul) {
	var count = 0;
	for (var i = 0; i < ul.childNodes.length; i++) {
		if (ul.childNodes[i].tagName == "LI") {
			count++;
		}
	}
	return count;
};
