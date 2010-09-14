function Checkout() {
	// handle on the applet
	this._applet = null;

	// hold status message for the page
	this._prompt = null;

	// keep track of whether object is initialised
	this._inited = false;

	// keep trac of whether java works here or not,
	// default to whether the browser claims to support java
	this._java_works = navigator.javaEnabled();

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

	// If Java works, and the user wants it then load the applet
	if (this._use_java()) {
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

	/* remember that we are initialised */
	this._inited = true;
}

// If Java works and the user wants it
Checkout.prototype._use_java = function() {
	return this._java_works && user.get_setting('export.usejava');
}

Checkout.prototype._basic = function(url, successCallback, errorCallback) {
	var handle = window.open(url, "Source Checkout");
	if (handle == null) {
		window.location = url;
	}
	successCallback();
}

Checkout.prototype._getLocation = function() {
	var protocolhost = location.protocol + "//" + location.hostname;

	if (location.port != 80) {
		protocolhost += ":" + location.port;
	}

	return protocolhost;
}

Checkout.prototype._java = function(url, successCallback, errorCallback) {
	var xhr = new XMLHttpRequest();
	var retcode = this._applet.writeZip(this._getLocation() + "/" + url);
	//if downloading worked
	if (retcode == 0) {
		successCallBack();
	} else {
		// negative response code means that java is not going to work ever
		if (retcode < 0) {
			this._java_works = false;
		}

		//use the file dialogue download method
		this._basic(url, successCallback, errorCallback);
	}
}

Checkout.prototype._download = function(successCallback, errorCallback, nodes) {
	var url = nodes.url;
	if (this._use_java() && this._applet != null) {
		this._java(url, successCallback, errorCallback);
	} else {
		this._basic(url, successCallback, errorCallback);
	}
}

/* Initiates a checkout.
 * We also munge the successCallback to record the number of checkouts the user has done.
 */
Checkout.prototype.checkout = function(team, project, successCallback, errorCallback) {
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
	IDE_backend_request("proj/co", {team: team, project: project},
	                    bind(this._download, this, record_export, errorCallback),
	                    errorCallback);
}
