function TeamStatus()
{
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	// Whether or not this is a read-only view
	this._read_only = false;

	//keep track of whether object is initialised
	this._inited = false;

	// whether or not the attempt to save things failed
	this._saveError = false;

	// list of text fields
	this._fields = ['name', 'description', 'feed', 'url'];
}

/* *****	Initialization code	***** */
TeamStatus.prototype.init = function()
{
	if(this._inited == false)
	{
		logDebug("TeamStatus: Initializing");

		if (!validate_team()) {
			return;
		}

		var teamInfo = user.get_team(team);
		var isReadOnly = teamInfo.readOnly == true
		this._set_read_only(isReadOnly);

		/* Initialize a new tab for switchboard - Do this only once */
		this.tab = new Tab( "Team Status" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		var saveElem = getElement('team-status-save');
		saveElem.disabled = isReadOnly;
		if (!isReadOnly) {
			this._signals.push(connect( saveElem, 'onclick', bind( this.saveStatus, this ) ));
			this._signals.push(connect( 'upload-helper', "onload", bind( this._uploadHelperLoad, this ) ));
		}
		tabbar.add_tab( this.tab );

		/* Initialise indiviual page elements */
		this._clearFields();
		this.GetStatus();

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}

TeamStatus.prototype._set_read_only = function(isReadOnly)
{
	if (this._read_only == isReadOnly) {
		return;
	}

	for (var i=0; i < this._fields.length; i++) {
		var field = this._getField(this._fields[i]);
		field.readOnly = isReadOnly;
	}
	this._getField('image').disabled = isReadOnly;

	setReadOnly('team-status-page', isReadOnly);

	this._read_only = isReadOnly;
}
/* *****	End Initialization Code 	***** */

/* ***** Tab events: onfocus, onblur and close		***** */
TeamStatus.prototype._onfocus = function()
{
	showElement('team-status-page');
}

TeamStatus.prototype._onblur = function()
{
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	hideElement('team-status-page');
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
	for(var i = 0; i < this._signals.length; i++) {
		disconnect(this._signals[i]);
	}
	this._signals = new Array();

	/* Close tab */
	this._onblur();
	this.tab.close();
	this._inited = false;
}
/* *****	End Tab events		***** */

/* *****	Field Handling		***** */
// Gets the input field the user may have edited
TeamStatus.prototype._getField = function(name)
{
	return getElement('team-status-'+name+'-input');
}
// Gets the element that the review state class will be set on.
TeamStatus.prototype._getField2 = function(name)
{
	logDebug('getting field: '+name);
	if (name == 'image')
	{
		return getElement('team-status-image-upload-form');
	}
	return this._getField(name);
}
TeamStatus.prototype._setReviewState = function(reviewed)
{
	var fields = this._fields;
	fields.push('image');
	for (var i=0; i < fields.length; i++) {
		var field = fields[i];
		if ( field in reviewed ) {
			addElementClass(this._getField2(field), reviewed[field] ? 'valid' : 'rejected');
		} else {
			removeElementClass(this._getField2(field), 'valid');
			removeElementClass(this._getField2(field), 'rejected');
		}
	}
}
TeamStatus.prototype._setFields = function(data)
{
	for (var i=0; i < this._fields.length; i++)
	{
		var field = this._fields[i];
		this._getField(field).value = data[field] || '';
	}
}
TeamStatus.prototype._clearFields = function()
{
	this._setFields({});
	this._setReviewState({});
}
TeamStatus.prototype._getFields = function()
{
	var data = {};
	for (var i=0; i < this._fields.length; i++)
	{
		var field = this._fields[i];
		var value = this._getField(field).value;
		if (!IDE_string_empty(value))	// not null or whitespace
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
	this._setFields(nodes.items);
	this._setReviewState(nodes.reviewed);
}
TeamStatus.prototype._errorGetStatus = function()
{
	this._clearFields();
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

/* *****    Hidden iframe Wrapping	***** */
TeamStatus.prototype._uploadHelperLoad = function(ev)
{
	var frame = ev.src();
	// If you serve json to Firefox then it shows you a download box, so we need to serve it as text.
	// This means that it then wraps it in <pre>, which we need to get the contents of.
	var content = frame.contentWindow.document.body.textContent;
	logDebug('iframe content: '+content);
	IDE_handle_backend_response(content, {},
	                            bind(this._receivePutStatusImage, this),
	                            bind(this._errorPutStatusImage, this));
}
TeamStatus.prototype._receivePutStatusImage = function(nodes)
{
	if (this._saveError)
	{
		return;
	}

	if (nodes.error)
	{
		this._errorPutStatusImage();
		return;
	}
	this._prompt = status_msg("Saved robot image successfully", LEVEL_OK);
	this._getField('image').value = '';
}
TeamStatus.prototype._errorPutStatusImage = function()
{
	this._saveError = true;
	this._prompt = status_msg("Unable to save robot image", LEVEL_ERROR);
	logDebug("TeamStatus: Failed to upload robot image");
	return;
}
/* *****    End Hidden iframe Wrapping	***** */

/* *****    Team Status save code	***** */

TeamStatus.prototype._receivePutStatus = function(nodes)
{
	if (nodes.error || this._saveError)
	{
		this._errorPutStatus();
		return;
	}
	this._prompt = status_msg("Saved team status successfully", LEVEL_OK);
	this.GetStatus();
}
TeamStatus.prototype._errorPutStatus = function()
{
	this._saveError = true;
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
	this._setReviewState({});
}
TeamStatus.prototype.saveStatus = function()
{
	this._saveError = false;

	// TODO: up-to-date checking?
	this._putStatus();

	var imageInput = this._getField('image');
	if (!IDE_string_empty(imageInput.value))	// not null or whitespace
	{
		// TODO: verify that it looks like an image
		getElement('team-status-image-command').value = 'team/status-put-image';
		getElement('team-status-image-team').value = team;
		getElement('team-status-image-upload-form').submit();
	}
}
/* *****    End Team Status save code	***** */
