function Admin() {
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;
}

/* *****	Initialization code	***** */
Admin.prototype.init = function() {
	if(!user.can_admin()) {
		status_msg('You have not been granted IDE Admin privileges', LEVEL_WARN);
		return;
	}
	if(!this._inited) {
		logDebug("Admin: Initializing");

		/* Initialize a new tab for Admin - Do this only once */
		this.tab = new Tab( "Administration" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		tabbar.add_tab( this.tab );

		/* Initialise indiviual page elements */
		this.ShowTeams();
		this.GetBlogFeeds();

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}
/* *****	End Initialization Code 	***** */

/* *****	Tab events: onfocus, onblur and close	***** */
Admin.prototype._onfocus = function() {
	setStyle($("admin-page"), {'display':'block'});
}

Admin.prototype._onblur = function() {
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	/* hide Admin page */
	setStyle($("admin-page"), {'display':'none'});
}

Admin.prototype._close = function() {
	/* Disconnect all signals */
	for(var i = 0; i < this._signals.length; i++) {
		disconnect(this._signals[i]);
	}
	this._signals = new Array();

	/* Close tab */
	this._onblur();
	this.tab.close();
	this._inited = false;
}
/* *****	End Tab events	***** */

/* *****	Team editing Code	***** */
Admin.prototype.ShowTeams = function() {
	var i = 0;
	var td_id = TH(null, 'Team ID');
	var td_name = TH({'class':'name'}, 'Team Name');
	var td_button = TH(null);
	var oddeven = i++ % 2 == 0 ? 'even' : 'odd';
	replaceChildNodes('admin-teams-table');
	appendChildNodes('admin-teams-table', TR({'class':oddeven}, td_id, td_name, td_button));

	for( var id in user.team_names ) {
		td_id = TD(null, id);
		td_name = TD({'class':'name'}, user.team_names[id]);
		var button = BUTTON(null, 'Edit');
		this._signals.push(connect(button, 'onclick', bind(this._editTeam, this, {id:id,name:user.team_names[id]})));
		td_button = TD(null, button);
		oddeven = i++ % 2 == 0 ? 'even' : 'odd';
		appendChildNodes('admin-teams-table',
			TR({'class':oddeven,id:'admin-teams-table-'+id}, td_id, td_name, td_button)
		);
	}
}

Admin.prototype._editTeam = function(ref) {
	var row = $('admin-teams-table-'+ref.id);
	var cell = getFirstElementByTagAndClassName('td', 'name', row);
	var button = getFirstElementByTagAndClassName('button', null, row);
	if(button.innerHTML == 'Edit') {
		var input = INPUT({value:cell.innerHTML});
		replaceChildNodes(cell, input);
		button.innerHTML = 'Save';
	} else {	//save it
		var d = loadJSONDoc("./admin/teamname", {
				'id':ref.id,
				'name':cell.firstChild.value
			});

		cell.firstChild.disabled = true;
		button.disabled = true;

		d.addCallback( bind( this._receiveEditTeam, this, ref) );
		d.addErrback( bind( this._errorEditTeam, this, ref) );
	}
}
Admin.prototype._receiveEditTeam = function(ref, nodes) {
	var row = $('admin-teams-table-'+ref.id);
	if(nodes.success) {
		this._prompt = status_msg("Team name updated", LEVEL_OK);
		var cell = getFirstElementByTagAndClassName('td', 'name', row);
		var button = getFirstElementByTagAndClassName('button', null, row);
		cell.innerHTML = nodes.name;
		button.innerHTML = 'Edit';
		button.disabled = false;
		user.team_names[nodes.id] = nodes.name;
		if(nodes.id == team) {
			$('teamname').innerHTML = nodes.name;
		}
	} else {
		this._errorEditTeam(ref, nodes);
	}
}
Admin.prototype._errorEditTeam = function(ref, nodes) {
	this._prompt = status_msg("Failed to update team name", LEVEL_ERROR);
	var row = $('admin-teams-table-'+ref.id);
	var cell = getFirstElementByTagAndClassName('td', 'name', row);
	var button = getFirstElementByTagAndClassName('button', null, row);
	cell.firstChild.disabled = false;
	button.disabled = false;
}
/* *****	End Team editing Code 	***** */

/* *****	Student blog feed listing code	***** */
Admin.prototype._receiveGetBlogFeeds = function(nodes) {
	var td_user = TH({'class':'user'}, 'User ID');
	var td_url = TH({'class':'url'}, 'URL');
	var td_status = TH({'class':'status'}, 'Status');
	replaceChildNodes('admin-feeds-table');
	appendChildNodes('admin-feeds-table', TR({'class':'even'}, td_user, td_url, td_status));

	var make_selectbox = function(properties, options, def) {
		var s = SELECT(properties);
		for( value in options ) {
			var opt = OPTION({value:value}, options[value]);
			if(value == def)
				opt.selected = true;
			appendChildNodes(s, opt);
		}
		return s;
	};

	var options = {unchecked:'Unchecked',valid:'Valid',invalid:'Invalid'};
	//iterate over all feeds and append them to the table
	for( var i=0; i<nodes.feeds.length; i++ ) {
		var feed = nodes.feeds[i];
		td_user = TD({'class':'user'}, feed.user);
		td_url = TD({'class':'url'}, A({href:feed.url},feed.url));

		var status = (feed.checked ? (feed.valid ? 'valid' : 'invalid') : 'unchecked');
		var selectbox = make_selectbox({id:'admin-feeds-'+feed.id}, options, status);
		var ref = {id:feed.id, url:feed.url, status:status, selectbox:selectbox};
		this._signals.push(connect(selectbox, 'onchange', bind(this.setBlogStatus, this, ref)));
		td_status = TD({'class':'status'}, selectbox);

		var oddeven = i % 2 == 0 ? 'odd' : 'even';
		appendChildNodes('admin-feeds-table', TR({'class':oddeven}, td_user, td_url, td_status));
		this.showBlogStatus(ref);
	}
}
Admin.prototype._errorGetBlogFeeds = function() {
		this._prompt = status_msg("Unable to load blog feeds", LEVEL_ERROR);
		log("Admin: Failed to retrieve feed blog urls");
		return;
}
Admin.prototype.GetBlogFeeds = function() {
	log("Admin: Retrieving blog feeds");
	var d = loadJSONDoc("./admin/listblogfeeds", {});

	d.addCallback( bind(this._receiveGetBlogFeeds, this) );
	d.addErrback( bind(this._errorGetBlogFeeds, this) );
}
/* *****    End Student blog feed listing code	***** */

/* *****	RSS feed validation code	***** */
Admin.prototype._receiveBlogStatus = function(ref, nodes) {
	if(nodes.success > 0 ) {
		this._prompt = status_msg("Blog feed updated", LEVEL_OK);
	} else {
		this._errorBlogStatus(ref, nodes);
	}
}
Admin.prototype._errorBlogStatus = function(ref, nodes) {
	this._prompt = status_msg("Unable to update blog feed", LEVEL_ERROR);
	$('admin-feeds-'+ref.id).value = ref.status;
	this.showBlogStatus(ref);
}
Admin.prototype.setBlogStatus = function(ref) {
	log("Admin: Setting blog feed status");
	var status = $('admin-feeds-'+ref.id).value;
	var d = loadJSONDoc("./admin/setfeedstatus", {
			id:ref.id,
			url:ref.url,
			status:status
		});
	d.addCallback( bind( this._receiveBlogStatus, this, ref) );
	d.addErrback( bind( this._errorBlogStatus, this, ref) );

	ref.status = status;
	this.showBlogStatus(ref);
}
Admin.prototype.showBlogStatus = function(ref) {
	log("Admin: Showing blog feed status");
	var tr = getFirstParentByTagAndClassName('admin-feeds-'+ref.id, 'tr', null);
	removeElementClass(tr, 'unchecked-feed-url');
	removeElementClass(tr, 'valid-feed-url');
	removeElementClass(tr, 'invalid-feed-url');
	addElementClass(tr, ref.status+'-feed-url');
}
/* *****	End RSS feed validation code	***** */
