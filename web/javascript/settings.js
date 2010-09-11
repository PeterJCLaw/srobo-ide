function SettingsPage() {
	// hold the tab object
	this.tab = null;

	// hold signals for the page
	this._signals = new Array();

	// hold status message for the page
	this._prompt = null;

	// hold list of available Setting objects
	this._settings = null;

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

		// Init each of the SettingsPage

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
	this._settings = new Array();

	/* Close tab */
	this.tab.close();
	this._inited = false;
}
/* ***** End Tab events ***** */

/* ***** Save Settings ***** */
SettingsPage.prototype.saveSettings = function() {
	// do something to save the settings in here!
}
/* ***** End Save Settings ***** */

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

/* ***** Disable, Enable, Remove the setting ***** */
Setting.prototype.disable = function() {
	addElementClass(this._container, 'disabled');
	this._field.disabled = true;
}

Setting.prototype.enable = function() {
	removeElementClass(this._container, 'disabled');
	this._field.disabled = false;
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
/* ***** End Disable, Enable, Remove the setting ***** */

/* ***** Get/Set the value on the form ***** */
Setting.prototype.getValue = function() {
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
}
/* ***** End Get/Set the value on the form ***** */

/* ***** Setting interface constructor ***** */
Setting.prototype._construct = function() {
	log("constructing setting '"+this._name+"'.");

	var nameHeading = createDOM('h5', {}, this._name);

	var descriptionSpan = SPAN({}, this._description);

	var selectorDiv = this._createSelector();

	appendChildNodes(this._container, nameHeading, descriptionSpan, selectorDiv);
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
			for(i=0; i < opts.options.length; i++) {
				appendChildNodes(this._field, OPTION({}, opts.options[i]));
			}
			break;
	}

	return DIV({}, this._field);

}
/* ***** End Setting interface constructor ***** */

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

function Enum(arr) {
	e = {};
	for(var i=0; i < arr.length; i++) {
		e[arr[i]] = i+arr[i]+i;
	}
	return e;
}

Setting.Type = Enum([
	'bool', // checkbox
	'input', // free input field
	'multiple', // multiple select field
	'single' // single select field
]);
