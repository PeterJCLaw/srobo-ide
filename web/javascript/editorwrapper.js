// vim: noexpandtab

/// A wrapper around our editor of choice.
/// This used to be editarea, which needed coddling to prevent it exploding (hence the name).
function ide_editarea(id) {
	// Public functions:
	//  - getSelectionRange() -- get the cursor selection range, no need to pass the id, load safe
	//  - setSelectionRange() -- set the cursor selection range, no need to pass the id, load safe

	// Public properties:
	this._id = id;
	this._open_queue = [];
	this._close_queue = [];
	this._value = null;
	this._ace = null;	// An ACE Editor instance

	this._init = function() {
		this._ace = ace.edit( this._id );
	}

	this.newSession = function() {
		var UndoManager = require("ace/undomanager").UndoManager;
		var EditSession = require( "ace/edit_session" ).EditSession;
		var session = new EditSession( "" , null );
		session.setUndoManager( new UndoManager );
		this._ace.setSession( session );
		return session;
	}

	this.setSession = function( session ) {
		if( session != null ) {
			this._ace.setSession( session );
		}
	}

	this.setSelectionRange = function( range ) {
		if( range != null ) {
			this._ace.selection.setSelectionRange( range );
		}
	}

	this.setReadOnly = function(isReadOnly) {
		this._ace.setReadOnly(isReadOnly);
		setReadOnly(this._id, isReadOnly);
	}

	this.focus = function() {
		return this._ace.focus();
	}

	this.getTextModeForPath = function(path) {
		var modelist = require("ace/ext/modelist");
		var textMode = modelist.getModeForPath(path).mode;
		return textMode;
	}

	this._init();
}
