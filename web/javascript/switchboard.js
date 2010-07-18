function Switchboard()
{
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	//hold the currently selected milestone
	this.milestone = null;

	//hold the dictionary of timeline events
	this.events = null;

	//keep track of whether object is initialised
	this._inited = false;

	//connect up the submit event for the 'submit-your-blogs-rss' form
	this._signals.push(connect( document.user_feed_form, "onsubmit", bind(this.SubmitFeed, this)));

}

/* *****	Initialization code	***** */
Switchboard.prototype.init = function()
{
	if(this._inited == false)
	{
		logDebug("Switchboard: Initializing");

		/* Initialize a new tab for switchboard - Do this only once */
		this.tab = new Tab( "Switchboard" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		tabbar.add_tab( this.tab );

		/* Initialise indiviual page elements */
		this.GetMessages();
		this.GetMilestones();
		this.GetFeed();
		this.GetBlogPosts();

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}
/* *****	End Initialization Code 	***** */

/* ***** Tab events: onfocus, onblur and close		***** */
Switchboard.prototype._onfocus = function()
{
	setStyle($("switchboard-page"), {'display':'block'});
}

Switchboard.prototype._onblur = function()
{
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	setStyle($("switchboard-page"), {'display':'none'});
}

Switchboard.prototype._close = function()
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
	setStyle($("switchboard-page"), {'display':'none'});
}
/* *****	End Tab events		***** */

/* *****    RSS feed url submit code	***** */
Switchboard.prototype._receiveSubmitFeed = function(nodes)
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
		setStyle("user-feed-url", {'background-color': '#98FF4F'});
		this.GetBlogPosts();
	}
	else
	{
		setStyle("user-feed-url", {'background-color': '#FFFFFF'});
	}
}
Switchboard.prototype._errorSubmitFeed = function()
{
	this._prompt = status_msg("Unable to update blog feed", LEVEL_ERROR);
	document.user_feed_form.user_feed_input.value = "";
}
Switchboard.prototype.SubmitFeed = function()
{
	logDebug("Switchboard: Setting blog feed");
	setStyle("user-feed-url", {'background-color': '#FFFFFF'});
	var d = loadJSONDoc("./switchboard/setblogfeed",
		{'feedurl':document.user_feed_form.user_feed_input.value});

	d.addCallback( bind( this._receiveSubmitFeed, this) );
	d.addErrback( bind( this._errorSubmitFeed, this) );
	return false;
}
/* *****   End RSS feed url submit code ***** */

/* *****	Student blog feed code	***** */

Switchboard.prototype._receiveGetFeed = function(nodes)
{
	//test for error - bail
	if(nodes.error > 0)
	{
		this._errorGetFeed();
		return;
	}
	else
	{
		//update url on page
		document.user_feed_form.user_feed_input.value = nodes.feedurl;
	}
	if(nodes.checked > 0 && nodes.valid > 0)	//it's been checked and found valid
	{
		setStyle("user-feed-url", {'background-color': '#98FF4F'});
	}
	else if(nodes.checked > 0)	//if it's been found invalid: mark it red
	{
		setStyle("user-feed-url", {'background-color': '#FF6666'});
	}
	else	//if it's not been checked: leave it white
	{
		setStyle("user-feed-url", {'background-color': '#FFFFFF'});
	}
}
Switchboard.prototype._errorGetFeed = function()
{
		this._prompt = status_msg("Unable to load feed url", LEVEL_ERROR);
		document.user_feed_form.user_feed_input.value = "";
		logDebug("Switchboard: Failed to retrieve feed url");
		return;
}
Switchboard.prototype.GetFeed = function()
{
	logDebug("Switchboard: Retrieving blog feed");
	var d = loadJSONDoc("./switchboard/getblogfeed", {});

	d.addCallback( bind(this._receiveGetFeed, this) );
	d.addErrback( bind(this._errorGetFeed, this) );
}
/* *****    End Student blog feed code	***** */

/* *****	Message Feed code	***** */
Switchboard.prototype.receiveMessages = function(nodes)
{
	// Remove any existing messages before adding new ones
	replaceChildNodes($("message-list"));
	for(var m in nodes.msgs)
	{
		var item = nodes.msgs[m];
		var a = A({'href':item.link, 'target':'_blank'}, item.title);	//Write message title link
		var s = SPAN({}, "");
		s.innerHTML = ": "+item.body+" [by "+item.author+"]";		//message body
		var l = LI({},"");
		appendChildNodes(l, a);						//Add the title to the list element
		appendChildNodes(l, s);						//Add the message to the list element
		appendChildNodes($("message-list"),l);				//Add the whole list to the message window
	}
}

Switchboard.prototype.errorReceiveMessages = function()
{
	this._prompt = status_msg("Unable to load messages", LEVEL_ERROR);
	logDebug("Switchboard: Failed to retrieve messages");
}

Switchboard.prototype.GetMessages = function()
{
	logDebug("Switchboard: Retrieving SR message feed");
	var d = loadJSONDoc("./switchboard/getmessages", {});

	d.addCallback( bind(this.receiveMessages, this));
	d.addErrback( bind(this.errorReceiveMessages, this));
}
/* *****	End Message Feed code	***** */

/* *****	Milestones Code		***** */
Switchboard.prototype.changeMilestone = function(id)
{	/* de-highlight previous milestone and highlight new one */
	if(this.milestone != null)
	{
		setStyle("timeline-ev-"+this.milestone, {'background':'#FF0000'});
	}
	this.milestone = id;
	setStyle("timeline-ev-"+id, {'background':'#FFFC00'});
	$("timeline-description").innerHTML = "<strong>"+this.events[id].title+": </strong>"+this.events[id].desc+" ("+this.events[id].date+")";
}

Switchboard.prototype.receiveMilestones = function(nodes)
{
/*	Overview: build the timeline showing key milestones
 *	Description: Each milestone event is converted into a <div>
 *	with an offset from the parent proportional to its date.
 */
	logDebug("Generating Timeline..");

	/* Store the events in object */
	this.events = nodes.events;

	/* Date manipulation */
	var start_date = new Date(nodes.start);
	logDebug("Timeline start: "+ start_date);
	var end_date = new Date(nodes.end);
	logDebug("Timeline end: "+ end_date);
	var duration = end_date - start_date;
	logDebug("Timeline Duration: "+duration);

	/* get the maximum progress bar width in pixels */
	var bar_width = rstrip(getStyle($("timeline-bar-out"), 'width'), "px");

	/* Convert a date into a pixel offset */
	function getOffset(event_date)
		{
			var d = new Date(event_date);
			var o = Math.floor(((d - start_date)/duration)*bar_width)+"px";
			return o;
		}

	/* set the progress bar width */
	var today = new Date();
	if(today < start_date)
	{
		//not yet at timeline - default to arbitrary date
		today = new Date("November 12, 2009");
	}
	else if(today > end_date)
	{
		//past the end, default to 100%
		today = end_date;
	}
	setStyle($("timeline-bar-in"), {'width': Math.floor(((today-start_date)/duration)*bar_width)+"px"});


	/* Add the events */
	for(m in nodes.events)
	{	/* create and position a new <div> for each timeline event */
		var e = DIV({"class":"timeline-bar-event",
				"id":"timeline-ev-"+m,
				"title":nodes.events[m].title}, "");
		this._signals.push( connect( e, "onclick", bind( this.changeMilestone, this, m) ) );
		appendChildNodes($("timeline-bar-in"), e);
		setStyle(e,
			{'margin-left': getOffset(nodes.events[m].date)});
	}
}

Switchboard.prototype.errorReceiveMilestones = function()
{
	this._prompt = this._prompt = status_msg("Unable to load milestones", LEVEL_ERROR);
	logDebug("Switchboard: Failed to load timeline data");
}

Switchboard.prototype.GetMilestones = function()
{
	logDebug("Switchboard: Retrieving SR timeline");
	var d = loadJSONDoc("./switchboard/milestones", {});

	d.addCallback( bind(this.receiveMilestones, this));
	d.addErrback( bind(this.errorReceiveMilestones, this));
}
/* *****	End Timeline code	***** */

/* *****	Blog Post Code		***** */
Switchboard.prototype.receiveBlogPosts = function(nodes)
{
	// Remove any existing messages before adding new ones
	replaceChildNodes($("student-blogs-list"));
	for(var m in nodes.msgs)
	{
		var item = nodes.msgs[m];
		var a = A({'href':item.link, 'target':'_blank'}, item.title);	//Write message title link
		var s = SPAN({}, "");
		s.innerHTML = ": "+item.body+" [by "+item.author+"]";		//message body
		var l = LI({},"");
		appendChildNodes(l, a);						//Add the title to the list element
		appendChildNodes(l, s);						//Add the message to the list element
		appendChildNodes($("student-blogs-list"),l);			//Add the whole list to the message window
	}
}

Switchboard.prototype.errorReceiveBlogPosts = function()
{
	this._prompt = this._prompt = status_msg("Unable to load competitors' blog posts", LEVEL_ERROR);
	logDebug("Switchboard: Failed to retrieve competitors blog posts");
}

Switchboard.prototype.GetBlogPosts = function()
{
	logDebug("Switchboard: Retrieving competitors' blog posts ");
	var d = loadJSONDoc("./switchboard/getblogposts", {});

	d.addCallback( bind(this.receiveBlogPosts, this));
	d.addErrback( bind(this.errorReceiveBlogPosts, this));
}
/* *****	End Blog Post Code	***** */
