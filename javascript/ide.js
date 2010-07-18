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

Object.prototype.clone = function() {
  var newObj = (this instanceof Array) ? [] : {};
  for (i in this) {
    if (i == 'clone') continue;
    if (this[i] && typeof this[i] == "object") {
      newObj[i] = this[i].clone();
    } else newObj[i] = this[i]
  } return newObj;
};

var IDE_auth_token = null
IDE_base = "control.php"

function IDE_authed() {
	return IDE_auth_token != null;
}

function IDE_backend_request(command, arguments, successCallback, errorCallback) {
	var args = arguments.clone();
	if (IDE_auth_token != null) {
		args["auth-token"] = IDE_auth_token
	}
	var rq = JSON.stringify(args);
	var xhr = new XMLHttpRequest();
	var cb = function() {
		if (xhr.readyState != 4) return;
		if (xhr.status == 200) {
			if (xhr.getResponseHeader("Content-type") == "text/html") {
				// PHP fatal error
				errorCallback(-1, "fatal PHP error");
				return;
			}
			var rt = xhr.responseText;
			var rp = JSON.parse(rt);
			if (rp["auth-token"]) {
				IDE_auth_token = rp["auth-token"];
			} else {
				IDE_auth_token = null;
			}
			if (rp.error) {
				errorCallback(rp.error[0], rp.error[1]);
			} else {
				successCallback(rp);
			}
		} else {
			errorCallback(-xhr.status, xhr.statusText);
		}
	};
	xhr.open("POST", IDE_base + "/" + command, true);
	xhr.onreadystatechange = cb;
	xhr.setRequestHeader("Content-type", "text/json");
	xhr.send(rq);
}
