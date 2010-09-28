/**
 * A poll handler that parts of the IDE can listen to in order to receive
 * updates about their backend status.
 */

function PollHandler() {
	// how long between each poll, in seconds
	this._poll_delay = 7;

	// how long to wait before retrying a poll if something went awry, in seconds
	this._poll_delay_err = 2;

	// hold the most recent data for the poll
	this._old_data = {};

	// hold the most recent data for the poll, in json form
	this._old_data_json = '';

	// store a copy of our instance!
	PollHandler.Instance = this;

	this._init();
}

PollHandler.GetInstance = function() {
	if(PollHandler.Instance == null) {
		PollHandler.Instance = new PollHandler();
	}
	return PollHandler.Instance;
}

/* ***** Initialization code ***** */
PollHandler.prototype._init = function() {
	log('PollHandler: Initializing');
	// start polling
	this._setupPoll();
}

PollHandler.prototype._setupPoll = function(delay) {
	var delay = delay || this._poll_delay;
	log('PollHandler: Setting up a delayed poll');
	callLater( delay, bind(this._doPoll, this) );
}
/* ***** End Initialization Code ***** */

/* ***** Poll Handling ***** */
/// look through the data we have and signal that it's new/changed.
PollHandler.prototype._recievePoll = function(nodes) {
	// setup the next poll
	this._setupPoll();

	// shout that we have new data, for anyone that wants it
	signal( this, 'new-poll-data' );

	// simple global check for changes.
	var nodes_json = JSON.stringify(nodes);
	if (nodes_json == this._old_data_json) {
		return;
	}

	// go through the details of what we have
	for ( var label in nodes ) {
		var data = nodes[label];

		// see if this piece of the info in unchanged
		if (JSON.stringify(data) == JSON.stringify(this._old_data[label])) {
			continue;
		}

		// shout that this piece of data has changed
		signal( this, 'onchange-'+label, data );
	}

	this._old_data = nodes;
	this._old_data_json = nodes_json;
}

PollHandler.prototype._errorRecievePoll = function() {
	// something went wrong, so setup another poll
	this._setupPoll(this._poll_delay_err);
}

PollHandler.prototype._doPoll = function() {
	IDE_backend_request('poll/poll', { team: team },
		bind( this._recievePoll, this ),
		bind( this._errorRecievePoll, this )
	);
}
/* ***** End Poll Handling ***** */

/* ***** End PollHanlder Object ***** */

