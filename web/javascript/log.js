// vim: noexpandtab
function Log(file, project) {
	//class properties
	this.tab = null;		//holds reference to tab in tabbar
	this.selectedRevision = -1;	//Selected revision, -1 indicates no revision set
	this.team = team;		//team number TODO: get this from elsewhere
	this.project = project || IDE_path_get_project(file); //holds the current project name
	this.user = null;		//holds author name
	this.userList = [];	//List of  users attributed to file(s)
	this.history = [];	//array of log entries returned by server
	this.file = file;		//the file/directory for which we are interested
	this.offset = 0;		//which results page we want to retrieve from the server
	this.pageCount = 0;		//stores the total number of results pages (retrieved from server)

	this._read_only = projpage.project_readonly(this.project);

	//do this only once: add a new tab to the tabbar and link it to this log page
	this.tab = new Tab("Log: "+this.file.toString());
	connect(this.tab, 'onfocus', bind(this._onfocus, this));
	connect(this.tab, 'onblur', bind(this._onblur, this));
	connect(this.tab, 'onclickclose', bind(this.close, this));
	tabbar.add_tab(this.tab);
	tabbar.switch_to(this.tab);
	//start initialisation
	this._init();

/*
	selectedRevision [int]	holds revision number to revert to
	userList [string[]]	Array of users who have made revisions to the selected  file(s)
	history			the array of dictionaries returned by the server
	file			the file(s) for which the logs are for
	user			holds the name of the user who's revisions have been selected
	overflow		holds the total number of page of results for  the latest query
	offset			holds the page offset for the next query

	_init()			Initialize function - clears variables and re-retrieves data from server
	_receiveHistory(rev)	Callback function for loadJSONDoc success
	_errorReceiveHistory()	Callback function for loadJSONDoc fail
	_retrieveHistory()	Instigates call to server to retrieve logs for given file(s) & users
	_populateList()		Takes history and updates list of logs on page, it also adds event handlers to log-menu buttons
	_update()		Event handler for when a new user is selected
	_revert(bool)		event handler for when user clicks 'revert' - if arg is false, confirmation is  requested from user
	_nextview(int)		event handler for when user clicks on 'older'/'newer' buttons,
				if int > 0 an older page of  results is retrieved, if int <0 a later page of results is retrieved
	_onfocus()		event handler for tab click
	_onblur()		event handler for tab looses focus
	close()			completely close the tab and log page
*/
}


Log.prototype._init = function() {
	logDebug("Initializing Log...");
	//clear data from previous query
	this.history = [];
	this.userList = [];
	//do new query
	this._retrieveHistory();
};

Log.prototype._receiveHistory = function(opt, revisions) {
	logDebug("Log history received ok");
	//extract data from query response
	update(this.history, revisions.log);
	update(this.userList, revisions.authors);
	this.pageCount = revisions.pages;
	if (opt == null || opt != 'quiet') {
		status_msg("File history loaded successfully", LEVEL_OK);
	}
	//present data
	this._populateList();
};

Log.prototype._errorReceiveHistory = function() {
	//handle failed request
	logDebug("Log history retrieval failed");
	this.history = [];
	status_button("Error retrieving history", LEVEL_WARN, "Retry", bind(this._receiveHistory, this));
};

Log.prototype._retrieveHistory = function(opt) {
	IDE_backend_request('file/log',
		{ team : team,
		  project: IDE_path_get_project(this.file),
		  path : IDE_path_get_file(this.file),
		  //this key is to filter by author
		  user : this.user,
		  offset : this.offset,
		  number : 10
		},
		bind(this._receiveHistory, this, opt),
		bind(this._errorReceiveHistory, this)
	);
};

Log.prototype._histDate = function(which) {
	var stamp = this.history[which].time;
	var d = new Date(stamp*1000);
	return d.toDateString();
};

//processess log data and formats into list. connects up related event handlers,
//deals with multile results pages
Log.prototype._populateList = function() {
	logDebug("Processing log list...");

	//print summary information
	var entries = this.history.length;
	var logSummaryElem = getElement("log-summary");
	if (entries <= 0) {
		logSummaryElem.innerHTML = "There are no revisions available for file(s): "+this.file;
	} else {
		logSummaryElem.innerHTML = "Displaying "+entries+" revision(s) between "+
			this._histDate(this.history.length-1)+" & "+this._histDate(0)+
			" Page "+(this.offset+1)+" of "+(this.pageCount);
	}

	//fill drop down box with authors attributed to file(s)
	//clear list:
	replaceChildNodes('repo-users');

	//first item in list is: 'Filter by user'
	var opt = OPTION({"value":-1}, "Filter by user");
	appendChildNodes('repo-users', opt);

	//second item in the list is: 'all' meaning, show logs from all users
	var opt = OPTION({"value":-1}, "Show all");
	appendChildNodes('repo-users', opt);

	//now add all attributed authors
	for (var i = 0; i < this.userList.length; i++) {
		var opt = OPTION({"value":i}, this.userList[i]);
		appendChildNodes('repo-users', opt);
	}
    //remove event handler for when user applies filter to results
	disconnectAll('repo-users');


	//clear log list
	var logListElem = getElement("log-list");
	replaceChildNodes(logListElem);
	//now populate log list
	for (var x = 0; x <this.history.length; x++) {
		var logtxt = SPAN(IDE_hash_shrink(this.history[x].hash)+" | "+this.history[x].author+" | "+this._histDate(x));
		var radio = INPUT({'type' : 'radio', 'name' : 'log', 'class' : 'log-radio', 'value' : this.history[x].hash });
		var label = LABEL( null, radio, logtxt );
		var commitMsg = DIV({'class' : 'commit-msg'}, this.history[x].message);
		appendChildNodes(logListElem, LI(null, label, commitMsg));
	}
	//make selected user selected in drop down box (visual clue that filter is applied)
	if (this.user != null) {
		getElement('repo-users').value = findValue(this.userList, this.user);
	}

	//connect event handler for when user applies filter to results
	connect('repo-users', 'onchange', bind(this._update, this));

	//disconnect the older/newer buttons
	var olderButton = getElement('older');
	var newerButton = getElement('newer');
	disconnectAll(olderButton);
	disconnectAll(newerButton);

	//if older results are available, enable the 'older' button and hook it up
	if (this.offset < (this.pageCount-1)) {
		olderButton.disabled = false;
		connect(olderButton, 'onclick', bind(this._nextview, this, +1));
	} else {
		olderButton.disabled = true;
	}

	//if newer results are available, enable the 'newer' button and hook it up
	if (this.offset > 0) {
		newerButton.disabled = false;
		connect(newerButton, 'onclick', bind(this._nextview, this, -1));
	} else {
		newerButton.disabled = true;
	}

	//connect up the 'Revert' button to event handler
	var revertElem = getElement('revert');
	disconnectAll(revertElem);
	revertElem.disabled = this._read_only;
	if (!this._read_only) {
		connect(revertElem, 'onclick', bind(this._revert, this, false));
	}

	//connect up the 'Diff' button to event handler
	disconnectAll('log-diff');
	connect('log-diff', 'onclick', bind(this._diff, this));

	//connect up the 'View' button to event handler
	disconnectAll('log-open');
	connect('log-open', 'onclick', bind(this._open, this));

	//connect up the close button on log menu
	disconnectAll("log-close");
	connect("log-close", 'onclick', bind(this.close, this));
};
//get older (updown > 0) or newer (updown < 0) results
Log.prototype._nextview = function(updown) {
	this.offset = this.offset+updown;
	//get new results page
	this._init();
};
//called when user applies author filter
Log.prototype._update = function() {
	//find out which author was selected  using select value as key to userList array
	var index = getElement('repo-users').value;
	//if user clicks 'All' (-1) clear user variable
	if (index > -1) {
		this.user = this.userList[index];
	} else {
		this.user = null;
	}
	logDebug("Filter logs by user: "+this.user);
	//reset offset
	this.offset = 0;
	this._init();
};

Log.prototype._receiveRevert = function(nodes ,args) {
    arg_nodes = args.nodes;
	if (nodes.commit != "") {
		status_msg("Successfully reverted to version "+this.selectedRevision+" (New Revision: "+nodes.commit+")", LEVEL_OK);
	} else {
		status_msg("Failed to revert: "+nodes.success, LEVEL_ERROR);
	}
	//in either case update the history
	this._retrieveHistory('quiet');

	//refresh all open file pages, this will need fixing later
	for (var key in editpage._open_files) {
		var fileHandle = editpage._open_files[key];
		if (fileHandle.path == this.file) {
			fileHandle._load_contents();
			fileHandle._update_contents();
		}
	}
};

Log.prototype._errorReceiveRevision = function(commitMsg) {
	status_button("Unable to contact server to revert file", LEVEL_ERROR, "retry", bind(this._do_revert, this, commitMsg));
};
Log.prototype._do_revert = function(commitMsg) {
	var args = { team: team,
	          project: IDE_path_get_project(this.file),
	            files: [IDE_path_get_file(this.file)],
	         revision: this.selectedRevision
	           };
	var do_commit = function(nodes) {
		var args = { team: team,
		          project: IDE_path_get_project(this.file),
		            paths: [IDE_path_get_file(this.file)],
		          message: commitMsg,
		            nodes: nodes
		           };
		IDE_backend_request("proj/commit", args,
			bind(this._receiveRevert,this),
			bind(this._errorReceiveRevision,this,commitMsg)
		);
	};
	IDE_backend_request("file/co", args,
	                    bind(do_commit, this),
	                    bind(this._errorReceiveRevision, this, commitMsg)
	                   );
};

//revert to selected revision. override = true to skip user confirmation
Log.prototype._revert = function(override) {
	this._find_selected();

	if (this.selectedRevision < 0) {
		//no revision selected
		status_msg("No revision selected !", LEVEL_WARN);
	} else if (override) {
		//user has confirmed revert, proceed
		status_msg("Reverting to revision: "+this.selectedRevision+"...", LEVEL_INFO);
		var b = new Browser(bind(this._do_revert, this), {'type' : 'isCommit'});
	} else {
		//user has not confirmed revert, seek confirmation
		status_button("Are you sure you want to revert selected file(s)?", LEVEL_WARN, "Yes", bind(this._revert, this, true));
	}
};

//find out which radio button is checked
Log.prototype._find_selected = function() {
	// ensure that the value doesn't cascade over.
	this.selectedRevision = -1;
	var radios = getElementsByTagAndClassName("input", "log-radio");
	for (var x = 0; x < radios.length; x++) {
		if (radios[x].checked == true) {
			this.selectedRevision = radios[x].value;
			break;
		}
	}
};

//view the diff applied by a selected revision.
Log.prototype._diff = function() {
	this._find_selected();

	if (this.selectedRevision < 0) {
		//no revision selected
		status_msg("No revision selected !", LEVEL_WARN);
	} else {
		diffpage.diff(this.file, this.selectedRevision);
	}
};

// Open the file at the selected revision.
Log.prototype._open = function() {
	this._find_selected();

	if(this.selectedRevision < 0) {
		//no revision selected
		status_msg("No revision selected !", LEVEL_WARN);
	} else {
		editpage.edit_file(this.team, this.project, this.file, this.selectedRevision);
	}
};

//tab gets focus
Log.prototype._onfocus = function() {
	showElement('log-mode');
	//don't waste time doing query again, just process results in buffer
	this._populateList();
};

//tab loses focus
Log.prototype._onblur = function() {
	hideElement('log-mode');
};
//tab is closed
Log.prototype.close = function() {
	this.tab.close();
	logDebug("Closing log tab");
};
