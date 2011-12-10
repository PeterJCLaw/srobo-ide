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

/* *****    RSS feed url submit code	***** */
TeamStatus.prototype._receiveSubmitFeed = function(nodes)
{
	if(nodes.error > 0 )
	{
		this._errorSubmitFeed();
	}
	else
	{
		this._prompt = status_msg("Blog feed updated", LEVEL_OK);
		document.user_feed_form.user_feed_input.value = nodes.feedurl;
	}

	if(nodes.valid > 0)
	{
		setStyle("team-feed-url", {'background-color': '#98FF4F'});
		this.GetBlogPosts();
	}
	else
	{
		setStyle("team-feed-url", {'background-color': '#FFFFFF'});
	}
}
TeamStatus.prototype._errorSubmitFeed = function()
{
	this._prompt = status_msg("Unable to update blog feed", LEVEL_ERROR);
	document.user_feed_form.user_feed_input.value = "";
}
TeamStatus.prototype.SubmitFeed = function()
{
	logDebug("TeamStatus: Setting blog feed");
	setStyle("team-feed-url", {'background-color': '#FFFFFF'});
	IDE_backend_request(
		'user/blog-feed-put',
		{'feedurl':document.user_feed_form.user_feed_input.value},
		bind( this._receiveSubmitFeed, this),
		bind( this._errorSubmitFeed, this)
	);
	return false;
}
/* *****   End RSS feed url submit code ***** */

/* *****	Team status fetch code	***** */

TeamStatus.prototype._receiveGetStatus = function(nodes)
{
	//test for error - bail
	if(nodes.error > 0)
	{
		this._errorGetStatus();
		return;
	}
	else
	{
		//update url on page
		$('team-feed-url').value = nodes.feed.url || '';
	}

	if(nodes.feed.checked > 0 && nodes.valid > 0)	//it's been checked and found valid
	{
		setStyle("team-feed-url", {'background-color': '#98FF4F'});
	}
	else if(nodes.feed.checked > 0)	//if it's been found invalid: mark it red
	{
		setStyle("team-feed-url", {'background-color': '#FF6666'});
	}
	else	//if it's not been checked: leave it white
	{
		setStyle("team-feed-url", {'background-color': '#FFFFFF'});
	}
}
TeamStatus.prototype._errorGetStatus = function()
{
	$('team-feed-url').value = '';
	this._prompt = status_msg("Unable to get team status", LEVEL_ERROR);
	logDebug("TeamStatus: Failed to retrieve info");
	return;
}
TeamStatus.prototype.GetStatus = function()
{
	logDebug("TeamStatus: Retrieving blog feed");
	IDE_backend_request("team/status", {},
	                    bind(this._receiveGetStatus, this),
	                    bind(this._errorGetStatus, this));
}
/* *****    End Team Status fetch code	***** */

/* *****    Team Status save code	***** */

TeamStatus.prototype.saveStatus = function()
{
}
/* *****    End Team Status save code	***** */
