/*
   Provide the XMLHttpRequest constructor for IE 5.x-6.x:
   Other browsers (including IE 7.x-8.x) do not redefine
   XMLHttpRequest if it already exists.

   This example is based on findings at:
   http://blogs.msdn.com/xmlteam/archive/2006/10/23/using-the-right-version-of-msxml-in-internet-explorer.aspx
*/
if (typeof XMLHttpRequest == "undefined")
  XMLHttpRequest = function () {
    try { return new ActiveXObject("Msxml2.XMLHTTP.6.0"); }
      catch (e) {}
    try { return new ActiveXObject("Msxml2.XMLHTTP.3.0"); }
      catch (e) {}
    try { return new ActiveXObject("Msxml2.XMLHTTP"); }
      catch (e) {}
    //Microsoft.XMLHTTP points to Msxml2.XMLHTTP.3.0 and is redundant
    throw new Error("This browser does not support XMLHttpRequest.");
  };

var IDE_clone = function(object) {
  var newObj = (object instanceof Array) ? [] : {};
  for (var i in object) {
    if (i == 'clone') continue;
    if (object[i] && typeof object[i] == "object") {
      newObj[i] = IDE_clone(object[i]);
    } else newObj[i] = object[i]
  } return newObj;
};

var IDE_base = "control.php";
var IDE_async_count = 0;
var IDE_backend_debug = null;
showElement = hideElement = function(){};

function IDE_backend_request(command, args, successCallback, errorCallback) {
	var rq = JSON.stringify(args);
	var xhr = new XMLHttpRequest();
	var cb = function() {
		if (xhr.readyState != 4) return;
		IDE_async_count--;
		if( IDE_async_count == 0 ) {
			hideElement('rotating-box');
		}
		if (xhr.status == 200) {
			if (xhr.getResponseHeader("Content-type") == "text/html") {
				// PHP fatal error
				errorCallback(-1, "fatal PHP error", args);
				return;
			}
			var rt = xhr.responseText;
			var rp = JSON.parse(rt);
			if (rp.debug) {
				IDE_backend_debug = rp.debug;
			}
			if (rp.error) {
				errorCallback(rp.error[0], rp.error[1], args);
			} else {
				successCallback(rp, args);
			}
		} else {
			errorCallback(-xhr.status, xhr.statusText, args);
		}
	};
	xhr.open("POST", IDE_base + "/" + command, true);
	xhr.onreadystatechange = cb;
	xhr.setRequestHeader("Content-type", "text/json");
	showElement('rotating-box');
	IDE_async_count++;
	xhr.send(rq);
}

function IDE_path_get_project(path) {
	var split = path.split(/\//);
	return split[1];
}

function IDE_path_get_file(path) {
	var split = path.split(/\//);
	split = split.slice(2);
	return split.join('/');
}
