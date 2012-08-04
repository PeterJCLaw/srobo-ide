function Checkout() {
	// handle on the applet
	this._applet = null;

	// hold status message for the page
	this._prompt = null;

	// keep track of whether object is initialised
	this._inited = false;

	// keep trac of whether java works here or not,
	// default to whether the browser claims to support java
	this._java_works = IDE_java_enabled();

	// The user setting key we use
	this._setting_key = 'export.usejava';

	// store a copy of our instance!
	Checkout.Instance = this;
}

Checkout.GetInstance = function() {
	if(Checkout.Instance == null) {
		Checkout.Instance = new Checkout();
	}
	return Checkout.Instance;
}

Checkout.prototype.init = function() {
	if(this._inited == true) {
		return;
	}

	logDebug("Checkout: Initializing");

	this._ensureApplet();

	/* remember that we are initialised */
	this._inited = true;
}

// On-demand create the applet.
// This allows the user to change their setting while the page is live and it work
Checkout.prototype._ensureApplet = function() {
	// If Java works, and the user wants it, and the applet isn't already loaded
	// then load the applet
	if (!this._use_java() || this._applet != null) {
		return;
	}

	this._applet = createDOM('applet',
		{ archive: 'applet/build/checkout.jar',
			 code: 'org.studentrobotics.ide.checkout.CheckoutApplet',
			   id: 'checkout-applet',
			 name: 'checkout-applet',
		MAYSCRIPT: true,
			width: '128',
		   height: '128'
		});
	appendChildNodes('applet', this._applet);
}

// If Java works and the user wants it
Checkout.prototype._use_java = function() {
	return this._java_works && user.get_setting(this._setting_key);
}

Checkout.prototype._basic = function(url, rev, successCallback, errorCallback) {
	logDebug('Checking out code using basic file transfer');
	$('robot-zip').src = url;
	status_msg('Exporting ' + rev + '.', LEVEL_INFO);
	successCallback();
}

// Gets the current location without the hash or search parts
Checkout.prototype._getLocation = function() {
	return location.protocol + '//' + location.host + location.pathname;
}

Checkout.prototype._java = function(url, rev, successCallback, errorCallback) {
	logDebug('Checking out code using magic java file transfer');
	this._ensureApplet();
	var xhr = new XMLHttpRequest();
	var retcode = this._applet.writeZip(encodeURI(this._getLocation() + url));
	//if downloading worked
	if (retcode == 0) {
		status_msg("Automatic export of " + rev + " succeeded", LEVEL_INFO);
		successCallback();
	} else {
		// negative response code means that java is not going to work ever
		if (retcode < 0) {
			this._java_works = false;
		}

		//use the file dialogue download method
		this._basic(url, rev, successCallback, errorCallback);
	}
}

Checkout.prototype._download = function(successCallback, errorCallback, nodes) {
	var url = nodes.url;
	var rev = IDE_hash_shrink(nodes.rev);
	if (this._use_java()) {
		this._java(url, rev, successCallback, errorCallback);
	} else {
		this._basic(url, rev, successCallback, errorCallback);
	}
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
