// Tab: A single tab.
function Tab(label, opts) {
	//some tab properties, set the defaults here
	this.label = label;
	this.title = 'Show the '+this.label+' tab';
	//whether the tab gets a close button on it
	this.can_close = true;
	//see if we've been given any options
	if(opts != null) {
		if( opts.title != null )	//the tab title
			this.title = opts.title;

		if( opts.can_close != null )	//can the tab be closed
			this.can_close = opts.can_close;
	}

	// Whether this tab can be focussed
	// Setting this to false prevents focus being shifted to the tab,
	// and clicking on it will only trigger the "onclick" signal.
	this.can_focus = true;

	// The list item that we are
	this._li = null;
	// The link
	this._a = null;

	// Whether we have focus
	this._focus = false;

	this._init = function() {
		if(this.can_close) {
			this._a_close = A( {"href":'#', "class":'close', "title":'Close this tab'}, 'X' );
			connect(this._a_close, 'onclick', bind(this._clickClose,this) );
		} else
			this._a_close = null;
		this._a = A( {"href":'#', "title": this.title}, this.label, this._a_close );
		this._li = LI({"class": 'nofocus'}, this._a );

		appendChildNodes($("tab-list"), this._li);

		connect(this._li, 'onclick', bind(this._onclick,this) );
	}

	this._onclick = function(ev) {
		kill_event(ev);
		signal( this, "onclick", this );
	}

	//someone clicked the close button on the tab
	this._clickClose = function(ev) {
		kill_event(ev);
		signal(this, "onclickclose", this);
	}

	// Called to tell find if the tab has focus
	this.has_focus = function() {
		return this._focus;
	}

	// Called to tell the tab it has focus
	this.got_focus = function() {
		removeElementClass( this._li, "nofocus" );
		addElementClass( this._li, "focus" );

		if( !this._focus ) {
			logDebug( "tab \"" + this.label + "\" focussed" );
			this._focus = true;
			signal( this, "onfocus", this );
		}
	}

	// Called to tell the tab it no longer has focus
	this.lost_focus = function() {
		removeElementClass( this._li, "focus" );
		addElementClass( this._li, "nofocus" );

		if( this._focus ) {
			logDebug( "tab \"" + this.label + "\" blurred" );
			this._focus = false;
			signal( this, "onblur", this );
		}
	}

	//close the tab
	this.close = function() {
		signal( this, "onclose", this );

		disconnectAll(this);
		removeElement( this._li );
	}

	this.flash = function() {
		Highlight( this._a,
				{ 'startcolor' : '#ffff31',
				  'endcolor' : this._a.style.backgroundColor,
				  'afterFinish' : bind(this._remove_flash_style, this)
				});
	}

	this._remove_flash_style = function() {
		status_msg( "TODO: Remove flash style", LEVEL_WARN );
	}

	this.set_label = function( l ) {
		this.label = l;
		replaceChildNodes(this._a, l, this._a_close);
	}

	this._init();
}


// TabBar: Managers tabs
// Calls show and hide functions as appropriate
function TabBar() {
	this.tabs = [];

	// Current tab with focus
	this._curtab = null;

	// Member functions
	this.add_tab = function( tab ) {
		this.tabs.push( tab );
		connect( tab, "onclick", bind( this._onclick, this ) );
		connect( tab, "onclose", bind( this._onclose, this ) );
	}

	this.switch_to = function( tab ) {
		// Don't do anything if the tab can't focus
		if( !tab.can_focus )
			return false;

		if( tab != this._curtab ) {
			if( this._curtab != null )
				this._curtab.lost_focus();

			var fromtab = this._curtab;
			this._curtab = tab;

			// Signal that the tab has been switched
			signal( this, "onswitch", fromtab, tab );

			tab.got_focus();
			return true;
		}
		return false;
	}

	// Force the tab to be unfocussed and then focussed
	this.force_refresh = function( tab ) {
		// Don't do anything if the tab can't focus
		if( !tab.can_focus )
			return;

		if( tab._curtab == tab )
			tab._curtab = null;

		this.switch_to( tab );
	}

	// find the list number of the current tab
	this._find_curtab_id = function() {
		for( var i=0; i<this.tabs.length; i++) {
			if( this.tabs[i].has_focus() )
				return i;
		}
		return -1;
	}

	// go to the next tab along, if possible
	this.next_tab = function() {
		log('switching to the next tab');
		var curtab_id = this._find_curtab_id();
		if(curtab_id > -1) {
			for(curtab_id++; curtab_id<this.tabs.length; curtab_id++) {
				logDebug('curtab_id:'+curtab_id);
				if(this.switch_to(this.tabs[curtab_id]))
					break;
			}
		}
	}

	// go to the previous tab along, if possible
	this.prev_tab = function() {
		log('switching to the previous tab');
		var curtab_id = this._find_curtab_id();
		if(curtab_id > 0) {
			for(curtab_id--; curtab_id>-1; curtab_id--) {
				logDebug('curtab_id:'+curtab_id);
				if(this.switch_to(this.tabs[curtab_id]))
					break;
			}
		}
	}

	// Handler for tab onclick events
	this._onclick = function( tab ) {
		this.switch_to( tab );
	}

	// Handler for closing a tab
	this._onclose = function( tab ) {
		var index = 0;

		for( var i in this.tabs ) {
			if( this.tabs[i] == tab ) {
				index = i;
				this.tabs.splice( i, 1 );
				break;
			}
		}

		if(tab.has_focus()) {	//we only need to change focus if the tab being removed has focus

			index--;
			if( index < 0 ) index = 0;

			// Try tabs to the left:
			for( var i = index; i >= 0; i-- )
				if( this.tabs[i].can_focus ) {
					index = i;
					break;
				}

			// Try tabs to the right:
			for( var i = index; i < this.tabs.length; i++ ) {
				if( this.tabs[i].can_focus ) {
					index = i;
					break;
				}
			}

			if( this.tabs[i].can_focus ) {
				logDebug( "Switching to tab index " + index );
				this.switch_to( this.tabs[index] );
			}
		}
	}

	//close all the tabs by signalling that their close button was clicked on
	//check that all the edit tabs are closed: return true if so, false otherwise
	this.close_all_tabs = function() {
		for( var i in this.tabs ) {
			if( this.tabs[i].can_close )
				signal(this.tabs[i], "onclickclose");
		}
		return editpage != null && editpage.close_all_tabs(false);
	}
}
