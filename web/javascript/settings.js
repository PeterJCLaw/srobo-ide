/* ***** Actual available user settings are at the bottom of this file ***** */

function SettingsPage() {
	// hold the tab object
	this.tab = null;

	// hold signals for the page
	this._signals = new Array();

	// hold status message for the page
	this._prompt = null;

	// hold list of available Setting objects
	this._settings = {};

	// keep track of whether object is initialised
	this._inited = false;

	// connect up the submit event for the 'submit-your-blogs-rss' form
	this._signals.push(connect( 'settings-save', 'onclick', bind(this.saveSettings, this)));

	// store a copy of our instance!
	SettingsPage.Instance = this;
}

SettingsPage.GetInstance = function() {
	if(SettingsPage.Instance == null) {
		SettingsPage.Instance = new SettingsPage();
	}
	return SettingsPage.Instance;
}

/* ***** Initialization code ***** */
SettingsPage.prototype.init = function() {
	if(this._inited == false)
	{
		logDebug("SettingsPage: Initializing");

		/* Initialize a new tab for SettingsPage - Do this only once */
		this.tab = new Tab( "Settings" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		tabbar.add_tab( this.tab );

		// Init each of the Settings
		replaceChildNodes('settings-table');
		for( var id in SettingsPage.Settings ) {
			this._settings[id] = new Setting(
				'settings-table',
				SettingsPage.Settings[id].name,
				SettingsPage.Settings[id].description,
				SettingsPage.Settings[id].options
			);
			if(user.get_setting(id) != null) {
				this._settings[id].setValue(user.get_setting(id));
			}
		}

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}
/* ***** End Initialization Code ***** */

/* ***** Tab events ***** */
SettingsPage.prototype._onfocus = function() {
	showElement('settings-page');
}

SettingsPage.prototype._onblur = function() {
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	hideElement('settings-page');
}

SettingsPage.prototype._close = function() {
	/* Clear and hide things as if we were moved away from */
	this._onblur();

	/* Clear class variables */
	this.milestone = null;
	this.events = null;

	/* Disconnect all signals */
	for(var i = 0; i < this._signals; i++) {
		disconnect(this._signals[i]);
	}
	this._signals = new Array();

	/* Destroy all settings objects */
	for(var i = 0; i < this._settings; i++) {
		this._settings[i].remove();
	}
	this._settings = {};

	/* Close tab */
	this.tab.close();
	this._inited = false;
}
/* ***** End Tab events ***** */

/* ***** Save and Get Settings ***** */
SettingsPage.prototype.getSetting = function(which) {
	return this._settings[which];
}

SettingsPage.prototype.saveSettings = function() {
	var values = new Object();
	for(var s in this._settings) {
		var val = this._settings[s].getValue();
		// fake option that we can't let past! (only if it's enabled)
		if(val == Setting.Options.select && this._settings[s].isEnabled()) {
			status_msg('Please select a value for "'+SettingsPage.Settings[s].name+'"', LEVEL_WARN);
			return;
		}
		values[s] = val;
	}
	user.set_settings(values, 'loud');
	signal(this, 'save');
}
/* ***** End Save and Get Settings ***** */

/* ***** Setting Object ***** */
function Setting(container, name, description, options) {
	// hold signals for the setting
	this._signals = new Array();

	// hold status message for the setting
	this._prompt = null;

	// hold the select/input/checkbox that the user will interact with
	this._field = null;

	// hold the div the setting will be shown in
	this._container = DIV({'class':'setting'});

	// hold the setting name
	this._name = name;

	// hold the setting description
	this._description = description;

	// hold setting setup options
	this._options = options;

	this._construct();
	appendChildNodes($(container), this._container);
}

/* ***** Disable, Enable, Flash, Remove the setting ***** */
Setting.prototype.isEnabled = function() {
	return this._field.disabled;
}

Setting.prototype.disable = function() {
	addElementClass(this._container, 'disabled');
	this._field.disabled = true;
}

Setting.prototype.enable = function() {
	removeElementClass(this._container, 'disabled');
	this._field.disabled = false;
}

Setting.prototype.flash = function() {
	Highlight(this._container, {transition: MochiKit.Visual.Transitions.wobble, duration: 4});
}

Setting.prototype.remove = function() {
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}

	/* Disconnect all signals */
	for(var i = 0; i < this._signals; i++) {
		disconnect(this._signals[i]);
	}

	/* Remove our elements and our handles on them */
	removeElement(this._container);
	this._container = null;
	this._field = null;
}
/* ***** End Disable, Enable, Flash, Remove the setting ***** */

/* ***** Get/Set the value on the form ***** */
Setting.prototype.getValue = function() {
	var value = this._getValue();
	// Cannot coerce the artificial option
	if (value == Setting.Options.select) {
		return Setting.Options.select;
	}
	// Coerce to boolean
	if (this._options.result == Setting.Options.bool) {
		return (value == 'true');
	}
	// No coercion
	return value;
}

Setting.prototype._getValue = function() {
	if(this._options.type == Setting.Type.checkbox) {
		return this._field.checked;
	} else if(this._options.type == Setting.Type.multiple) {
		var sel = new Array();
		for(var i=0; i < this._field.options.length; i++) {
			if(this._field.options[i].selected) {
				sel.push(this._field.options[i].value);
			}
		}
		return sel;
	} else {
		return this._field.value;
	}
}

Setting.prototype.setValue = function(value) {
	if(this.getValue() == value) {
		return;
	}
	if(this._options.type == Setting.Type.checkbox) {
		this._field.checked = (value == true);
	} else if(this._options.type == Setting.Type.multiple) {
		for(var i=0; i < this._field.options.length; i++) {
			if(inArray(this._field.options[i].value, value)) {
				this._field.options[i].selected = true;
			}
		}
	} else {
		this._field.value = value;
	}
	signal(this, 'onchange');
}
/* ***** End Get/Set the value on the form ***** */

/* ***** Setting interface constructor ***** */
Setting.prototype._construct = function() {
	log("constructing setting '"+this._name+"'.");

	var nameHeading = createDOM('h4', {}, this._name);

	var descriptionSpan = SPAN({}, this._description);

	var selectorDiv = this._createSelector();

	appendChildNodes(this._container, nameHeading, selectorDiv, descriptionSpan);
}

Setting.prototype._createSelector = function() {

	var opts = this._options;

	switch(opts.type) {
		case Setting.Type.checkbox:
			this._field = INPUT({type:'checkbox'});
			break;

		case Setting.Type.input:
			this._field = INPUT({});
			break;

		case Setting.Type.single:
		case Setting.Type.multiple:
			this._field = SELECT();
			this._field.multiple = (opts.type == Setting.Type.multiple)
			// Check the callback first -- dynamic values
			if(opts.optionsCallback != null) {
				options = opts.optionsCallback();
			} else if(opts.options != null) {
				options = opts.options;
			} else {
				break;
			}
			// Optionally add a 'Please select' option that may not be selected
			if(opts['default'] == Setting.Options.select) {
				this._addSelectOption();
			}
			// It's a plain Array
			if(options.length != null) {
				for(var i=0; i < options.length; i++) {
					appendChildNodes(this._field, OPTION({}, options[i]));
				}
			} else {	// It's a hash-table
				for(var k in options) {
					appendChildNodes(this._field, OPTION({value:k}, options[k]));
				}
			}
			break;
	}

	if(opts['default'] != null) {
		this.setValue(opts['default']);
	}

	if(opts.dependsUpon != null) {
		this._setupDepends();
		this._checkDepends();
	}

	return DIV({}, this._field);

}

/* Adds a dummy "Please select" option that gets removed once it's changed */
Setting.prototype._addSelectOption = function() {
	appendChildNodes(this._field, OPTION({value:Setting.Options.select}, 'Please select'));
	this.setValue(Setting.Options.select);
	this._signals.push(connect( this, 'onchange', bind(this._removeSelectOption, this) ));
}

/* Removes the dummy "Please select" option once it's changed */
Setting.prototype._removeSelectOption = function() {
	if (this.getValue() == Setting.Options.select) {
		return;
	}
	for(var i=0; i < this._field.options.length; i++) {
		if(this._field.options[i].value == Setting.Options.select) {
			removeElement(this._field.options[i]);
		}
	}
}

Setting.prototype._setupDepends = function() {
	var depends = this._options.dependsUpon;
	if (depends.callBack != null) {
		return;
	} else if (depends.setting != null) {
		this._signals.push(connect(
			SettingsPage.GetInstance().getSetting(depends.setting),
			'onchange',
			bind(this._checkDepends, this)
		));
	}
}

Setting.prototype._checkDepends = function() {
	var depends = this._options.dependsUpon;
	var active = false;
	if (depends.callBack != null) {
		active = depends.callBack();
	} else if (this._options.dependsUpon.setting != null) {
		var actualValue = SettingsPage.GetInstance().getSetting(depends.setting).getValue();
		active = ( (depends.valueEq != null && depends.valueEq == actualValue)
		        || (depends.valueNeq != null && depends.valueNeq != actualValue)
		         )
	}
	if (active) {
		this.enable();
	} else {
		this.disable();
	}
}
/* ***** End Setting interface constructor ***** */

/* ***** Setting interface signals ***** */
Setting.prototype.__connect__ = function(ident, signal, objOrFunc, funcOrStr) {
	this._signals.push(ident);
	this._signals.push(connect(this._field, signal, objOrFunc, funcOrStr));
}

Setting.prototype.__disconnect__ = function(ident, signal, objOrFunc, funcOrStr) {
	disconnect(ident);
	disconnect(this._field, signal, objOrFunc, funcOrStr);
	for(var i=0; i < this._signals.length; i++) {
		if(this._signals[i] == ident) {
			disconnectAll(this._signals.splice(i,2));
			break;
		}
	}
}
/* ***** End Setting interface signals ***** */

/* ***** End Setting Object ***** */


/* ***** useful function mimicking a PHP one ***** */
function inArray(val, arr) {
	for(var i=0; i < arr.length; i++) {
		if(arr[i] == val) {
			return true;
		}
	}
	return false
}

/* ***** Enum-like things ***** */

function Enum(id, arr) {
	var e = {};
	for(var i=0; i < arr.length; i++) {
		e[arr[i]] = id+i+arr[i];
	}
	return e;
}

Setting.Options = Enum('Setting.Options', [
	'bool', // Force the result to approximate a boolean by checking against 'true'
	'select' // Provide as the default a `Please select` option that forces a user choice.
	         // Only valid for single or multiple type settings.
]);

Setting.Type = Enum('Setting.Type', [
	'checkbox', // checkbox
	'input', // free input field
	'multiple', // multiple select field
	'single' // single select field
]);


/* ***** Actual available user settings ***** */
SettingsPage.Settings = {
	'export.usejava' : {
		name: 'File export mechanism',
		description: 'Use the automatic Java file export system or not. This system automatically finds and saves the project to the correct location on your usb stick. Otherwise you get a save file dialogue, asking you to find the usb stick manually',
		options: {
			'default': Setting.Options.select,
			dependsUpon: {callBack: function(){return navigator.javaEnabled();}},
			type: Setting.Type.single,
			result: Setting.Options.bool,
			options: { 'true': 'Use Java', 'false': "Don't use Java" }
		}
	},
	'project.autoload' : {
		name: 'Project Autoload',
		description: 'Whether or not to automatically select a project when you login to the IDE.',
		options: {
			'default': 'Last selected',
			type: Setting.Type.single,
			options: {'project.last':'Last selected', 0:'None', 'project.load':'Specify manually'}
		}
	},
	'project.load' : {
		name: 'Project to load',
		description: 'Which project to load when you login to the IDE.',
		options: {
			dependsUpon: {setting: 'project.autoload', valueEq:'project.load'},
			type: Setting.Type.single,
			// TODO: Fix the below not to use a private property!
			optionsCallback: function(){ return projpage._list.projects }
		}
	},
	'team.autoload' : {
		name: 'Team Autoload',
		description: 'Whether or not to automatically select a team when you login to the IDE.',
		options: {
			'default': 'Last selected',
			type: Setting.Type.single,
			options: {'team.last':'Last selected', 0:'None', 'team.load':'Specify manually'}
		}
	},
	'team.load' : {
		name: 'Team to load',
		description: 'Which team to load when you login to the IDE.',
		options: {
			dependsUpon: {setting: 'team.autoload', valueEq:'team.load'},
			type: Setting.Type.single,
			optionsCallback: function(){ return user.team_names }
		}
	}
};
/* ***** End Actual available user settings ***** */
