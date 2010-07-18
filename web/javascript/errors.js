function ErrorsPage() {

	//hold the tab object
	this.tab = null;
	//Array of ErrorFiles
	this.eflist = new Array();

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
	this.load = function(info, opts) {
		if(this._prompt != null) {
			this._prompt.close();
			this._prompt = null;
		}
		var path = info.path+'/';
		var filelist = new Array();
		var module_name = this._module_name(info.file);
		this._init();
		log('Loading the ErrorPage');
		logDebug('module_name:'+module_name+'|');

		//if we've simply got an import or syntax error, not a dud function etc
		if(info.messages[0].split(':')[0] == module_name && info.messages[0].split(':')[2] == " NOT PROCESSED UNABLE TO IMPORT") {
			//grab the error type
			var type = info.err[0].split(':')[0].replace(new RegExp("^\\s*", 'g'),'').split(' ')[0];
			var orig = info.err.join('\n\r');
			logDebug('type:'+type+'|');

			//prep the variables
			useful_line = null;
			var code = null;
			var marker = null;
			var file = info.file;
			var line = 0;

			if( type == 'Caught' ) {	//an exception whlie loading module
				var new_type = info.err[info.err.length-1].split(':')[0].substr(2);
				if( new_type == 'IndentationError' )
				type = new_type;
			}

			type = type.slice(0, -5);
			logDebug('type:'+type+'|');
			switch(type) {
				case 'Syntax':
					useful_line = info.err[info.err.length-3];
					code	= info.err[1].substr(4);
					marker	= info.err[2].substr(4);
					line	= useful_line.split(', line ')[1].split(')')[0];
					file	= useful_line.split('(')[1].split(', line ')[0];
					break;
				case 'Import':
					useful_line = info.err[0];
					code = useful_line.split(':')[1];
					file = info.file;
					break;
				case 'Indentation':
					useful_line = info.err[info.err.length-1];
					file = useful_line.split('(')[1].split(', line')[0];
					line = useful_line.split(', line ')[1].split(')')[0];
					code = useful_line.split(':')[1].split(' (')[0];
					break;
				default:
					type = info.err[info.err.length-1].split(':')[0].substr(2);
					useful_line = info.err[info.err.length-3];
					file = useful_line.split('File "')[1].split('", line ')[0];
					line = useful_line.split(', line ')[1].split(', in ')[0];
					code = info.err[info.err.length-2];
					marker = info.err[info.err.length-1].split(':')[1];
					break;
			}

			file = path+file;
			type += ' Error:\n';

			logDebug('file:'+file+'| line:'+line+'| code:'+code+'| marker:'+marker+'|');

			if(this.eflist[file] == null)
				this.eflist[file] = new ErrorFile(file);

			this.eflist[file].load_error(type, line, code, marker, orig);

		} else {
			for(var i = 0; i < info.messages.length; i++) {
				var msg = info.messages[i].split(':');
				var file = path+msg[0];

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

				this.eflist[file].add_warn({ 'line' : msg[1], 'comment' : msg[2] });
			}
			this.eflist[file].load_warns();
		}

		if(opts != null) {
			if(opts.switch_to)
				tabbar.switch_to(this.tab);
			if(opts.alert)
				this._prompt = status_button( info.messages.length+" errors found!", LEVEL_WARN, 'view errors',
					bind( tabbar.switch_to, tabbar, this.tab ) );
		}
	}

	this._module_name = function(n) {
		if(n.substr(n.length-3) == '.py')
			return n.slice(0, -3);
		return n;
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
		for( f in this.eflist ) {
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
		for( f in this.eflist ) {
			if(this.eflist[f] != null)
				this.check(f);
		}
	}

	this.check = function(file, opts) {
		if(opts != null && opts.code != null) {
			var d = postJSONDoc("./checkcode", {
				queryString : { 'team' : team, 'path' : file, 'date': new Date().getTime() },
				sendContent : {'code' : opts.code }});
			opts.code = '';	//no need for it later on, so save the memory
		} else
			var d = loadJSONDoc("./checkcode", { 'team' : team, 'path' : file, 'date': new Date().getTime() });

		d.addCallback( partial(bind(this._done_check, this), file, opts) );
		d.addErrback( bind(this._fail_check, this, file, opts) );
	}

	this._done_check = function(file, opts, info) {
		var cb = ( opts != null && opts.callback != null && typeof opts.callback == 'function' )
		if( info.errors == 1 ) {
			this.load(info, opts);
			if(cb) {
				opts.callback('codefail', info.errors);
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

		for( i in this.eflist ) {
			if(this.eflist[i] != null)
				this.eflist[i].remove();
		}
		this.eflist = new Array();

		this.tab.close();

		if( this._prompt != null ) {
			this._prompt.close();
			this._prompt = null;
		}

		for(var i = 0; i < this._signals; i++) {
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
		for( i in this.eflist ) {
			this.eflist[i].hide_errs();
		}
	}
}

function ErrorFile(name) {
	//the path of this file
	this.label = name;

	//array for the warnings in a file
	this._warns = new Array();

	//the HTML element for the title
	this._name_elem = null;
	//the HTML element for the warnings
	this._warn_elem = null;
	//the HTML element for the errors
	this._err_elem = null;
	//the HTML element for all messages (errors and warnings)
	this._msgs_elem = null;
	//are the errors shown
	this._msgs_shown = true;

	//hold signals for the page
	this._signals = new Array();
	//hold the signal for showing the error original
	this._err_orig_signal = null;

	this._init = function() {
		logDebug('initing file: '+this.label);

		//make the html
		this._view_link = A({"title":'click to view file', 'href':'#'} , this.label);
		this._expand_elem = createDOM('button', {'file':this.label}, 'Collapse');
		this._refresh_elem = createDOM('button', {'file':this.label, 'title':'Click to re-check the current saved version of the file'}, 'Check Again');
		this._name_elem = createDOM('dt', null, this._view_link, this._refresh_elem, this._expand_elem );
		this._warn_elem = UL(null, null);
		this._err_elem = UL(null, null);
		this._msgs_elem = createDOM('dd', {'file':this.label}, this._warn_elem, this._err_elem);

		//add the html to the page
		appendChildNodes("errors-listing", this._name_elem);
		appendChildNodes("errors-listing", this._msgs_elem);

		//hook up the signal
		this._signals.push(connect( this._view_link, 'onclick', bind(this._view_onclick, this) ));
		this._signals.push(connect( this._expand_elem, 'onclick', bind(this._expand_onclick, this) ));
		this._signals.push(connect( this._refresh_elem, 'onclick', bind(errorspage.check, errorspage, this.label, null) ));
	}

	this.add_warn = function(w) {
		this._warns.push(w)
	}

	this.load_warns = function() {
		for( var i=0; i<this._warns.length; i++ ) {
			var w = this._warns[i];
			appendChildNodes( this._warn_elem, LI({"line" : w.line}, ''+w.line+':'+w.comment) );
		}
		this.show_msgs();
	}

	this.load_error = function(type, line, code, marker, orig) {
		if(code == null || code == '')
			code = ' ';
		//build up the html output
		var title = SPAN(null, type);
		var code_num = createDOM('dt', null, line);
		var code_line = createDOM('dd', null, code);
		code = createDOM('dl', null, code_num, code_line);

		if(marker != null) {
			var mark_num = createDOM('dt', null, '');
			var mark_line = createDOM('dd', null, marker);
			appendChildNodes(code, mark_num, mark_line);
		}

		var li = LI({'title':'click to show/hide original'}, title, code);

		disconnect(this._err_orig_signal);
		this._err_orig_signal = connect( li, 'onclick', bind(this._toggle_show_error_orig, this) );

		//throw the html to the screen
		replaceChildNodes( this._err_elem, li, LI( {'class' : 'orig hide'}, orig) );
		this.show_msgs();
	}

	this.reset = function() {
		this.clear_errors();
		this.clear_warns();
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
			disconnectAll(this._signals[i]);
		}
	}

	this.clear_warns = function() {
		if(this._warn_elem != null)
			replaceChildNodes(this._warn_elem, null);
		this._warns = new Array();
	}

	this.clear_errors = function() {
		if(this._err_elem != null)
			replaceChildNodes(this._err_elem, null);
		this.errors = new Array();
	}

	this._toggle_show_error_orig = function() {
		toggleElementClass( 'hide', getFirstElementByTagAndClassName('li', 'orig',  this._err_elem));
	}

	this._view_onclick = function() {
		editpage.edit_file( team, projpage.project, this.label, 'HEAD', 'REPO' );
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
