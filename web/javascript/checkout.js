function Checkout() {
	// hold status message for the page
	this._prompt = null;

	// store a copy of our instance!
	Checkout.Instance = this;
}

Checkout.GetInstance = function() {
	if(Checkout.Instance == null) {
		Checkout.Instance = new Checkout();
	}
	return Checkout.Instance;
}

Checkout.prototype._basic = function(url, rev, successCallback, errorCallback) {
	logDebug('Checking out code using basic file transfer');
	getElement('robot-zip').src = url;
	var revLink = A({href: url, target: '_blank', title: 'Download the zip again'}, rev);
	var span = SPAN(null, 'Exporting ', revLink, '.');
	status_msg(span, LEVEL_INFO);
	successCallback();
}

// Gets the current location without the hash or search parts
Checkout.prototype._getLocation = function() {
	return location.protocol + '//' + location.host + location.pathname;
}

Checkout.prototype._download = function(successCallback, errorCallback, nodes) {
	var url = nodes.url;
	var rev = IDE_hash_shrink(nodes.rev);
	this._basic(url, rev, successCallback, errorCallback);
}

/* Initiates a checkout.
 * We also munge the successCallback to record the number of checkouts the user has done.
 */
Checkout.prototype.checkout = function(team, project, rev, successCallback, errorCallback) {
	var setting = 'export.number';
	var record_export = function() {
		var exports = user.get_setting(setting);
		if(exports == null) {
			exports = 0;
		}
		var s = new Object();
		s[setting] = exports+1;
		user.set_settings(s);
		successCallback();
	}
	// get URL
	IDE_backend_request("proj/co", {team: team, project: project, rev: rev},
	                    bind(this._download, this, record_export, errorCallback),
	                    errorCallback);
}
