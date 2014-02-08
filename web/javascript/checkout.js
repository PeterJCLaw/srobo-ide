function Checkout() {
	// hold status message for the page
	this._prompt = null;

	// store a copy of our instance!
	Checkout.Instance = this;
}

Checkout.GetInstance = function() {
	if (Checkout.Instance == null) {
		Checkout.Instance = new Checkout();
	}
	return Checkout.Instance;
};

Checkout.prototype._basic = function(url, project, rev, successCallback, errorCallback) {
	logDebug('Checking out code using basic file transfer');
	getElement('robot-zip').src = url;
	var projRev = project + '@' + rev;
	var revLink = A({href: url, target: '_blank', title: 'Download the zip again'}, projRev);
	var span = SPAN(null, 'Exporting ', revLink, '.');
	status_msg(span, LEVEL_INFO);
	successCallback();
};

Checkout.prototype._download = function(successCallback, errorCallback, project, nodes) {
	var url = nodes.url;
	var rev = IDE_hash_shrink(nodes.rev);
	this._basic(url, project, rev, successCallback, errorCallback);
};

/**
 * Initiates a checkout.
 */
Checkout.prototype.checkout = function(team, project, rev, successCallback, errorCallback) {
	// get URL
	IDE_backend_request("proj/co", {team: team, project: project, rev: rev},
	                    bind(this._download, this, successCallback, errorCallback, project),
	                    errorCallback);
};
