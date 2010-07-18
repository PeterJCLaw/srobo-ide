/**
 * Plugin designed for test prupose. It add a button (that manage an alert) and a select (that allow to insert tags) in the toolbar.
 * This plugin also disable the "f" key in the editarea, and load a CSS and a JS file
 */  
var EditArea_SRkeydown= {
	/**
	 * Get called once this file is loaded (editArea still not initialized)
	 * @return nothing	 
	 */	 	 	
	init: function(){ }

	/**
	 * Is called each time the user touch a keyboard key.
	 *	 
	 * @param (event) e: the keydown event
	 * @return true - pass to next handler in chain, false - stop chain execution
	 * @type boolean	 
	 */
	,onkeydown: function(e){
		parent.ea_autosave(e);
		return true;
	}
	
};

// Adds the plugin class to the list of available EditArea plugins
editArea.add_plugin("SRkeydown", EditArea_SRkeydown);
