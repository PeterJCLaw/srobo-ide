
function RoboLog() {
	this.tab = null;

	// Number of seconds between updates
	this.UPDATE_INTERVAL = 0.3;

	// TODO: Change this to point to dynamic things
	this.LOG_URL = "./robolog";

	this._ping = 0;

	// Whether the robot was connected or not on our last query
	this._robo_state = false;

	this._init = function() {
		connect( "robolog-clear", "onclick", bind( this._clear, this ) );

		this._update();
	}

	this._onfocus = function() {
		setStyle($("robolog-page"), {"display" : "block"});

		this._update();
	}

	this._onblur = function() {
		setStyle($("robolog-page"), {"display" : "none"});
	}

	this._update = function() {
		if( this.tab != null && !this.tab.has_focus() )
			return;

		// Explicitly use MochiKit's loadJSONDoc
		// We don't want the rotating box to be displayed for these requests
		var d = MochiKit.Async.loadJSONDoc( this.LOG_URL, 
						    { "last_received_ping": this._ping ,
						      "team" : team } );

		d.addCallback( bind( this._recv_update, this ) );
		d.addErrback( bind( this._update_fail, this ) );
	}

	this._recv_update = function(resp) {
		t = this.UPDATE_INTERVAL;

		// First check to see whether the server has responded that robologging is currently disabled
		if ( resp["disabled"] ) {
			return;	// live robot logging not running: return and do not ping
		}

		if( resp["present"] ) {
			if( this.tab == null ) {
				this.tab = new Tab("Robot Log");

				connect(this.tab, "onfocus", bind(this._onfocus, this));
				connect(this.tab, "onblur", bind(this._onblur, this));
				
				tabbar.add_tab(this.tab);
			}

			if( !this._robo_state )
				status_msg( "Robot connected", LEVEL_INFO );
			$("robolog-status").innerHTML = "Robot Connected";
		} else {
			if( this._robo_state )
				status_msg( "Robot disconnected", LEVEL_INFO );

			$("robolog-status").innerHTML = "Robot Disconnected";

			// When the robot isn't connected, poll at a slightly more leisurely pace
			t *= 3;
		}

		this._robo_state = resp["present"];

		if( this.tab != null ) {
			this._ping = resp["ping"];

			$("robolog-pre").innerHTML = $("robolog-pre").innerHTML + resp["data"];
			$("robolog-page").scrollTop = $("robolog-page").scrollHeight;
		}

		callLater( t,
			   bind( this._update, this ) );
	}

	this._update_fail = function() {
		logDebug( "Failed to get robot log update -- trying again" );

		// Try again a bit later :-0
		callLater( 5,
			   bind( this._update, this ) );
	}

	this._clear = function() {
		$("robolog-pre").innerHTML = "";
	}

	this._init();
}
