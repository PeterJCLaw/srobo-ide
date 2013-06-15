
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

		// Show the user some help when the click the forgotten password link
		disconnectAll( "forgotten-password-button" );
		connect( "forgotten-password-button", "onclick", bind( this._forgotten_password_help, this ) );

		var userBox = getElement("username");

		//clear box on focus, replace with 'username' on blur.
		connect("username","onfocus",function(){
			if (userBox.value == userBox.defaultValue)
				userBox.value='';
		});
		connect("username","onblur",function(){
			if (!userBox.value)
				userBox.value = userBox.defaultValue;
		});
		//and focus the username
		userBox.focus();
	}

	// Grab the username and password from the login form and start the login
	this._do_login = function(ev) {
		if( ev != null ) {
			ev.preventDefault();
			ev.stopPropagation();
		}

		var user = getElement("username").value;
		var pass = getElement("password").value;

		IDE_backend_request("auth/authenticate", {username: user, password: pass},
			function(){ window.location.reload(); },
			bind(function(errcode, errmsg) {
				status_msg(errmsg, LEVEL_WARN);
				getElement("password").value = '';
				getElement("password").focus();
			}, this)
		);
	}

	// Grab the username and password from the login form and start the login
	this._forgotten_password_help = function(ev) {
		if (ev != null) {
			ev.preventDefault();
			ev.stopPropagation();
		}

		toggleElementClass('visible', 'forgotten-password-help');
	}
}

// onload function
addLoadEvent( function() {
	//On page load - this replaces a onload action of the body tag
	login = new Login();
	login.setup();
});
