// Initialise to an invalid team number

team = null; /* The current team id */

MAX_TAB_NAME_LENGTH = 8;

// The tab bar
var tabbar = null;

// The project page
var projpage = null;

// The edit page
var editpage = null;

// The errors tab
var errorspage = null;

// The user
var user;

// The team selector
var team_selector;

// The user settings page
var settingspage = null;

// The Team Status page
var teamstatuspage = null;

// The Admin page
var adminpage = null;

// The Search page
var searchpage = null;

// The Diff page
var diffpage = null;

// The about box
var about = null;

function logErrorNice(err) {
	if (typeof(err) != 'string') {
		var locLen = window.location.href.length;
		var fn = err.fileName.substr(locLen);
		err = err + " : " + fn + "(" + err.lineNumber + "," + err.columnNumber + ")";
	}
	logError(err);
}

// onload function
addLoadEvent( function() {
	//On page load - this replaces a onload action of the body tag
	//Hook up the save file button
	connect(window, 'onbeforeunload', beforeunload);

	//hook up the keyboard shortcuts handler
	connect(document, 'onkeydown', on_doc_keydown);

	user = new User();
	var d = user.load();
	// Wait for the user information to come back
	d.addCallback( load_gui );
	d.addErrback( function(err) {
		logErrorNice(err);
		window.alert("Failed to get user info: " + err);
	} );
	load_gui_initial();
});

function validate_team() {
	if ( team == null || team == 0 ) {
		this._prompt = status_msg("You must select a team", LEVEL_ERROR);
		return false;
	}
	return true;
}

// 1) Load the initial UI elements - those that don't need a team to get going
function load_gui_initial() {
	logDebug( "load_gui_initial" );

	// Team selector
	team_selector = new TeamSelector();

	// Main tab well
	tabbar = new TabBar();

	// Projects page
	projpage = new ProjPage(team_selector);

	// Edit page
	editpage = new EditPage();

	// About Box
	about = new AboutBox();

	//The Team Status page - this must happen before populate_shortcuts_box is called
	teamstatuspage = new TeamStatus();

	//The settings page - this must happen before populate_shortcuts_box is called
	settingspage = SettingsPage.GetInstance();

	//The Admin page - this must happen before populate_shortcuts_box is called
	adminpage = new Admin();

	//The Search page - this must happen before populate_shortcuts_box is called
	searchpage = SearchPage.GetInstance();
}

// 2) Executed once we have user details
function load_gui() {
	logDebug( "load_gui" );

	var shortcutsList = populate_shortcuts_box();

	// Shortcut button
	var shortcuts = new dropDownBox("dropShortcuts", shortcutsList);
	var sbutton = new Tab( "v", {can_close:false,title:'See more options'} ); // TODO: find something like this
	sbutton.can_focus = false;
	connect( sbutton, "onclick", function(){shortcuts.toggleBox();} ) ;
	removeElementClass(sbutton._a ,"nofocus"); /* remove nofocus class */
	addElementClass(sbutton._a ,"shortcutButton"); /* add its own class so it can be styled individually */
	tabbar.add_tab( sbutton );

	// Now that the UI is ready, and we have all the info needed so far,
	// show the user the team selector.
	// Since this could load the project page (via a default team), it
	// needs to be after we add the shortcuts box to the tabbar.
	team_selector.load();

	// Diff Page
	diffpage = new DiffPage();

	// Errors Tab
	errorspage = new ErrorsPage();
}

function on_doc_keydown(ev) {
	//since this call could come from EditArea we have to disregard mochikit nicities
	var e;
	if (typeof ev._event == 'object') {
		e = ev._event;
	} else {
		e = ev;
	}

	var stop = false;
	if (e.altKey) {
		switch(e.keyCode) {
			case 33://PageUp
				tabbar.prev_tab();
				stop = true;
				break;
			case 34://PageDown
				tabbar.next_tab();
				stop = true;
				break;
		}
	} else if (e.ctrlKey || e.metaKey) {
		switch(e.keyCode) {
			case 69://E
				projpage.clickExportProject();
				stop = true;
				break;
		}
	}
	if (stop) {
		// try to prevent the browser doing something else
		kill_event(ev);
	}
}

/* contain all these in one place:
  - we've now got things that deal with raw events
  - they only work on some events
 */
function kill_event(e) {
	if (typeof e.preventDefault == 'function') {
		e.preventDefault();
	}
	if (typeof e.stopPropagation == 'function') {
		e.stopPropagation();
	}
}

function beforeunload(e) {
	if (tabbar != null && !tabbar.close_all_tabs()) {
		e.confirmUnload("You should close tabs before closing this window");
	}
}

// Create drop down list TODO: make this a generic function?
function populate_shortcuts_box() {
	var shortcuts = [];

	function newShortcut(name, description, callback) {
		var a = A( {"title": description}, name );
		var li = LI(null, a);
		connect( li, "onclick", callback );
		return li;
	}

	shortcuts.push(newShortcut( "Create new file",
		"Create a new file",
		bind(editpage.new_file, editpage)
	));

	shortcuts.push(newShortcut( "User settings",
		"Change user settings",
		bind(settingspage.init, settingspage)
	));

	shortcuts.push(newShortcut( "Search",
		"Search for things within the IDE",
		bind(searchpage.init, searchpage)
	));

	if (user.show_team_status) {
		shortcuts.push(newShortcut( "Team Status",
			"Information about your team",
			bind(teamstatuspage.init, teamstatuspage)
		));
	}

	shortcuts.push(newShortcut( "About",
		"View information about the RoboIDE",
		bind(about.showBox, about)
	));

	if (user.can_admin()) {
		shortcuts.push(newShortcut( "Administration",
			"IDE Admin",
			bind(adminpage.init, adminpage)
		));
	}

	var new_ul = UL(null);
	for (var i = 0; i<shortcuts.length; i++) {
		appendChildNodes(new_ul, shortcuts[i]);
	}

	return new_ul;
}

// Take id of existing hidden div to make into appearing box
function dropDownBox (id, children) {
	this._init = function(id, children) {
		this.id = getElement(id);
		appendChildNodes(this.id, children);
		connect( this.id, "onmouseenter", bind( this._clearTimeout, this) );	// when mouse is inside the dropbox disable timeout
		connect( this.id, "onmouseleave", bind( this.hideBox, this ) );		// when mouse leaves dropbox hide it
		connect( this.id, "onclick", bind( this.hideBox, this ) );
		this._timer = null;	// timeout for box
	};
	this.showBox = function() {	// show the box and set a timeout to make it go away
		removeElementClass( this.id, "hidden" );
		var xid = this.id;	// local to allow us to pass it inside setTimeout
		this._timer = setTimeout(function(){addElementClass(xid, "hidden");} ,1500);
	};
	this.hideBox = function() {
		addElementClass(this.id, "hidden");
	};

	this.toggleBox = function() {
		if (hasElementClass(this.id, "hidden")) {	// is the box visible?
			this.showBox();
		} else {
			this.hideBox();
		}
	};

	this._clearTimeout = function() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
		}
	};

	this._init(id, children);
}

// Show some info about the IDE, just the version number for now
function AboutBox() {
	this._init = function() {
		this.box = getElement('about-box');
		connect( this.box, "onclick", bind( this.hideBox, this ) );
		this.got_info = false;
	};
	this.get_info = function() {
		if (this.got_info) {
			return;
		}
		IDE_backend_request('info/about', {}, bind(this._got_info, this), function() {});
	};
	this._got_info = function(nodes) {
		var dl = createDOM('dl', {id:'about-list'});
		for (var i in nodes.info) {
			var dt = createDOM('dt', null, i+':');
			var dd = createDOM('dd', null, nodes.info[i]);
			appendChildNodes(dl, dt, dd);
		}
		swapDOM('about-list', dl);
		this.got_info = true;
	};
	this.showBox = function() {
		this.get_info();
		removeElementClass( this.box, "hidden" );
		showElement("grey-out");
	};
	this.hideBox = function() {
		addElementClass( this.box, "hidden" );
		hideElement("grey-out");
	};

	this._init();
}

// The user
function User() {
	// List of team numbers
	this.teams = null;

	this.show_team_status = false;

	// The user's settings
	this._settings = null;

	this._info_deferred = null;

	this.load = function() {
		// Return a deferred that fires when the data's ready
		var retd = new Deferred();

		this._request_info();

		this._info_deferred = retd;
		return this._info_deferred;
	};

	this._request_info = function() {
		IDE_backend_request("user/info", {}, bind(this._got_info, this),
			bind(function() {
				status_button( "Failed to load user information", LEVEL_ERROR,
				               "retry", bind(this._request_info, this) );
			}, this));
	};

	this._got_info = function(info) {
		logDebug( "Got user information" );

		this.teams = info.teams;

		this._settings = (info.settings instanceof Array) ? {} : info.settings;
		for (var k in this._settings) {
			logDebug(k + " = " + this._settings[k]);
		}

		if (info["is-admin"]) {
			this.can_admin = function() { return true; };
		}

		if (info["show-team-status"]) {
			this.show_team_status = true;
		}

		// Connect up the logout button
		disconnectAll( "logout-button" );
		connect( "logout-button", "onclick", bind(this._logout_click, this) );

		this._info_deferred.callback(null);
	};

	this.get_setting = function(sname) {
		var value = this.get_raw_setting(sname);
		if (value != null) {
			return value;
		}
		var setting = SettingsPage.Settings[sname];
		if (setting != null) {
			value = setting.options['default'];
		}
		return value;
	};

	this.get_raw_setting = function(sname) {
		return this._settings[sname];
	};

	this.get_team = function(teamId) {
		if (teamId == null) {
			return null;
		}

		var teams = user.teams;
		for (var i=0; i < teams.length; i++) {
			var teamInfo = teams[i];
			if (teamInfo.id == teamId) {
				return teamInfo;
			}
		}
		return null;
	};

	// Set user settings.
	this.set_settings = function(settings, opts) {
		var changed = false;
		log('Setting user settings');
		for (var s in settings) {
			if (this._settings[s] !== settings[s] || changed) {
				changed = true;
			}
			this._settings[s] = settings[s];
		}
		if (changed) {
			this._save_settings(opts);
		} else if (opts == 'loud') {
			status_msg( 'User settings unchanged', LEVEL_INFO );
		}
	};

	// Save user settings. Called right after they're set.
	this._save_settings = function(opts) {
		log('Saving user settings');
		var cb, eb;
		if (opts == 'loud') {
			cb = partial( status_msg, 'User settings saved', LEVEL_OK );
			eb = partial( status_button, 'Could not save user settings', LEVEL_ERROR, 'retry', bind(this._save_settings, this, opts) );
		} else {
			cb = eb = function(){};
		}

		IDE_backend_request('user/settings-put', {settings: this._settings}, cb, eb);
	};

	this._logout_success = function(nodes) {
		window.location.reload();
	};

	this._logout_error = function(nodes) {
		status_button("Failed to log out", LEVEL_ERROR, "retry",
		              bind( this._logout_click, this ));
	};

	this._logout_click = function(ev) {
		if (ev != null) {
			ev.preventDefault();
			ev.stopPropagation();
		}

		IDE_backend_request("auth/deauthenticate", {},
		                    bind(this._logout_success, this),
		                    bind(this._logout_error, this)
		                   );
	};

	// do they have admin priviledges - this gets overwirtten by the info collecter if they do
	this.can_admin = function() {
		return false;
	};
}

function TeamSelector() {
	this._prompt = null;

	this.load = function() {
		var teambox = [];

		if (user.teams.length == 1) {
			team = user.teams[0].id;
		} else if (user.teams.length == 0) {
			replaceChildNodes( "teaminfo", SPAN("You are not in any teams!") );
			return;
		} else {
			var olist = [];

			if (!this._team_exists(team)) {
				// Work out what team we should be in
				var team_load = user.get_setting('team.autoload');
				var team_to_load = user.get_setting(team_load);
				if (team_to_load != undefined &&
				    this._team_exists( team_to_load )) {
					team = team_to_load;
					logDebug( "Defaulting to team " + team );
				}
			}

			olist = this._build_options();

			if (!this._team_exists(team)) {
				// Add a "please select a team" option
				olist.unshift( OPTION( { id: 'teamlist-tmpitem',
				                   selected: 'selected' },
				                     "Please select a team." )
				             );

				this._prompt = status_msg( "Please select a team", LEVEL_INFO );
			}

			var tsel = SELECT( null, olist );

			connect( tsel, "onchange", bind( this._selected, this ) );
			connect( this, "onchange", function(t) { user.set_settings({'team.last':t}); } );
			teambox.push( "Team: " );
			teambox.push( tsel );
		}

		// Span to hold the team name
		var tname = SPAN( { "id" : "teamname" }, null );
		teambox.push( tname );

		replaceChildNodes( "teaminfo", teambox );
		this._update_name();

		if (this._team_exists(team)){
			signal(this, "onchange", team);
		}
	};

	var team_sort = function(a, b) {
		if (a.id == b.id) {
			return 0;
		} else if (a.id < b.id) {
			return -1;
		} else {
			return 1;
		}
	};

	this._build_options = function() {
		var olist = [];

		user.teams.sort(team_sort);

		for (var i = 0; i < user.teams.length; i++) {
			var team_id = user.teams[i].id;
			var props = { "value" : team_id };

			if (team_id == team) {
				props.selected = "selected";
			}

			olist.push( OPTION(props, team_id) );
		}

		return olist;
	};

	// Returns true if the given team number exists for this user
	this._team_exists = function(teamId) {
		return user.get_team(teamId) != null;
	};

	this._selected = function(ev) {
		if (this._prompt != null) {
			this._prompt.close();
			this._prompt = null;
		}

		var src = ev.src();

		//if it's not changed (webkit does weirdness)
		if (src.value == team) {
			return;
		}

		//close tabs from other teams before changing
		log('Team changed - closing all tabs');
		if (tabbar != null && !tabbar.close_all_tabs()) {
			src.value = team;
			alert('Open files must be closed before changing teams');
			return;
		}

		// Remove the "please select a team" item from the list
		var tmpitem = getElement("teamlist-tmpitem");
		if (tmpitem != null && src != tmpitem) {
			removeElement(tmpitem);
		}

		team = src.value;
		logDebug("team changed to " + team);
		this._update_name();

		signal(this, "onchange", team);
	};

	this._update_name = function() {
		var name = "";
		var teamInfo = user.get_team(team);
		if (teamInfo != null) {
			name = teamInfo.name;

			if (user.teams.length == 1) {
				var chosen_name = name;
				name = "Team " + team;
				if (!IDE_string_empty(chosen_name)) {
					name += ": " + chosen_name;
				}
			}
		}

		replaceChildNodes( "teamname", " " + name );
	};
}

function setReadOnly(elem, isReadOnly)
{
	if (isReadOnly) {
		addElementClass(elem, 'read-only');
	} else {
		removeElementClass(elem, 'read-only');
	}
}
