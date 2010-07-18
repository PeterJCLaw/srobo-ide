/**
 * Plugin designed for test prupose. It add a button (that manage an alert) and a select (that allow to insert tags) in the toolbar.
 * This plugin also disable the "f" key in the editarea, and load a CSS and a JS file
 */


if (! ("console" in window) || !("firebug" in console)) {
        var names = ["log", "debug", "info", "warn", "error", "assert", "dir", "dirxml", "group"
                     , "groupEnd", "time", "timeEnd", "count", "trace", "profile", "profileEnd"];
        window.console = {};
        for (var i = 0; i <names.length; ++i) window.console[names[i]] = function() {};
}



var EditArea_autocomplete= {
	/**
	 * Get called once this file is loaded (editArea still not initialized)
	 *
	 * @return nothing
	 */
	init: function(){
		//	alert("test init: "+ this._someInternalFunction(2, 3));
		editArea.load_css(this.baseURL+"css/autocomplete.css");
		editArea.load_script(this.baseURL+"../../../MochiKit.js");

		this.strbuf;
		this.NOTCOMPLETING = 0;
		this.COMPLETING = 1;
		this.state = this.NOTCOMPLETING; // State of autocompletion
	}
	/**
	 * Returns the HTML code for a specific control string or false if this plugin doesn't have that control.
	 * A control can be a button, select list or any other HTML item to present in the EditArea user interface.
	 * Language variables such as {$lang_somekey} will also be replaced with contents from
	 * the language packs.
	 *
	 * @param {string} ctrl_name: the name of the control to add
	 * @return HTML code for a specific control or false.
	 * @type string	or boolean
	 */
	,get_control_html: function(ctrl_name){
		switch(ctrl_name){
			case "test_but":
				// Control id, button img, command
				return parent.editAreaLoader.get_button_html('test_but', 'test.gif', 'test_cmd', false, this.baseURL);
			case "test_select":
				html= "<select id='test_select' onchange='javascript:editArea.execCommand(\"test_select_change\")' fileSpecific='no'>"
					+"			<option value='-1'>{$test_select}</option>"
					+"			<option value='h1'>h1</option>"
					+"			<option value='h2'>h2</option>"
					+"			<option value='h3'>h3</option>"
					+"			<option value='h4'>h4</option>"
					+"			<option value='h5'>h5</option>"
					+"			<option value='h6'>h6</option>"
					+"		</select>";
				return html;
		}
		return false;
	}
	/**
	 * Get called once EditArea is fully loaded and initialised
	 *
	 * @return nothing
	 */
	,onload: function(){
		//alert("test load");
		console.info("Autocomplete plugin loaded");
	}

	/**
	 * Is called each time the user touch a keyboard key.
	 *
	 * @param (event) e: the keydown event
	 * @return true - pass to next handler in chain, false - stop chain execution
	 * @type boolean
	 */
	,onkeydown: function(e) {
		var code = e.keyCode;
		var str = String.fromCharCode(code);
		console.debug("Keycode: %i   Character: %s", code, str);

		return this._keydown(e);
	}

	/**
	 * Executes a specific command, this function handles plugin commands.
	 *
	 * @param {string} cmd: the name of the command being executed
	 * @param {unknown} param: the parameter of the command
	 * @return true - pass to next handler in chain, false - stop chain execution
	 * @type boolean
	 */
	,execCommand: function(cmd, param){
		// Handle commands
		switch(cmd){
			case "test_select_change":
				var val= document.getElementById("test_select").value;
				if(val!=-1)
					parent.editAreaLoader.insertTags(editArea.id, "<"+val+">", "</"+val+">");
				document.getElementById("test_select").options[0].selected=true;
				return false;
			case "test_cmd":
				alert("user clicked on test_cmd");
				return false;
		}
		// Pass to next handler in chain
		return true;
	}

	/**
	 * This is just an internal plugin method, prefix all internal methods with a _ character.
	 * The prefix is needed so they doesn't collide with future EditArea callback functions.
	 *
	 * @param {string} a Some arg1.
	 * @param {string} b Some arg2.
	 * @return Some return.
	 * @type unknown
	 */
	,_someInternalFunction : function(a, b) {
		return a+b;
	}

	,_isNormal: function(e) {
		var code = e.keyCode;
		// Only 'normal' if 0-9 with no shift
		if (code >= 48 && code <= 57 && !ShiftPressed(e)) {
			return true;
		} else if ((code >= 65 && code <= 90) || code == 8) {
			return true;
		} else {
			return false;
		}
	}
	,_isIgnore: function(e) {
		var code = e.keyCode;
		var ignoreLst = [16, 17, 18, 19, 20, 45, 91, 92, 144, 145];
		if (ignoreLst.indexOf(code) != -1) {
			return true;
		} else if (CtrlPressed(e) || AltPressed(e)) {
			return (this._isNormal(e));
		} else {
			return false;
		}
	}
	,_isControl: function(e) {
		var code = e.keyCode;
		var controlLst = [38, 40, 13, 27]; // Up, Down, Enter, Escape
		return(controlLst.indexOf(code) != -1);
	}
	,_isBreak: function(e) {
		var code = e.keyCode;
		return (!(this._isNormal(e) || this._isIgnore(e) || this._isControl(e)));
	}

	/**
	 * Process key presses. There are three types of key:
	 * 'normal' - a-z0-9 and backspace, used in function names
	 *            (_ and - would be nice but very hard due to browser incompatibility)
	 * 'ignore' - Ctrl, Shift, Alt, Caps Lock, Num Lock, etc..
	 * 'control' - Keys used to select the desired completion - up/down arrows, enter, esc
	 * 'break' - Any key not in the above three sets, space, tab, symbol, etc..
	 *
	 * N.B. key can be both the 'normal' set and 'ignore' set, e.g. Ctrl+r is the normal
	 * char 'r' but doesn't actually print anything to the edit_area so should be ignored.
	 *
	 * There is also a set of 'start' characters used to determine if autocompletion
	 * should begin, thse being space, tab and (
	 *
	 * The autocomplete plugin uses a static variable to keep track of the current
	 * state, either autocompleting or not.
	 *
	 * If not currently autocompleting, the character before the cursor is checked
	 * against the 'start' characters, if a match is found, and the key pressed is in
	 * the 'normal' set then autocompletion begins.
	 *
	 * If not currently autocompleting and an 'ignore', 'control' or 'break' key is
	 * pressed, nothing happens.
	 *
	 * If autocompleting and a 'normal' character is pressed then this char is added to a buffer
	 * and autocompletion continues.
	 *
	 * If autocompleting and an 'ignore' key is pressed then the state is left unchanged.
	 *
	 * If autocompleting and a 'break' key pressed, autocompletion is terminated.
	 *
	 * If autocompleting and a 'control' key is pressed then the outcome depends on the key:
	 * 	Up: The currently selected completion is changed
	 * 	Down: As above
	 * 	Enter: The currently selected completion is chosen and entered into edit_area
	 * 	Esc: Autocompletion is terminated
	 */
	,_keydown: function(e) {
		var code = e.keyCode;
		var str = String.fromCharCode(code);
		var cursor = editArea.textarea.selectionStart;

		console.log("Normal: %i  Ignore: %i  Control: %i  Break: %i", this._isNormal(e), this._isIgnore(e), this._isControl(e), this._isBreak(e));

		if (this.state == this.NOTCOMPLETING) {
//			if (cursor != 0 && editArea
			// Don't count backspace when not completing
			if (!this._isIgnore(e) && this._isNormal(e) && code != 8) {
				this.state = this.COMPLETING;
				this.strbuf = "";
				this.strbuf += str;
			}
		} else if (this.state == this.COMPLETING) {
			if (this._isIgnore(e)) {
				return true;
			}
			else if (this._isNormal(e)) {
				if (code == 8) {
					this.strbuf = this.strbuf.substr(0, this.strbuf.length - 1);
				} else {
					this.strbuf += str;
				}
			} else if (this._isBreak(e)) {
				this.state = this.NOTCOMPLETING;
			} else if (this._isControl(e)) {
				return false;
			} else {
				return true; // Should never reach here
			}
		}
		console.log(this.state);
		if (this.state == this.COMPLETING) {
			var d = MochiKit.Async.loadJSONDoc("./autocomplete", {str: this.strbuf, nocache: new Date().getTime()});
			var self = this;
			d.addCallback(self._mochiCallback);
			d.addErrback(self._mochiCallback);
		}
		return true;
	}

	,_mochiCallback : function(result) {
		console.log("callback");
		console.log(result);
	}
};

// Adds the plugin class to the list of available EditArea plugins
editArea.add_plugin("autocomplete", EditArea_autocomplete);
