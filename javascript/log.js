// vim: noexpandtab
function Log(file, project) {
	//class properties
	this.tab = null;		//holds reference to tab in tabbar
	this.selectedRevision = -1;	//Selected revision, -1 indicates no revision set
	this.team = team;		//team number TODO: get this from elsewhere
	this.project = project;    //holds the current project name
	this.user = null;		//holds author name
	this.userList = new Array();	//List of  users attributed to file(s)
	this.history = new Array();	//array of log entries returned by server
	this.file = file;		//the file/directory for which we are interested
	this.offset = 0;		//which results page we want to retrieve from the server
	this.overflow = 0;		//stores  total number of results pages (retrieved from server)

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
	this.history = new Array();
	this.userList = new Array();
	//do new query
	this._retrieveHistory();
}

Log.prototype._receiveHistory = function(opt, revisions) {
	logDebug("Log history received ok");
	//extract data from query response
	update(this.history, revisions.history);
	update(this.userList, revisions.authors);
	this.overflow = revisions.overflow;
	if(opt == null || opt != 'quiet')
		status_msg("File history loaded successfully", LEVEL_OK);
	//present data
	this._populateList();
}

Log.prototype._errorReceiveHistory = function() {
	//handle failed request
	logDebug("Log history retrieval failed");
	this.history = new Array();
	status_button("Error retrieving history", LEVEL_WARN, "Retry", bind(this._receiveHistory, this));
}

Log.prototype._retrieveHistory = function(opt) {
	var d = loadJSONDoc("./gethistory", { team : team,
						  file : this.file,
						  user : this.user,
						  offset : this.offset});

	d.addCallback( bind(this._receiveHistory, this, opt));
	d.addErrback( bind(this._errorReceiveHistory, this));
}
//processess log data and formats into list. connects up related event handlers,
//deals with multile results pages
Log.prototype._populateList = function() {
	logDebug("Processing log list...");

	//print summary information
	var entries = this.history.length;
	if(entries <= 0) {
		$("log-summary").innerHTML = "There are no revisions availble for file(s): "+this.path;
	} else {
		$("log-summary").innerHTML = "Displaying "+entries+ " revision(s) between "+this.history[this.history.length-1].date+" & "+this.history[0].date+" Page "+(this.offset+1)+" of "+(this.overflow);
	}

	//fill drop down box with authors attributed to file(s)
	//clear list:
	replaceChildNodes('repo-users', opt);

	//first item in list is: 'Filter by user'
	var opt = OPTION({"value":-1}, "Filter by user");
	appendChildNodes('repo-users', opt);

	//second item in the list is: 'all' meaning, show logs from all users
	var opt = OPTION({"value":-1}, "Show all");
	appendChildNodes('repo-users', opt);

	//now add all attributed authors
	for(var i=0; i < this.userList.length; i++) {
		var opt = OPTION({"value":i}, this.userList[i]);
		appendChildNodes('repo-users', opt);
	}
    //remove event handler for when user applies filter to results
	disconnectAll('repo-users');


	//clear log list
	replaceChildNodes($("log-list"), null);
	//now populate log list
	for(var x=0; x <this.history.length; x++) {
		var logtxt = SPAN("r"+this.history[x].rev+" | "+this.history[x].author+" | "+this.history[x].date);
		var radio = INPUT({'type' : 'radio', 'id' : 'log', 'name' : 'log', 'class' : 'log-radio', 'value' : this.history[x].rev });
		var label = LABEL( null, radio, logtxt );
		var commitMsg = DIV({'class' : 'commit-msg'}, this.history[x].message);
		appendChildNodes($("log-list"), LI(null, label, commitMsg));
	}
	//make selected user selected in drop down box (visual clue that filter is applied)
	if(this.user != null) {
		$('repo-users').value = findValue(this.userList, this.user);
	}

	//connect event handler for when user applies filter to results
	connect('repo-users', 'onchange', bind(this._update, this));

	//disconnect the older/newer buttons
	disconnectAll($("older"));
	disconnectAll($("newer"));

	//if older results are available, enable the 'older' button and hook it up
	if(this.offset < (this.overflow-1)) {
		$("older").disabled = false;
		connect($("older"), 'onclick', bind(this._nextview, this, +1));
	} else
		$("older").disabled = true;

	//if newer results are available, enable the 'newer' button and hook it up
	if(this.offset > 0) {
		$("newer").disabled = false;
		connect($("newer"), 'onclick', bind(this._nextview, this, -1));
	} else
		$("newer").disabled = true;

	//connect up the 'Revert' button to event handler
	disconnectAll($("revert"));
	connect($("revert"), 'onclick', bind(this._revert, this, false));

	//connect up the 'Diff' button to event handler
	disconnectAll('log-diff');
	connect('log-diff', 'onclick', bind(this._diff, this, false));

	//connect up the close button on log menu
	disconnectAll($("log-close"));
	connect($("log-close"), 'onclick', bind(this.close, this));
}
//get older (updown > 0) or newer (updown < 0) results
Log.prototype._nextview = function(updown) {
	this.offset = this.offset+updown;
	//get new results page
	this._init();
}
//called when user applies author filter
Log.prototype._update = function() {
	//find out which author was selected  using select value as key to userList array
	var index = $('repo-users').value;
	//if user clicks 'All' (-1) clear user variable
	if(index > -1) {
		this.user = this.userList[index];
	} else {
		this.user = null;
	}
	logDebug("Filter logs by user: "+this.user);
	//reset offset
	this.offset = 0;
	this._init();
}

Log.prototype._receiveRevert = function(nodes) {
	if(nodes.new_revision > 0)
		status_msg("Successfully reverted to version "+this.selectedRevision+" (New Revision: "+nodes.new_revision+")", LEVEL_OK);
	else
		status_msg("Failed to revert: "+nodes.success, LEVEL_ERROR);
	//in either case update the history
	this._retrieveHistory('quiet');
}

Log.prototype._errorReceiveRevision = function(commitMsg) {
	status_button("Unable to contact server to revert file", LEVEL_ERROR, "retry", bind(this._do_revert, this, commitMsg));
}
Log.prototype._do_revert = function(commitMsg) {
	var d = loadJSONDoc("./revert", {
					team : team,
					files : this.file,
					torev : this.selectedRevision,
					message : commitMsg});

	d.addCallback( bind(this._receiveRevert, this));
	d.addErrback( bind(this._errorReceiveRevision, this, commitMsg));
}

//revert to selected revision. override = true to skip user confirmation
Log.prototype._revert = function(override) {
	this._find_selected();

	if(this.selectedRevision < 0) {
		//no revision selected
		status_msg("No revision selected !", LEVEL_WARN);
	} else if(override) {
		//user has confirmed revert, proceed
		status_msg("Reverting to revision: "+this.selectedRevision+"...", LEVEL_INFO);
		var b = new Browser(bind(this._do_revert, this), {'type' : 'isCommit'});
	} else {
		//user has not confirmed revert, seek confirmation
		status_button("Are you sure you want to revert selected file(s)?", LEVEL_WARN, "Yes", bind(this._revert, this, true));
	}

}

//find out which radio button is checked
Log.prototype._find_selected = function() {
	var radios = getElementsByTagAndClassName("input", "log-radio");
	for(var x=0; x < radios.length; x++) {
		if(radios[x].checked == true) {
			this.selectedRevision = radios[x].value;
			break;
		}
	}
}

//view the diff applied by a selected revision.
Log.prototype._diff = function() {
	this._find_selected();

	if(this.selectedRevision < 0) {
		//no revision selected
		status_msg("No revision selected !", LEVEL_WARN);
	} else {
		diffpage.diff(this.file, this.selectedRevision);
	}
}

//tab gets focus
Log.prototype._onfocus = function() {
	if(getStyle($("log-mode"), "display") != "block") {
		setStyle($("log-mode"), {"display" : "block"});
	}
	//don't waste time doing query again, just process results in buffer
	this._populateList();
}

//tab loses focus
Log.prototype._onblur = function() {
	setStyle($("log-mode"), {"display" : "none"});
}
//tab is closed
Log.prototype.close = function() {
	this.tab.close();
	delete this;	//free memory
	logDebug("Closing log tab");
}
