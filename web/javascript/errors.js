function ErrorsPage() {

	//hold the tab object
	this.tab = null;
	//Array of ErrorFiles
	this.eflist = new Object();

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	this._inited = false;

	this._init = function() {
		if(this._inited)
			return;

		this.tab = new Tab( "Errors" );
		connect( this.tab, "onfocus", bind( this._onfocus, this ) );
		connect( this.tab, "onblur", bind( this._onblur, this ) );
		connect( this.tab, "onclickclose", bind( this._close, this ) );

		tabbar.add_tab( this.tab );

		this._signals.push(connect("close-errors-page", "onclick", bind(this._close, this) ));
		this._signals.push(connect("collapse-errors-page", "onclick", bind(this._collapse_all, this) ));
		this._signals.push(connect("check-errors-page", "onclick", bind(this._check_all, this) ));
		this._signals.push(connect("expand-errors-page", "onclick", bind(this._expand_all, this) ));

		this._inited = true;
	}

	//load a new set of errors
	this.load = function(info, opts, project) {
		if(this._prompt != null) {
			this._prompt.close();
			this._prompt = null;
		}
		var path = '/' + project + '/';
		var filelist = new Array();
		this._init();
		log('Loading the ErrorPage');

		for(var i = 0; i < info.errors.length; i++) {
			var item = info.errors[i];
			var file = path + item.file;

			//if we've not seen this file before, but it has a listing already, reset it
			if(findValue(filelist, file) == -1 && this.eflist[file] != null) {
				log('Resetting '+file);
				this.eflist[file].reset();
			} else if(this.eflist[file] == null) {	//if it's null the initialise it
				this.eflist[file] = new ErrorFile(file);
				log('file '+file+' has been added');
			}
			if(findValue(filelist, file) == -1)	//add it to our list if it's not there
				filelist.push(file);

			this.eflist[file].add_item(item);
		}
		for(var i = 0; i < filelist.length; i++) {
			this.eflist[filelist[i]].load_items();
		}

		if(opts != null) {
			if(opts.switch_to)
				tabbar.switch_to(this.tab);
			if(opts.alert)
				this._prompt = status_button( info.errors.length+" errors found!", LEVEL_WARN, 'view errors',
					bind( tabbar.switch_to, tabbar, this.tab ) );
		}
	}

	this._expand_all = function() {
		for( var i in this.eflist ) {
			this.eflist[i].show_msgs();
		}
	}

	this._collapse_all = function() {
		for( var i in this.eflist ) {
			this.eflist[i].hide_msgs();
		}
	}

	this._onfocus = function() {
		setStyle( $("errors-page"), {"display":"block"} );
	}

	this._onblur = function() {
		setStyle( $("errors-page"), {"display":"none"} );
	}

	this._file_count = function() {
		var count = 0;
		for( var f in this.eflist ) {
			if(this.eflist[f] != null)
				count++;
		}
		return count;
	}

	this._clear_file = function(file) {
		if(this.eflist[file] != null) {
			this.eflist[file].remove();
			this.eflist[file] = null;
		}
		if(this._file_count() == 0)
			this._close();
	}

	this._check_all = function() {
		for( var f in this.eflist ) {
			if(this.eflist[f] != null)
				this.check(f);
		}
	}

	/**
	 * use autosave parameter if to check against autosave or normal save
	 */
	this.check = function(file, opts, autosave, revision) {
		var project = IDE_path_get_project(file)
		IDE_backend_request("file/lint", {team: team,
		                                  project: project,
		                                  path: IDE_path_get_file(file),
		                                  rev: revision,
		                                  autosave: autosave
		                                 },
		                                  partial(bind(this._done_check, this), file, opts, project),
		                                  bind(this._fail_check, this, file, opts));
	}

	this._done_check = function(file, opts, project, info) {
		var cb = ( opts != null && opts.callback != null && typeof opts.callback == 'function' )
		if( info.errors.length > 0 ) {
			this.load(info, opts, project);
			if(cb) {
				opts.callback('codefail', info.errors.length);
			}
		} else {
			if(cb) {
				opts.callback('pass');
			}
			//if not (quiet if pass or a mulifile call from the projpage and no errors yet and this is not the last one to check)
			if( !( opts != null && (opts.quietpass || opts.projpage_multifile && projtab.has_focus() && async_count > 1) ) )
				this._prompt = status_msg( "No errors found", LEVEL_OK );
			this._clear_file(file);
		}
	}

	this._fail_check = function(file, opts) {
		this._prompt = status_button( "Failed to check code", LEVEL_ERROR,
					"retry", bind( this.check, this, file, opts ) );

		//run the callback, after our status message so they can overwrite it if they desire
		if( opts != null &&  opts.callback != null && typeof opts.callback == 'function' )
			opts.callback('checkfail');
	}

	this._close = function() {
		if(!this._inited)
			return;

		for( var i in this.eflist ) {
			if(this.eflist[i] != null)
				this.eflist[i].remove();
		}
		this.eflist = new Array();

		this.tab.close();

		if( this._prompt != null ) {
			this._prompt.close();
			this._prompt = null;
		}

		for(var i = 0; i < this._signals.length; i++) {
			disconnect(this._signals[i]);
		}
		this._signals = new Array();

		this._inited = false;
	}

	this.show_only = function(file) {
		this.hide_all_files();
		this.eflist[file].show_errs();
	}

	this.hide_all_files = function() {
		for( var i in this.eflist ) {
			this.eflist[i].hide_errs();
		}
	}
}

function ErrorFile(name) {
	//the path of this file
	this.label = name;

	//array for the warnings in a file
	this._items = new Array();

	//the HTML element for the title
	this._name_elem = null;
	//the HTML element for the warnings
	this._items_elem = null;
	//the HTML element for all messages (errors and warnings)
	this._msgs_elem = null;
	//are the errors shown
	this._msgs_shown = true;

	//hold the main signals for the file
	this._signals = new Array();
	//hold the signals for double-clicking on the items
	this._item_signals = new Array();

	this._init = function() {
		logDebug('initing file: '+this.label);

		//make the html
		this._view_link = A({"title":'click to view file', 'href':'#'} , this.label);
		this._expand_elem = createDOM('button', null, 'Collapse');
		this._refresh_elem = createDOM('button', {'title':'Click to re-check the current saved version of the file'}, 'Check Again');
		this._name_elem = createDOM('dt', null, this._view_link, this._refresh_elem, this._expand_elem );
		this._items_elem = UL(null, null);
		this._msgs_elem = createDOM('dd', null, this._items_elem);

		//add the html to the page
		appendChildNodes("errors-listing", this._name_elem);
		appendChildNodes("errors-listing", this._msgs_elem);

		//hook up the signal
		this._signals.push(connect( this._view_link, 'onclick', bind(this._view_onclick, this, null) ));
		this._signals.push(connect( this._expand_elem, 'onclick', bind(this._expand_onclick, this) ));
		this._signals.push(connect( this._refresh_elem, 'onclick', bind(errorspage.check, errorspage, this.label, null, false) ));
	}

	this.add_item = function(w) {
		this._items.push(w)
	}

	this.load_items = function() {
		for( var i=0; i<this._items.length; i++ ) {
			var item = this._items[i];
			var li = LI({'class' : item.level}, ''+item.lineNumber+':'+' ['+item.level.charAt(0).toUpperCase()+'] '+item.message);
			li.title = 'Double click to view the error in the file.';
			this._item_signals.push(connect( li, 'ondblclick', bind(this._view_onclick, this, item.lineNumber) ));
			appendChildNodes( this._items_elem, li );
		}
		this.show_msgs();
	}

	this.reset = function() {
		this.clear_items();
	}

	this.remove = function() {
		this.reset();
		if(this._name_elem != null)
			removeElement(this._name_elem);
		if(this._msgs_elem != null)
			removeElement(this._msgs_elem);
		this._msgs_elem = null;
		this._name_elem = null;
		for( var i in this._signals ) {
			disconnect(this._signals[i]);
		}
	}

	this.clear_items = function() {
		if(this._items_elem != null)
			replaceChildNodes(this._items_elem, null);
		this._items = new Array();
		for( var i=0; i<this._item_signals.length; i++ ) {
			disconnect(this._item_signals[i]);
		}
		this._item_signals = new Array();
	}

	this._view_onclick = function(line) {
		var etab = editpage.edit_file( team, IDE_path_get_project(this.label), this.label, null, 'REPO' );
		if (etab.rev != 'HEAD') {
			etab.open_revision('HEAD');
		}
		if (line != null) {
			etab.setSelectionRange(line, 0, -1);
		}
	}

	this._expand_onclick = function() {
		if(!this._msgs_shown)
			this.show_msgs();
		else
			this.hide_msgs();
	}

	this.show_msgs = function() {
		if(!this._msgs_shown) {
			showElement( this._msgs_elem );
			this._expand_elem.innerHTML = 'Collapse';
			this._msgs_shown = true;
		}
	}

	this.hide_msgs = function() {
		if(this._msgs_shown) {
			hideElement( this._msgs_elem );
			this._expand_elem.innerHTML = 'Expand';
			this._msgs_shown = false;
		}
	}

	this._init();
}
