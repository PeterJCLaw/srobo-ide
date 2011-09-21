/// Diff page that handles showing diffs to users
function DiffPage() {
	//hold the tab object
	this.tab = null;

	//hold signals for the page
	this._signals = new Array();

	//hold status message for the page
	this._prompt = null;

	//store inited state
	this._inited = false;

	//store the diff object that's doing the rendering
	this._diff = null;
}

/* *****	Initialization code	***** */
DiffPage.prototype.init = function() {
	if(!this._inited) {
		logDebug("Diff: Initializing");

		/* Initialize a new tab for Diff - Do this only once */
		this.tab = new Tab( "File Difference" );
		this._signals.push(connect( this.tab, "onfocus", bind( this._onfocus, this ) ));
		this._signals.push(connect( this.tab, "onblur", bind( this._onblur, this ) ));
		this._signals.push(connect( this.tab, "onclickclose", bind( this._close, this ) ));
		tabbar.add_tab( this.tab );

		/* remember that we are initialised */
		this._inited = true;
	}

	/* now switch to it */
	tabbar.switch_to(this.tab);
}
/* *****	End Initialization Code 	***** */

/* *****	Tab events: onfocus, onblur and close	***** */
DiffPage.prototype._onfocus = function() {
	showElement('diff-page');
}

DiffPage.prototype._onblur = function() {
	/* Clear any prompts */
	if( this._prompt != null ) {
		this._prompt.close();
		this._prompt = null;
	}
	/* hide Diff page */
	hideElement('diff-page');
}

DiffPage.prototype._close = function() {
	/* Disconnect all signals */
	for(var i = 0; i < this._signals.length; i++) {
		disconnect(this._signals[i]);
	}
	this._signals = new Array();

	/* Close tab */
	this._onblur();
	this.tab.close();
	this._inited = false;
}
/* *****	End Tab events	***** */

/* *****	Facade the Diff objects	**** */
DiffPage.prototype._diffReady = function () {
	var description;
	if (this._diff.logpatch) {
		description = 'applied by log revision';
	} else {
		description = 'from your modifications, based on';
	}
	description += ' '+IDE_hash_shrink(this.revhash);
	$('diff-page-summary').innerHTML = 'Displaying differences on '
			+this.file+' '+description;
	this.init();
}

DiffPage.prototype.diff = function (file, rev, code) {
	this._diff = new Diff($('diff-page-diff'), file, rev);
	this._diff.makeDiff(code);
	this._signals.push(connect( this._diff, "ready", bind( this._diffReady, this ) ));
}
/* *****	End Facade the Diff objects	**** */

/// Diff object that handles drawing the diffs in a given location
function Diff(elem, file, rev) {
	// the element we're going to put the diff into
	this._elem = elem;

	// store file path
	this.file = file;

	// store newer file revision
	this.revhash = rev;

	// whether or not this is a patch from the log
	this.logpatch = null;
}

/* *****	Diff loading Code	***** */
Diff.prototype._recieveDiff = function(nodes) {
	replaceChildNodes(this._elem);
	var difflines = (nodes.diff.replace('\r','')+'\n').split('\n');
	var modeclasses = {
		' ' : '',
		'+' : 'add',
		'-' : 'remove',
		'=' : '',
		'@' : 'at'
	};
	var mode = '=';
	var group = '';
	for( var i=0; i < difflines.length; i++) {
		line = difflines[i];
		if(line.substring(0,1) == mode) {
			group += line+'\n';
		} else {
			appendChildNodes(this._elem, DIV({'class': modeclasses[mode]}, group));
			mode = line.substring(0,1);
			group = line+'\n';
		}
	}
	logDebug('diff ready, signalling');
	signal(this, 'ready');
}

Diff.prototype._errDiff = function(code) {
	status_button("Error retrieving diff", LEVEL_WARN, "Retry", bind(this.diff, this, code));
}

Diff.prototype.makeDiff = function(code) {
	var recieve = bind( this._recieveDiff, this );
	var err = bind( this._errDiff, this, code );

	var args = {
		   team: team,
		project: IDE_path_get_project(this.file),
		   path: IDE_path_get_file(this.file),
		   hash: this.revhash
	};

	if( code == undefined ) {
		this.logpatch = true;
	} else {
		args.code = code;
		this.logpatch = false;
	}

	IDE_backend_request("file/diff", args, recieve, err);
}
/* *****	End Diff loading Code	***** */
