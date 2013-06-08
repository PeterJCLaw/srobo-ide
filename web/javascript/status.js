
// **** Status Bar ****

// Number that's incremented every time a new status message is displayed
status_num = 0;

// Status levels
LEVEL_INFO = 0;
LEVEL_OK = 1;
LEVEL_WARN = 2;
LEVEL_ERROR = 3;

// The ID of the status bar, this can easily be overwritten if needed
status_id = "status";

function status_clearclass() {
	var classes = ["status-info", "status-ok", "status-warn", "status-error"];
	var s = getElement(status_id);

	map( partial( removeElementClass, s ), classes );
}

// Hide the status bar
function status_hide() {
	setStyle( "status-span", {"display":"none"} );

	var s = getElement(status_id);
	status_clearclass();
}

// Show the status bar with the given message, and prepend "warning" or "error"
function status_msg( message, level ) {
	switch(level) {
	case LEVEL_WARN:
		message = [ createDOM( "STRONG", null, "Warning: " ),
			    message ];
		break;
	case LEVEL_ERROR:
		message = [ createDOM( "STRONG", null, "Error: " ),
			    message ];
		break;
	}

	return status_rich_show( message, level );
}

// Replace the status bar's content with the given DOM object
function status_rich_show( obj, level ) {
	var s = getElement(status_id);

	var o = createDOM( "SPAN", { "id" : "status-span",
				     "display" : "" }, obj );
	replaceChildNodes( status_id, o );

	status_clearclass();
	switch(level) {
	case LEVEL_INFO:
		addElementClass( s, "status-info" );
		break;
	case LEVEL_OK:
		addElementClass( s, "status-ok" );
		break;
	case LEVEL_WARN:
		addElementClass( s, "status-warn" );
		break;
	default:
	case LEVEL_ERROR:
		addElementClass( s, "status-error ");
		break;
	}

	// Give it a shake if it's not OK
	if( level > LEVEL_OK )
		shake(s);

	status_num ++;
	var close_f = partial( status_close, status_num );

	return { "close": close_f };
}

// Hide the status if message id is still displayed
function status_close(id) {
	if( status_num == id )
		status_hide();
}

function status_click() {
	status_hide();
}

// Display a status message with some options
// Args:
//    message: The message to display
//      level: The log level of the message (LEVEL_OK etc)
//   opt_list: An array of buttons, each of which must be an object with the following properties
//             text: The button text
//         callback: The function to call when the button is clicked.
function status_options( message, level, opt_list ) {
	var m = [ message, " -- " ]
	for( var i=0; i < opt_list.length; i++) {
		var b = A({ "href" : "#" }, opt_list[i].text );
		connect( b, "onclick", partial(function(cb) { status_click(); cb(); }, opt_list[i].callback) );
		m.push(b);
		if(i+1 < opt_list.length)
			m.push(' | ');
	}

	return status_msg( m, level );
}

// Display a status message with a button
// Args:
//    message: The message to display
//      level: The log level of the message (LEVEL_OK etc)
//      btext: The button text
//      bfunc: The function to call when the button is clicked.
function status_button( message, level, btext, bfunc ) {
	var b = createDOM( "A", { "href" : "#" }, btext );
	connect( b, "onclick", function() { status_click(); bfunc(); } );

	var m = [ message, " -- ", b ]

	return status_msg( m, level );
}
