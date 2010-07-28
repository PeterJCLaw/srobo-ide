var IDE_use_java = navigator.javaEnabled();

var IDE_download_basic = function(url, successCallback, errorCallback) {
	var handle = window.open(url, "Source Checkout");
	if (handle == null) {
		window.location = url;
	}
	successCallback();
}

var IDE_download_java = function(url, successCallback, errorCallback) {
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
		var state = xhr.readyState;
		if (state == 4) {
			if (xhr.status == 200) {
				if (document.getElementById("checkout-applet").writeZip(xhr.responseText)) {
					successCallback();
				} else {
					// this could potentially mean a broken Java
					// as a precaution, turn it off
					IDE_use_java = false;
					// hand off to the basic handler
					IDE_download_basic(url, successCallback, errorCallback);
				}
			} else {
				errorCallback();
			}
		}
	};
	xhr.open("GET", url, true);
	xhr.send("");
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
