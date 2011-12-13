function Admin() {
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	// Id for the table that contains the items to review
	this._tableId = 'admin-page-review';
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
		this.GetTeamsToReview();

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

/* *****	Teams with stuff to review listing code	***** */
Admin.prototype._receiveGetTeamsToReview = function(nodes) {

	replaceChildNodes(this._tableId);

	var selectId = 'admin-page-team-select';

	if (nodes.teams.length == 0) {
		this._prompt = status_msg("There are no teams to review.", LEVEL_OK);
		swapDOM(selectId, SPAN({id: selectId}, 'None'));
		return;
	} else if (nodes.teams.length == 1) {
		var team = nodes.teams[0];
		this.GetItemsToReview(team);
		swapDOM(selectId, SPAN({id: selectId}, team));
		return;
	}

	this._prompt = status_msg("Please select a team to review.", LEVEL_OK);

	var pleaseSelect = OPTION({value: -1}, "Please select..");
	var s = SELECT({id: selectId}, pleaseSelect);
	for( var i=0; i<nodes.teams.length; i++ ) {
		var team = nodes.teams[i];
		var opt = OPTION({value:team}, team);
		appendChildNodes(s, opt);
	}

	connect(s, 'onchange', bind(function() {
		if (s.value == -1) {
			return;
		}
		swapDOM(pleaseSelect, null);
		this.GetItemsToReview(s.value);
	}, this));

	swapDOM(selectId, s);
}
Admin.prototype._errorGetTeamsToReview = function() {
		this._prompt = status_msg("Unable to load teams to review", LEVEL_ERROR);
		log("Admin: Failed to retrieve items to review");
		return;
}
Admin.prototype.GetTeamsToReview = function() {
	log("Admin: Retrieving teams to review");
	IDE_backend_request("admin/review-teams-get", {},
		bind(this._receiveGetTeamsToReview, this),
		bind(this._errorGetTeamsToReview, this)
	);
}
/* *****    End Teams with stuff to review listing code	***** */

/* *****	Items to review display code	***** */
Admin.prototype._receiveGetItemsToReview = function(nodes) {

	// Clear the table
	replaceChildNodes(this._tableId);

	var linkable = ['feed', 'url'];

	for ( var field in nodes.items ) {
		var th = TH(null, 'Team '+field+':');
		// rely on the backend escaping the content for display.
		var content = nodes.items[field];
		if ( findValue( linkable, field ) != -1 ) {	// contains
			content = A({href: content}, content);
		}
		content = TD(null, content);

		var accept = BUTTON(null, 'Accept');
		var reject = BUTTON(null, 'Reject');

		var buttons = TD({'class': 'buttons'}, accept, reject);
		var tr = TR(null, th, content, buttons)

		var setReview = bind(this._setReview, this, tr, team, field, nodes.items[field]);
		connect(accept, 'onclick', partial(setReview, true));
		connect(reject, 'onclick', partial(setReview, false));

		appendChildNodes(this._tableId, tr);
	}
}
Admin.prototype._errorGetItemsToReview = function() {
		this._prompt = status_msg("Unable to load items to review", LEVEL_ERROR);
		log("Admin: Failed to retrieve items to review");
		return;
}
Admin.prototype.GetItemsToReview = function(team) {
	log("Admin: Retrieving items to review for team " + team);
	IDE_backend_request("admin/review-items-get", { team: team },
		bind(this._receiveGetItemsToReview, this),
		bind(this._errorGetItemsToReview, this)
	);
}
/* *****    End Items to review display code	***** */

/* *****    Review saving code	***** */
Admin.prototype._receive_setReview = function(tr, nodes) {
	removeElement(tr);
}
Admin.prototype._error_setReview = function(tr) {
	// enable the row so they can re-submit
	removeElementClass(tr, 'disabled');
	removeElementClass(tr, 'valid');
	removeElementClass(tr, 'rejected');

	map(function(button) {
		button.disabled = false;
	}, getElementsByTagAndClassName('button', null, tr));

	this._prompt = status_msg("Unable to save review", LEVEL_ERROR);
	log("Admin: Failed to save review");
}
Admin.prototype._setReview = function(tr, team, field, value, valid) {
	// disable the row until the response comes back
	addElementClass(tr, 'disabled');
	addElementClass(tr, valid ? 'valid' : 'rejected');
	map(function(button) {
		button.disabled = true;
	}, getElementsByTagAndClassName('button', null, tr));

	log("Admin: Setting review for " + field + ' of team ' + team);
	IDE_backend_request("admin/review-item-set",
		{ team: team, item: field, value: value, valid: valid },
		bind(this._receive_setReview, this, tr),
		bind(this._error_setReview, this, tr)
	);
}
/* *****    End Review saving code	***** */
