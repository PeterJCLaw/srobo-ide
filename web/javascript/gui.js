// Initialise to an invalid team number
team = 0; /*The current team number*/

MAX_TAB_NAME_LENGTH = 8;

// The tab bar
var tabbar = null;

// The project page
var projpage = null;

// The simulator page
var simpage = null;

// The project tab
var projtab = null;

// The edit page
var editpage = null;

// The errors tab
var errorspage = null;

// The robot log page
var robolog = null;

// The user
var user;

// The team selector
var team_selector;

// The user settings page
var settingspage = null;

// The switchboard page
var switchboardpage = null;

// The Admin page
var adminpage = null;

// The Diff page
var diffpage = null;

// The about box
var about = null;

// The initial onchange event ident connected to the tabbar
// Gets disconnected as soon as a team is selected
var tabchange_ident = null;

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
	d.addCallback( load_select_team );
	d.addErrback( function(err) {
		window.alert("Failed to get user info: " + err);
	} );
});

// 1) executed after the user's information has been acquired
function load_select_team() {
	// Got the user information -- now get team information
	team_selector = new TeamSelector();

	projpage = new ProjPage();
	connect( team_selector, "onchange", bind(projpage.set_team, projpage) );
	tabchange_ident = connect( team_selector, "onchange", load_gui );

	// Triggers the signals from the team selector
	team_selector.load();
}

// 2) Executed once we have team
function load_gui() {
	logDebug( "load_gui" );
	// We don't want this function to be called again
	disconnect( tabchange_ident );

	tabbar = new TabBar();

	// Edit page
	editpage = new EditPage();

	// About Box
	about = new AboutBox();

	//The switchboard page - this must happen before populate_shortcuts_box is called
	switchboardpage = new Switchboard();

	//The settings page - this must happen before populate_shortcuts_box is called
	settingspage = SettingsPage.GetInstance();

	//The Admin page - this must happen before populate_shortcuts_box is called
	adminpage = new Admin();

	var shortcutsList = populate_shortcuts_box();

	// Shortcut button
	var shortcuts = new dropDownBox("dropShortcuts", shortcutsList);
	var sbutton = new Tab( "v", {can_close:false,title:'See more options'} ); // TODO: find something like this
	sbutton.can_focus = false;
	connect( sbutton, "onclick", function(){shortcuts.toggleBox();} ) ;
	removeElementClass(sbutton._a ,"nofocus"); /* remove nofocus class */
	addElementClass(sbutton._a ,"shortcutButton"); /* add its own class so it can be styled individually */
	tabbar.add_tab( sbutton );

	// Projects tab
	projtab = new Tab( "Projects", {can_close:false} );
	connect( projtab, "onfocus", bind( projpage.show, projpage ) );
	connect( projtab, "onblur", bind( projpage.hide, projpage ) );
	tabbar.add_tab( projtab );

	// Simulator tab
	//simpage = new SimPage();

	// Checkout handler
	Checkout.GetInstance().init();

	// Diff Page
	diffpage = new DiffPage();

	// Errors Tab
	errorspage = new ErrorsPage();

	// Switchboard tab
	switchboardpage.init()

	robolog = new RoboLog();

	//The selection operations
	sel_operations = new ProjOps();

	tabbar.switch_to( projtab );
}

function on_doc_keydown(ev) {
	//since this call could come from EditArea we have to disregard mochikit nicities
	if(typeof ev._event == 'object')
		var e = ev._event;
	else
		var e = ev;

	var stop = false;
	if( e.altKey ) {
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
	} else if( e.ctrlKey ) {
		switch(e.keyCode) {
			case 69://E
				projpage.clickExportProject();
				stop = true;
				break;
		}
	}
	if(stop) {
		// try to prevent the browser doing something else
		kill_event(ev);
	}
}

/* contain all these in one place:
  - we've now got things that deal with raw events
  - they only work on some events
 */
function kill_event(e) {
	if(typeof e.preventDefault == 'function')
		e.preventDefault();
	if(typeof e.stopPropagation == 'function')
		e.stopPropagation();
}

function beforeunload(e) {
	if(tabbar != null && !tabbar.close_all_tabs())
		e.confirmUnload("You should close tabs before closing this window");
}

// Create drop down list TODO: make this a generic function?
function populate_shortcuts_box() {
	var shortcuts = new Array();

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

	shortcuts.push(newShortcut( "View Switchboard",
		"Messages, docs and helpful information",
		bind(switchboardpage.init, switchboardpage)
	));

	shortcuts.push(newShortcut( "About",
		"View information about the RoboIDE",
		bind(about.showBox, about)
	));

	if(user.can_admin()) {
		shortcuts.push(newShortcut( "Administration",
			"IDE Admin",
			bind(adminpage.init, adminpage)
		));
	}

	var new_ul = UL(null);
	for( var i=0; i<shortcuts.length; i++) {
		appendChildNodes(new_ul, shortcuts[i]);
	}

	return new_ul;
}

// Take id of existing hidden div to make into appearing box
function dropDownBox (id, children) {
	this._init = function(id, children) {
		this.id = $(id);
		appendChildNodes(this.id, children);
		connect( this.id, "onmouseenter", bind( this._clearTimeout, this) );	// when mouse is inside the dropbox disable timeout
		connect( this.id, "onmouseleave", bind( this.hideBox, this ) );		// when mouse leaves dropbox hide it
		connect( this.id, "onclick", bind( this.hideBox, this ) );
		this._timer = null;	// timeout for box
	}
	this.showBox = function() {	// show the box and set a timeout to make it go away
		removeElementClass( this.id, "hidden" );
		var xid = this.id;	// local to allow us to pass it inside setTimeout
		this._timer = setTimeout(function(){addElementClass(xid, "hidden");} ,1500);
	}
	this.hideBox = function() {
		addElementClass( this.id, "hidden" );
	}

	this.toggleBox = function() {
		if ( hasElementClass( this.id, "hidden" ) ) {	// is the box visible?
			this.showBox();
		} else {
			this.hideBox();
		}
	}

	this._clearTimeout = function() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
		}
	}

	this._init(id, children);
}

// Show some info about the IDE, just the version number for now
function AboutBox() {
	this._init = function() {
		this.box = $('about-box');
		connect( this.box, "onclick", bind( this.hideBox, this ) );
		this.got_info = false;
	}
	this.get_info = function() {
		if(this.got_info)
			return;
		var d = loadJSONDoc("./info");
		d.addCallback( bind( this._got_info, this ) );
	}
	this._got_info = function(nodes) {
		var dl = createDOM('dl', {id:'about-list'});
		for(var i in nodes.info) {
			var dt = createDOM('dt', null, i+':');
			var dd = createDOM('dd', null, nodes.info[i]);
			appendChildNodes(dl, dt, dd);
		}
		swapDOM('about-list', dl);
		this.got_info = true;
	}
	this.showBox = function() {
		this.get_info();
		removeElementClass( this.box, "hidden" );
		showElement($("grey-out"));
	}
	this.hideBox = function() {
		addElementClass( this.box, "hidden" );
		hideElement($("grey-out"));
	}

	this._init();
}

// The user
function User() {
	// List of team numbers
	this.teams = null;
	// Dictionary of team names (team number => name)
	this.team_names = null;
	// The user's settings
	this._settings = null;

	this._info_deferred = null;

	this.load = function() {
		// Return a deferred that fires when the data's ready
		var retd = new Deferred();

		this._request_info();

		this._info_deferred = retd;
		return this._info_deferred;
	}

	this._request_info = function() {
		IDE_backend_request("user/info", {}, bind(this._got_info, this), bind(function() {
			status_button( "Failed to load user information", LEVEL_ERROR,
			               "retry", bind( this._request_info, this ) ) }, this));
	}

	this._got_info = function( info ) {
		logDebug( "Got user information" );

		this.team_names = {};
		this.teams = [];
		for( var i = 0; i < info["teams"].length; i++ ) {
			var num = info["teams"][i];
			this.team_names[num] = "Team " + num;
			this.teams.push(parseInt(num, 10));
		}

		this._settings = (info.settings instanceof Array) ? {} : info.settings;
		for( var k in this._settings ) {
			logDebug( k + " = " + this._settings[k] );
		}

		if(info["is-admin"]) {
			this.can_admin = function() { return true;};
		}

		// Connect up the logout button
		disconnectAll( "logout-button" );
		connect( "logout-button", "onclick", bind( this._logout_click, this ) );

 		this._info_deferred.callback(null);
	}

	this.get_setting = function(sname) {
		return this._settings[sname];
	}

	// Set user settings.
	this.set_settings = function(settings, opts) {
		var changed = false;
		log('Setting user settings');
		for( var s in settings ) {
			if (this._settings[s] !== settings[s] || changed) {
				changed = true;
			}
			this._settings[s] = settings[s];
		}
		if (changed) {
			this._save_settings(opts);
		} else if(opts == 'loud') {
			status_msg( 'User settings unchanged', LEVEL_INFO );
		}
	}

	// Save user settings. Called right after they're set.
	this._save_settings = function(opts) {
		log('Saving user settings');
		if(opts == 'loud') {
			var cb = partial( status_msg, 'User settings saved', LEVEL_OK );
			var eb = partial( status_button, 'Could not save user settings', LEVEL_ERROR, 'retry', bind(this._save_settings, this, opts) );
		} else {
			var cb = eb = function(){};
		}

		IDE_backend_request('user/settings-put', {settings: this._settings}, cb, eb);
	}

	this._logout_click = function(ev) {
		if( ev != null ) {
			ev.preventDefault();
			ev.stopPropagation();
		}

		IDE_backend_request("auth/deauthenticate", {},
		                    bind(window.location.reload, window.location),
		                    bind(function() {
		                                     status_button( "Failed to log out", LEVEL_ERROR, "retry",
		                                                    bind( this._logout_click, this, null )
		                                                  );
		                                    },
                            this));
	}

	// do they have admin priviledges - this gets overwirtten by the info collecter if they do
	this.can_admin = function() {
		return false;
	}

	// Do the login if they press enter in the password box
	this._pwd_on_keypress = function(ev) {
		if ( ev.key()["string"] == "KEY_ENTER" )
			this._do_login( null );
	}

};

function TeamSelector() {
	this._prompt = null;

	this.load = function() {
		var teambox = [];

		if( user.teams.length == 1 )
			team = user.teams[0];
		else
		{
			var olist = [];

			if( !this._team_exists(team) ) {
				// Work out what team we should be in
				var team_load = user.get_setting('team.autoload');
				var team_to_load = user.get_setting(team_load);
				if( team_to_load != undefined
				    && this._team_exists( team_to_load ) ) {
					team = team_to_load;
					logDebug( "Defaulting to team " + team );
				}
			}

			olist = this._build_options();

			if( !this._team_exists(team) ) {
				// Add a "please select a team" option
				olist.unshift( OPTION( { "id" : "teamlist-tmpitem",
							 "selected" : "selected" },
						       "Please select a team." ) );

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

		replaceChildNodes( $("teaminfo"), teambox );
		this._update_name();

		if( this._team_exists(team) )
			signal( this, "onchange", team );
	}

	this._build_options = function() {
		var olist = [];

		for( var t in user.teams ) {
			var props = { "value" : user.teams[t]};

			if( user.teams[t] == team )
				props["selected"] = "selected";

			olist.push( OPTION(props, user.teams[t]) );
		}

		return olist;
	}

	// Returns true if the given team number exists for this user
	this._team_exists = function(team) {
		if( team == 0 )
			return false;

		for( var i in user.teams )
			if( user.teams[i] == team )
				return true;
		return false;
	}

	this._selected = function(ev) {
		if( this._prompt != null ) {
			this._prompt.close();
			this._prompt = null;
		}

		var src = ev.src();

		//if it's not changed (webkit does weirdness)
		if(src.value == team)
			return;

		//close tabs from other teams before changing
		log('Team changed - closing all tabs');
		if(tabbar != null && !tabbar.close_all_tabs()) {
			src.value = team;
			alert('Open files must be closed before changing teams');
			return;
		}

		// Remove the "please select a team" item from the list
		var tmpitem = $("teamlist-tmpitem");
		if( tmpitem != null && src != tmpitem )
			removeElement( tmpitem );

		team = parseInt(src.value, 10);
		logDebug( "team changed to " + team );
		this._update_name();

		signal( this, "onchange", team );
	}

	this._update_name = function() {
		var name = "";
		if( this._team_exists(team) ) {
			name = user.team_names[ team ];

			if( user.teams.length == 1 )
				name = "Team " + team + ": " + name
		}

		replaceChildNodes( $("teamname"), " " + name );
	}
}


// Count of outstanding asynchronous requests
var async_count = 0;

function postJSONDoc( url, qa ) {
	alert("did a postJSONDoc: " + url);
	async_count += 1;
	showElement( $("rotating-box") );

	var d = new Deferred();
	var r;

	if( qa == undefined ) {	//it shouldn't ever be, but if that's what they want...
		r = doXHR( url );
	} else {	//throw in some defaults, then run the call
		qa.method = 'post';
		qa.headers = {"Content-Type":"application/x-www-form-urlencoded"};
		qa.sendContent = queryString(qa.sendContent);
		r = doXHR( url, qa );
	}

	r.addCallback( partial( load_postedJSONDoc, d, 0 ) );
	r.addErrback( partial( load_postedJSONDoc, d, 1 ) );

	return d;
}

function load_postedJSONDoc( d, fail, XHR ) {
	var res = evalJSONRequest(XHR);
	if( fail == 0 )	//success
		ide_json_cb( d, res );
	else
		ide_json_err( d, res );
}

function loadJSONDoc( url, qa ) {
	alert("did a loadJSONDoc: " + url);
	async_count += 1;
	showElement( $("rotating-box") );

	var d = new Deferred();
	var r;

	if( qa == undefined )
		r = MochiKit.Async.loadJSONDoc( url );
	else
		r = MochiKit.Async.loadJSONDoc( url, qa );

	r.addCallback( partial( ide_json_cb, d ) );
	r.addErrback( partial( ide_json_err, d ) );

	return d;
}

function ide_json_cb( d, res ) {
	async_count -= 1;

	if( async_count == 0 )
		hideElement( $("rotating-box") );

	d.callback(res);
}

function ide_json_err( def, res ) {
	async_count -= 1;

	if( async_count == 0 )
		hideElement( $("rotating-box") );

	def.errback(res);
}
