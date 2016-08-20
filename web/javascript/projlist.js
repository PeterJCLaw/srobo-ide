// vim: noexpandtab

// Object that grabs the project list
// Signals:
//  - onchange: when the projects list changes.
//              First argument is the team number
function ProjList() {
	// Array of project names (strings)
	this.projects = [];
	// Whether we've loaded
	this.loaded = false;
	// The team
	this._team = null;

	//prompt when it errors while grabing the list
	this._err_prompt = null;

	// Member functions:
	// Public:
	//  - project_exists: Returns true if the given project exists.
	//  - update: mask of _grab_list
	// Private:
	//  - _init
	//  - _grab_list: Grab the project list and update.
	//  - _got_list: Handler for the project list response.
}

// Update the list to the given team
ProjList.prototype.update = function(team) {
	this._grab_list(team);
};

ProjList.prototype._grab_list = function(team) {
	this._team = team;

	//kill the error message, if it exists
	if (this._err_prompt != null) {
		this._err_prompt.close();
		this._err_prompt = null;
	}

	this.loaded = false;

	var failback = function() {
		this._err_prompt = status_button( "Error retrieving the project list", LEVEL_ERROR,
		                                  "retry", bind(this._grab_list, this) );
	};
	IDE_backend_request("team/list-projects", {team: team}, bind(this._got_list, this),
	                    bind(failback, this) );
};

ProjList.prototype._got_list = function(resp) {
	this.projects = resp["team-projects"];
	this.loaded = true;

	signal( this, "onchange", this._team );
};

ProjList.prototype.project_exists = function(pname) {
	logDebug('Checking project existence: '+pname+' in '+this.projects+' : '+(findValue(this.projects, pname) > -1) );
	return findValue(this.projects, pname) > -1;
};
