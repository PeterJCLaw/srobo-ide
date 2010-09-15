
// The ID of the status bar
status_id = "login-feedback";

// login control
function Login() {

	// Show the login dialog
	this.setup = function() {

		// Connect up the onclick event to the login button
		disconnectAll( "login-button" );
		connect( "login-button", "onclick", bind( this._do_login, this ) );

		// Do stuff when the user presses enter
		disconnectAll( "login-box" );
		connect( "login-box", "onsubmit", bind( this._do_login, this ) );

		//clear box on focus, replace with 'username' on blur.
		connect("username","onfocus",function(){if ($("username").value==$("username").defaultValue) $("username").value=''});
		connect("username","onblur",function(){if (!$("username").value) $("username").value = $("username").defaultValue});
		//and focus the username
		$("username").focus();
	}

	// Grab the username and password from the login form and start the login
	this._do_login = function(ev) {
		if( ev != null ) {
			ev.preventDefault();
			ev.stopPropagation();
		}

		var user = $("username").value;
		var pass = $("password").value;

		IDE_backend_request("auth/authenticate", {username: user, password: pass},
			function(){ window.location.reload(); },
			bind(function(errcode, errmsg) {
				status_msg(errmsg, LEVEL_WARN);
				$("password").value = '';
				$("password").focus();
			}, this)
		);
	}
}

// onload function
addLoadEvent( function() {
	//On page load - this replaces a onload action of the body tag
	login = new Login();
	login.setup();
});
