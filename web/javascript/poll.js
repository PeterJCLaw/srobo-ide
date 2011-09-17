
/**
 * Generic class to wrap a repeated call to the same endpoint,
 *  listening for when the data it returns changes.
 * Construction:
 * @param command: The endpoint to poll.
 * @param delay: The time to wait between polls (default = 7) in seconds.
 * @param retry: How many times to retry a failed poll (default = floor(delay / 10)).
 * Signals:
 *  onchange: The data received from the poll is different to the last time it was received without error.
 *            The data received is included as the first argument to the signal.
 */
function Poll(command, delay, retry) {
	// The command to poll
	this._command = command;

	// How long to wait between polls
	this._delay = delay || 7;

	// How many times to retry if a poll fails
	this._retryCount = retry || Math.floor(this._delay / 10);

	// Store the previous data from this poll so we can detect changes
	this._prevData = null;

	this._setupPoll();
}

Poll.prototype._setupPoll = function(delay) {
	var delay = delay || this._delay;
	callLater( delay, bind(this._doPoll, this, 1) );
}

Poll.prototype._pollResponse = function(nodes) {
	if (JSON.stringify(nodes) != JSON.stringify(this._prevData)) {
		this._prevData = nodes;
		signal(this, 'onchange', nodes);
	}
	this._setupPoll();
}

Poll.prototype._pollResponseError = function(retryNum, nodes) {
	logDebug('Poll for "'+this._command+'" failed (attempt '+retryNum+').');
	// either retry immediately, or setup the next ordinary poll
	if (retryNum < this._retryCount) {
		this._doPoll(retryNum + 1);
	} else {
		this._setupPoll();
	}
}

Poll.prototype._doPoll = function(retryNum) {
	IDE_backend_request(this._command, {},
		bind(this._pollResponse, this),
		bind(this._pollResponseError, this, retryNum)
	);
}
