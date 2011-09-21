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

	//store file path
	this.file = '';

	//store newer file revision
	this.revhash = -1;

	// whether or not this is a patch from the log
	this.revhash = null;
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

/* *****	Diff loading Code	***** */
DiffPage.prototype._recieveDiff = function(nodes) {
	replaceChildNodes('diff-page-diff');
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
			appendChildNodes('diff-page-diff', DIV({'class': modeclasses[mode]}, group));
			mode = line.substring(0,1);
			group = line+'\n';
		}
	}
	var description;
	if (this.logpatch) {
		description = 'applied by log revision';
	} else {
		description = 'from your modifications, based on';
	}
	description += ' '+IDE_hash_shrink(this.revhash);
	$('diff-page-summary').innerHTML = 'Displaying differences on '
			+this.file+' '+description;
	this.init();
}

DiffPage.prototype._errDiff = function(rev, code, nodes) {
	status_button("Error retrieving diff", LEVEL_WARN, "Retry", bind(this.diff, this, this.file, rev, code));
}

DiffPage.prototype.diff = function(file, rev, code) {
	this.file = file;
	this.revhash = rev;
	var recieve = bind( this._recieveDiff, this );
	var err = bind( this._errDiff, this, rev, code );

	var args = {
		   team: team,
		project: IDE_path_get_project(file),
		   path: IDE_path_get_file(file),
		   hash: rev
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
