function Calendar() {

	this.proj = "";
	this.team = null;

	//holds the selected month & year
	this.date = new Date();

	this.logs = new Array();	//will hold complete month of log entries
	this.logdays = new Array();	//will hold max of one log entry (the last) for each day in month

	//event signals
	this._signals = new Array();
}

var MONTHS = new Array("January", "February", "March", "April", "May", "June",
	"July", "August","September", "October", "November", "December");


Calendar.prototype.init = function() {
	this.logs = new Array();
	this.logdays = new Array();

	// set the date so that we draw it with the correct days offset
	this.date.setDate(1);

	//do html
	this.drawCal();
	//try ajax
	this.getDates();
	//setup events

	map( disconnect, this._signals );
	this._signals = [];

	this._signals.push( connect("cal-prev-month",
				    'onclick',
				    bind(this.changeMonth, this, -1) ) );
	this._signals.push( connect("cal-next-month",
				    'onclick',
				    bind(this.changeMonth, this, +1) ) );
}

Calendar.prototype.drawCal = function() {

	//Set month header
	getElement("cal-header").innerHTML = MONTHS[this.date.getMonth()]+" "+this.date.getFullYear();

	//reset row, cell and day variables
	var td = 0;
	var tr = 0;
	var day = 1;

	//clear all cells from calendar
	for(tr=0; tr < 6; tr++) {
		replaceChildNodes("cal-row-"+tr);
	}

	//insert grey cells so we start on the correct day of the week
	tr = 0;
	for(td= 0; td < 7; td++) {
		var rowId = "cal-row-" + tr;
		if(td < this.date.getDay()) {
			appendChildNodes(rowId, TD({'class':'null'}, ""));
		} else {
			appendChildNodes(rowId, TD({'id' : 'cal'+day}, day));
			day++;
		}
	}

	//now generate the rest of the cells in rows of 7 cells
	for(tr=1; tr < 6; tr++) {
		var rowId = "cal-row-" + tr;
		while(td < (7*(tr+1))) {
			if(day <= this.dinm() ) {
				appendChildNodes(rowId, TD({'id' : 'cal'+day}, day));
				day++;
			} else {
				//appendChildNodes(rowId, TD(null, " "));
			}
			td++;
		}
	}

	//highlight today's date (bold text only) if we are showing current month and current year
	var date_today = new Date();
	if ( this.date.getMonth() == date_today.getMonth() && this.date.getFullYear() == date_today.getFullYear() )
		setStyle("cal" + date_today.getDate(), {"font-weight" : "bold", "border" : "1px solid #eee"});

	//clears date/revision select box
	replaceChildNodes("cal-revs",
		OPTION({"value" : 'HEAD'}, "HEAD - the most recent version"),
		OPTION({"value" : -1, "selected" : "selected"}, "Select a date")
	);
}

//convert date string in log array into jscript date
Calendar.prototype.extract = function(datetime) {
	var parts = datetime.split("/");
	return new Date(parts[0], parts[1], parts[2], parts[3], parts[4], parts[5]);
}

//return the number of days in the week
Calendar.prototype.dinm = function() {
	return 32 - new Date(this.date.getFullYear(), this.date.getMonth(), 32).getDate();
}

//ajax handler for receiving logs from server
Calendar.prototype._receiveDates = function(nodes) {

	if(nodes.log.length > 0) {
		//convert string representation of date to javascript date object
		this.logs = map(function(x) {
			var jsdate = new Date(x.time * 1000);
			return { "date" : jsdate,
				 "message" : x.message,
				 "rev" : x.hash,
				 "author" : x.author};
		}, nodes.log);


		this.processDates();
		this.updateCal();
	}
	else {
		this.logs = [];
		this.logdays = [];
		return;
	}
}

//ajax handler for failed requests
Calendar.prototype._errorReceiveDates = function() {
	logDebug("Error retrieving calendar dates");
}

//get month of logs messages from server
Calendar.prototype.getDates = function() {
	if (IDE_string_empty(this.proj)) {
		return;
	}

	IDE_backend_request("proj/log", {team: this.team,
	                                 project: this.proj},
	                                 bind(this._receiveDates, this),
	                                 bind(this._errorReceiveDates, this));
}

//create a new array with one log entry per date (the last one from that day of that month)
Calendar.prototype.processDates = function() {

	//blank array
	this.logdays = new Array();

	//get array of days with corresponding log entries
	for(var z=0; z < this.logs.length; z++) {
		var now = this.logs[z].date.getDate();
		if( this.logs[z].date.getMonth() == this.date.getMonth()	// is in the month on display
		 && this.logs[z].date.getYear() == this.date.getYear()	// is in the year on display
		 && findValue(this.logdays, now) < 0) {
			this.logdays.push(now);
		}
	}
}

//use logdays to bring to life the cells on the Calendar which relate to log entries
Calendar.prototype.updateCal = function() {
	for(var i=0; i < this.logdays.length; i++) {
		var cell = getElement("cal"+this.logdays[i]);
		setNodeAttribute(cell, "class", "td-log");
		connect(cell,
			'onclick',
			bind(this.change_day, this, this.logdays[i]) );
		setNodeAttribute(cell, "title", "Click to see revisions for this day");
	}
}

Calendar.prototype.changeMonth = function(dir) {
	this.date.setMonth(this.date.getMonth() + dir);
	replaceChildNodes("cal-revs",
		OPTION({"value" : 'HEAD'}, "HEAD - the most recent version"),
		OPTION({"value" : -1, "selected" : "selected"}, "Select a date")
	);
	this.init();
}

Calendar.prototype.change_day = function(target) {
	//set this.date's day to the current date (for message in drop-down)
	this.date.setDate(target);
	//alert user to select a revision
	replaceChildNodes("cal-revs",
		OPTION({"value" : 'HEAD'}, "HEAD - the most recent version"),
		OPTION({"value" : -1, "selected" : "selected"}, "Select a revision for "+this.date.getDate()+" "+MONTHS[this.date.getMonth()])
	);

	//clear the boxes from around all dates
	for (var i=1; i<=this.dinm(); i++)
		setStyle("cal"+i, {"border-color" : "#eee"});

	//but a box around the selected day
	setStyle("cal"+target, {"border-color" : "#000"});

	//get logs for target date
	for(var i = 0; i < this.logs.length; i++) {
		if(this.logs[i].date.getDate() == target) {
			appendChildNodes("cal-revs", OPTION({ "value" : this.logs[i].rev},
							    IDE_hash_shrink(this.logs[i].rev) + " " +
							    this.logs[i].author+": "+
							    this.logs[i].message.slice(0, 20)+" ("+
							    this.logs[i].date.getHours()+":"+
							    this.logs[i].date.getMinutes()+")"));
		}
	}

	disconnectAll("cal-revs");
	connect("cal-revs", 'onchange', bind(this._load_new_rev, this) );

	projpage.hide_filelist();
	status_msg("Please select a revision", LEVEL_OK);
	getElement("cal-revs").className = "hilight"
	setTimeout(function() {
			getElement("cal-revs").className = "normal"
		},500);
	setTimeout(function() {
			getElement("cal-revs").className = "hilight"
		},1000);
	setTimeout(function() {
			getElement("cal-revs").className = "normal"
		},1500);
}

Calendar.prototype._load_new_rev = function() {
	var target = getElement("cal-revs").value;

	// Check that it's not a special value
	if( !(target < 0) )
		this._load_rev( target );
	if(target == 'HEAD')
	{
		this.date = new Date();
		this.init();
	}
}

Calendar.prototype._load_rev = function(rev) {
	projpage.flist.change_rev(rev);
	rev = IDE_hash_shrink(rev);
	status_msg("Now showing project at revision: "+rev, LEVEL_OK);
}

Calendar.prototype.change_proj = function(project, team) {
	this.proj = project;
	this.team = team;
	this.date = new Date();

	this.init();
}
