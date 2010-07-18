// File save dialog
// Arguments:
//  - cback: Callback for when things happen.
//  - options: Dictionary of settings:
// 		- "type" 	'isFile' 	- renders file browser with file name box
// 				'isDir' 	- renders file browser with folder name box
// 				'isCommit' 	- renders file browser without file view, just commit box
// 				'isProj' 	- renders file browser without file view, just commit box

function Browser(cback, options) {
	// Public functions:
	//  - clickSaveFile(bool noMsg): event handler for when save is clicked, if noMsg is true: ignore lack of commit msg
	//  - clickCancelSave(): cancel and close browser
	//  - dirSelected(): event handler for when a directory is selected in the left hand pane
	//  - display(): show all browser css
	//  - hide():hide all browser css

	// Private functions:
	//  - _init:	Constructor. Displays filebrowser, grabs file tree and connects up events.
	//  - _receiveTree: AJAX success callback
	//  - _errorReceiveTree: AJAX fail callback
	//  - _getFileTree: Grab file tree from server
	//  - _processTree: Recursive function to turn filelist into DOM

	this.newDirectory = '/';
	this.newFname = "";
	this.commitMsg = "";

	this._DEFAULT_MSG = "Commit message";
	this._DEFAULT_FNAME = "new.py";
	this._DEFAULT_PNAME = "new-project";
	this._DEFAULT_DNAME = "new-directory";

	this._List = new Array();
	this.type = options.type;
	this.title = options.title;

	//hold the ident for the escape catcher
	this._esc_press = null;

	this.fileTree = null;
	this.callback = cback;
	this._selector = null;
	this._init();
}

Browser.prototype._init = function() {

	$("new-commit-msg").value = this._DEFAULT_MSG;
	if(this.type == 'isDir')
		$("new-file-name").value = this._DEFAULT_DNAME;
	else if(this.type == 'isProj')
		$("new-file-name").value = this._DEFAULT_PNAME;
	else
		$("new-file-name").value = this._DEFAULT_FNAME;

	//make visible
	this.display();

	//get file listings - if not just commit message
	if(this.type != 'isCommit' && this.type != 'isProj') {
		var projlist = new ProjList();
		projlist.update(team);
		// The selection box for selecting a project
		this._selector = new ProjSelect(projlist, $("browser-project-select"));
		connect( this._selector, "onchange", bind( this._getFileTree, this, team ) );
	}

	//clear previous events
	disconnectAll("save-new-file");
	disconnectAll("cancel-new-file");
	disconnectAll("new-commit-msg");
	disconnectAll("new-file-name");
	disconnectAll("left-pane");
	disconnect(this._esc_press);
	//set up event handlers
	this._esc_press = connect(document, 'onkeydown', bind(this._window_keydown, this));
	connect("new-file-name", 'onkeypress', bind(this._new_file_keypress, this));
	connect("save-new-file", 'onclick', bind(this.clickSaveFile, this, false, false));
	connect("cancel-new-file", 'onclick', bind(this.clickCancelSave, this));
	connect("new-commit-msg","onfocus", bind(this._msg_focus, this));
	connect("new-file-name","onfocus", bind(this._fname_focus, this));
	connect("left-pane","onclick", bind(this.rootSelected, this));

	if(this.type == 'isProj')
		$("new-file-name").focus();
	else
		$("new-commit-msg").focus();
}

Browser.prototype._new_file_keypress = function(ev) {
	if(ev._event.keyCode == 13 && ev._modifier == null) {
		log('Enter pressed - browser saving')
		signal("save-new-file", 'onclick');
	}
}

Browser.prototype._window_keydown = function(ev) {
	if(ev._event.keyCode == 27 && ev._modifier == null) {
		log('Escape pressed - hiding browser')
		signal("cancel-new-file", 'onclick');
	}
}

Browser.prototype._receiveTree = function(nodes) {
	this.fileTree = nodes.tree;
	$('save-new-file').disabled = false;
	//default to the first directory
	replaceChildNodes($("left-pane-list"), null);
	replaceChildNodes($("right-pane-list"), null);
	this._processTree($("left-pane-list"), this.fileTree, "");
	//fake a click on the LHS root to show the root files on the RHS
	signal("left-pane", 'onclick');
}

Browser.prototype._errorReceiveTree = function(a) {
	$("browser-status").innerHTML = "ERROR :: Unable to retrieve file list for "+a;
	signal("left-pane", 'onclick');
}

Browser.prototype._getFileTree = function(tm) {
	if($("browser-project-select").options[$("browser-project-select").selectedIndex].id == 'projlist-tmpitem')
		return;

	this._receiveTree({tree:[{autosave:0,children:[],kind:'FOLDER',name:'Loading...',path:'@',rev:-1}]});
	$('save-new-file').disabled = true;

	var d = loadJSONDoc("./filelist", { team : tm,
					    project : $("browser-project-select").value
					  });

	d.addCallback( bind(this._receiveTree, this));
	d.addErrback( bind(this._errorReceiveTree, this, $("browser-project-select").value));
}

Browser.prototype._badCommitMsg = function(msg) {
	//test for is-nothing or is-only-whitespace
	return this.commitMsg == this._DEFAULT_MSG || /^$|^\s+$/.test(msg);
}

Browser.prototype._badFname = function(name) {
	//test for is-nothing or starts-with-whitespace or starts-with-a-dot
	return /^$|^\s|^[.]/.test(name);
}

//when user clicks save
Browser.prototype.clickSaveFile = function(noMsg) {
	disconnectAll("browser-status");
	this.commitMsg = $("new-commit-msg").value;
	this.newFname = $("new-file-name").value;

	switch(this.type) {
		case 'isFile':
			var type = 'file';
			break;
		case 'isDir':
			var type = 'directory';
			break;
		case 'isProj':
			var type = 'project';
			break;
	}

	//don't allow null strings or pure whitespace
	if(this._badFname(this.newFname)) {
		$("browser-status").innerHTML = "Please specify a valid "+type+" name:";
		$("new-file-name").focus();
		return;
	}

	//fail if the file list is requested, but hasn't loaded
	if((this.type == 'isFile' || this.type == 'isDir') && this.fileTree == null) {
		$("browser-status").innerHTML = "Unable to save - file list not yet loaded";
		return;
	}

	//file, dir or project name already exists
	logDebug('Finding '+this.newFname+' in '+this._List+' : '+(findValue(this._List, this.newFname) > -1) );
	if( ( ( this.type == 'isFile' || this.type == 'isDir' ) && findValue(this._List, this.newFname) > -1 ) ||
		( this.type == 'isProj' && projpage.project_exists(this.newFname) )
	) {
		var warn = '"'+this.newFname+'" already exists';
		if(this.type == 'isProj')
			warn = 'Project '+warn;
		else
			warn += ' in "'+this.newDirectory.substr(1)+'"';

		$("browser-status").innerHTML = warn+"!";
		$("new-file-name").focus();
		return;
	}

	var commitErrFlag = ( !noMsg &&
		this.type != 'isProj' &&
		this._badCommitMsg(this.commitMsg) );

	if(commitErrFlag) {
		$("browser-status").innerHTML = "No commit message added - click to ignore";
		connect($("browser-status"), 'onclick', bind(this.clickSaveFile, this, true));
		$("new-commit-msg").focus();
		return;
	}

	disconnectAll("browser-status");

	switch(this.type) {
		case 'isFile' :
		case 'isDir' :
			this.callback("/"+this._selector.project+this.newDirectory+this.newFname, this.commitMsg);
			break;
		case 'isProj' :
			this.callback(this.newFname);
			break;
		case 'isCommit' :
			this.callback(this.commitMsg);
			break;
	}
	this.hide();
}

//cancel save operation
Browser.prototype.clickCancelSave = function() {
	status_hide();
	this.commitMsg = "";
	this.newFilePath = "";
	this.hide();
}

//Recursive function to convert filetree into valid DOM tree
Browser.prototype._processTree = function(parentDOM, tree, pathSoFar) {
	for (var i = 0; i < tree.length; i++) {
		if(tree[i].kind == "FOLDER") {
			//create entry in folder list
			var newPathSoFar = pathSoFar+"/"+tree[i].name;

			var newLeaf = LI(null, tree[i].name+(tree[i].path == '@' ? '' : "/"));

			connect(newLeaf, 'onclick', bind(this.dirSelected, this, newPathSoFar+"/", tree[i].children));

			//create new list for child folder
			var newBranch = LI(null, "");
			appendChildNodes(newBranch, UL(null, ""));
			appendChildNodes(parentDOM, newLeaf);

			//recursive call, with newly created branch
			appendChildNodes(parentDOM, this._processTree(newBranch.childNodes[1], tree[i].children, newPathSoFar));
		}
	}
	return parentDOM;
}

Browser.prototype.rootSelected = function() {
	log('Browser: root folder selected');
	this.dirSelected('/', this.fileTree);
}

Browser.prototype.dirSelected = function(directory, thingsInside, ev) {
	if(ev != undefined)
		ev.stop();
	logDebug("Folder selected :"+directory);
	//update selected directory
	this.newDirectory = directory;
	$("selected-dir").innerHTML = directory;

	//empty file list
	replaceChildNodes($("right-pane-list"));

	//populate file list with only files from all the things contained within this directory
	this._List = new Array();
	for (var j = 0; j < thingsInside.length; j++) {
		if(thingsInside[j].kind == "FILE") {	//build li and append to pane
			var li = LI(null, "");
			li.innerHTML = thingsInside[j].name;
			appendChildNodes($("right-pane-list"), li);
		}
		this._List.push(thingsInside[j].name);	//add to the list of all things regardless
	}
}

Browser.prototype.display = function() {
	showElement($("file-browser"));
	showElement($("grey-out"));

	switch(this.type) {
		case 'isFile':
			$("browser-status").innerHTML = "Please Select a save directory & new file name";
			$("browser-title").innerHTML = this.title || "File Save As";
			showElement("save-path");
			showElement("right-pane");
			showElement("left-pane");
			showElement("new-commit-msg");
			showElement("new-file-name");
			break;
		case 'isDir' :
			$("browser-status").innerHTML = "Please Select a save directory & new directory name";
			$("browser-title").innerHTML = this.title || "New Directory";
			showElement("save-path");
			showElement("right-pane");
			showElement("left-pane");
			showElement("new-commit-msg");
			showElement("new-file-name");
			break;
		case 'isCommit' :
			$("browser-status").innerHTML = "Please add a commit message before saving";
			$("browser-title").innerHTML = "Commit Message:";
			hideElement("save-path");
			hideElement("right-pane");
			hideElement("left-pane");
			showElement("new-commit-msg");
			hideElement("new-file-name");
			break;
		case 'isProj' :
			$("browser-status").innerHTML = "Enter new project name:";
			$("browser-title").innerHTML = this.title || "New Project";
			hideElement("save-path");
			hideElement("right-pane");
			hideElement("left-pane");
			hideElement("new-commit-msg");
			showElement("new-file-name");
			break;
	}
}
Browser.prototype.hide = function() {
	logDebug("Hiding File Browser");
	disconnectAll("save-new-file");
	disconnectAll("cancel-new-file");
	disconnectAll("new-commit-msg");
	disconnectAll("new-file-name");
	disconnectAll("left-pane");
	disconnectAll("browser-status");
	disconnect(this._esc_press);
	hideElement($("file-browser"));
	hideElement($("grey-out"));
	replaceChildNodes($("left-pane-list"));
	replaceChildNodes($("right-pane-list"));
}

Browser.prototype._msg_focus = function() {
	var t = $("new-commit-msg");

	if( t.value == this._DEFAULT_MSG )
		// Select all
		t.select();
}

Browser.prototype._fname_focus = function() {
	var t = $("new-file-name");

	if( t.value == this._DEFAULT_FNAME )
		// Select all
		t.select();
}
