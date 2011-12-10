function TeamStatus()
{
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	//keep track of whether object is initialised
	this._inited = false;

	// connect up the submit event for the form
	this._signals.push(connect( 'team-status-save', 'onclick', bind(this.saveStatus, this)));

	// list of text fields
	this._fields = ['name', 'description', 'feed', 'url'];
}

/* *****	Initialization code	***** */
TeamStatus.prototype.init = function()
{
	if(this._inited == false)
	{
		logDebug("TeamStatus: Initializing");

		/* Initialize a new tab for switchboard - Do this only once */
		this.tab = new Tab( "Team Status" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		tabbar.add_tab( this.tab );

		/* Initialise indiviual page elements */
		this.GetStatus();

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}
/* *****	End Initialization Code 	***** */

/* ***** Tab events: onfocus, onblur and close		***** */
TeamStatus.prototype._onfocus = function()
{
	setStyle($("team-status-page"), {'display':'block'});
}

TeamStatus.prototype._onblur = function()
{
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	setStyle($("team-status-page"), {'display':'none'});
}

TeamStatus.prototype._close = function()
{
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	/* Clear class variables */
	this.milestone = null;
	this.events = null;

	/* Disconnect all signals */
	for(var i = 0; i < this._signals; i++) {
		disconnect(this._signals[i]);
	}
	this._signals = new Array();

	/* Close tab */
	this.tab.close();
	this._inited = false;

	/* hide switchboard page */
	setStyle($("team-status-page"), {'display':'none'});
}
/* *****	End Tab events		***** */

/* *****	Field Handling		***** */
TeamStatus.prototype._setFields = function(data)
{
	for (var i=0; i < this._fields.length; i++)
	{
		var field = this._fields[i];
		$('team-status-'+field+'-input').value = data[field] || '';
	}
}

TeamStatus.prototype._getFields = function()
{
	var data = {};
	for (var i=0; i < this._fields.length; i++)
	{
		var field = this._fields[i];
		var value = $('team-status-'+field+'-input').value;
		if (value != null && !/^\s*$/.test(value))	// not null or whitespace
		{
			data[field] = value;
		}
	}
	return data;
}
/* *****	End Field Handling		***** */

/* *****	Team status fetch code	***** */
TeamStatus.prototype._receiveGetStatus = function(nodes)
{
	if (nodes.error)
	{
		this._errorGetStatus();
		return;
	}
	this._setFields(nodes);
}
TeamStatus.prototype._errorGetStatus = function()
{
	this._setFields({});
	this._prompt = status_msg("Unable to get team status", LEVEL_ERROR);
	logDebug("TeamStatus: Failed to retrieve info");
	return;
}
TeamStatus.prototype.GetStatus = function()
{
	logDebug("TeamStatus: Retrieving team status");
	IDE_backend_request("team/status-get", { team: team },
	                    bind(this._receiveGetStatus, this),
	                    bind(this._errorGetStatus, this));
}
/* *****    End Team Status fetch code	***** */

/* *****    Team Status save code	***** */

TeamStatus.prototype._receivePutStatus = function(nodes)
{
	if (nodes.error)
	{
		this._errorPutStatus();
		return;
	}
	this._prompt = status_msg("Saved team status successfully", LEVEL_OK);
}
TeamStatus.prototype._errorPutStatus = function()
{
	this._prompt = status_msg("Unable to save team status", LEVEL_ERROR);
	logDebug("TeamStatus: Failed to put info");
	return;
}
TeamStatus.prototype._putStatus = function()
{
	var data = this._getFields();
	data.team = team;
	logDebug("TeamStatus: saving team status");
	IDE_backend_request("team/status-put", data,
	                    bind(this._receivePutStatus, this),
	                    bind(this._errorPutStatus, this));
}
TeamStatus.prototype.saveStatus = function()
{
	// TODO: up-to-date checking?
	this._putStatus();
}
/* *****    End Team Status save code	***** */
