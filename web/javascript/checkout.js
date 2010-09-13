var IDE_use_java = navigator.javaEnabled();

var IDE_download_basic = function(url, successCallback, errorCallback) {
	var handle = window.open(url, "Source Checkout");
	if (handle == null) {
		window.location = url;
	}
	successCallback();
}

function getLocation() {
	protocolhost = location.protocol + "//" + location.hostname
	if (location.port != 80) {
		protocolhost += ":" + location.port
	}

	return protocolhost
}

var IDE_download_java = function(url, successCallback, errorCallback) {
	var xhr = new XMLHttpRequest();
	var retcode = $("checkout-applet").writeZip(getLocation() + "/" + url);
	//if downloading worked
	if (retcode == 0) successCallBack();
	else {
		// negative response code means that java is not going to work ever
		if (retcode < 0) IDE_use_java = false;

		//use the file dialogue download method
		IDE_download_basic(url, successCallback, errorCallback);
	}
}

var IDE_download = function(url, successCallback, errorCallback) {
	if (IDE_use_java) {
		IDE_download_java(url, successCallback, errorCallback);
	} else {
		IDE_download_basic(url, successCallback, errorCallback);
	}
}

function IDE_checkout(team, project, successCallback, errorCallback) {
	// get URL
	IDE_backend_request("proj/co", {team: team, project: project},
	                    function(response) {
	                    	IDE_download(response.url,
	                    	             successCallback,
	                    	             errorCallback);
	                    }, errorCallback);
}
