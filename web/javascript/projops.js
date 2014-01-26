// vim: noexpandtab

//handles all 'selection operations' in sidebar of project page
function ProjOps() {

	//view_log()                    for each item selected in file list it will attempt to open a new log tab
	//receive_newfolder([])         ajax success handler
	//error_receive_newfolder()     ajax fail hanlder
	//newfolder()                   gets folder name & location and instigates new folder on server

	//list of operations
	this.ops = new Array();

	this._read_only = false;

	this.init = function() {
		//connect up operations
		for(var i=0; i < this.ops.length; i++) {
			var action = bind(this._handler, this, this.ops[i]);
			this.ops[i].event = connect(this.ops[i].handle, 'onclick', action);
		}
	}

	this.set_readonly = function(isReadOnly) {
		if (this._read_only != isReadOnly) {
			var setClass = isReadOnly ? addElementClass : removeElementClass;
			for (var i=0; i < this.ops.length; i++) {
				var op = this.ops[i];
				if (op.isWrite) {
					setClass(op.handle, 'disabled');
				}
			}
		}
		this._read_only = isReadOnly;
	}

	this._handler = function(operation, ev) {
		if (operation.isWrite && this._read_only) {
			kill_event(ev);
			return false;
		}
		operation.action();
	}

	this.view_log = function() {
		//for every file that is selected:
		if(projpage.flist.selection.length == 0) {
			status_msg("No file/folders selected", LEVEL_WARN);
			return;
		}
		for(var i = 0; i < projpage.flist.selection.length; i++) {
			//try to find log file in tabbar
			var exists = map(function(x) {
				if(x.label == "Log: "+projpage.flist.selection[i]) {
					return true;}
				else { return false; }
			}, tabbar.tabs);
			var test = findValue(exists, true);
			//if already present, flash it but don't open a new one
			if(test > -1) {
				tabbar.tabs[test].flash();
			} else { //not present, open it
				var cow = new Log(projpage.flist.selection[i], projpage.project);
			}
		}
	}

	this.receive_newfolder = function(nodes) {
		logDebug("Add new folder: ajax request successful");
		switch(nodes.success) {
			case 1:
				status_msg(nodes.feedback, LEVEL_OK);
				projpage.flist.refresh();
				break;
			case 0:
				status_msg(nodes.feedback, LEVEL_ERROR);
				break;
		}
	}

	this.error_receive_newfolder = function(new_name, new_message) {
		logDebug("Add new folder: ajax request failed");
		status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.new_folder, this, new_name, new_msg) );
	}

	this.new_folder = function(new_name, new_msg) {
		if(!projpage.projects_exist()) {
			status_msg("You must create a project before creating a folder", LEVEL_ERROR);
			return;
		}
		logDebug("Add new folder: "+new_name+" ...contacting server");
		if(new_name == null || new_name == undefined) {
			var browser = new Browser(bind(this.new_folder, this), {'type' : 'isDir'});
		} else {
			IDE_backend_request("file/mkdir",
                                { team : team,
						          path : IDE_path_get_file(new_name),
                                  project:IDE_path_get_project(new_name)
						        },
                                    bind(this.receive_newfolder,this),
                                    bind(this.error_receive_newfolder, new_name, new_msg,this)
                                );

		}
	}

	this._mv_success = function(nodes) {
		logDebug("_mv_success()");
		status_msg("Move successful!", LEVEL_OK);
		projpage.flist.refresh();
	}

	this._mv_cback = function(dest, cmsg) {
		var src = projpage.flist.selection[0];
		var type = null;

		//is it a file or a folder?
		if(src.indexOf(".") < 0) { type = 'isDir'; }
		else { type = 'isFile'; }

		//do we already have a move to location?
		logDebug("type "+type);
		if(dest == "" || dest == null) {
			logDebug("launch file browser to get move destination");
			var b = new Browser(bind(this._mv_cback, this), {'type' : 'isFile'});
			return;
		} else {
			//do some sanity checking
			switch(type) {
				case 'isFile' :
					if(dest.indexOf(".") < 0) {
						status_msg("Move destination file must have an extension", LEVEL_ERROR);
						return;
					}
					break;
				case 'isDir' :
					if(dest[dest.length-1] == "/") {
						dest = dest.slice(0, dest.length-2);
					}
					if(dest.indexOf(".") > 0) {
						status_msg("Move destination must be a folder", LEVEL_ERROR);
						return;
					}
					break;
			}
		}

		status_msg("About to do move..."+src+" to "+dest, LEVEL_OK);

		IDE_backend_request("file/mv", {
				 "project": IDE_path_get_project(src),
				    "team": team,
				 "message": cmsg,
				"old-path": IDE_path_get_file(src),
				"new-path": IDE_path_get_file(dest)
			},
            //on move success, do a commit
			bind( function() {IDE_backend_request("proj/commit",{
                    team:team,
                    project:IDE_path_get_project(src),
                    paths:[IDE_path_get_file(src),IDE_path_get_file(dest)],
                    message:cmsg
                  },
                  //bind commit success to _mv_success
                  bind(this._mv_success,this),
                  //begin binding commit failure to move failure callback
			      bind( function () {
				            status_button( "Error moving files/folders", LEVEL_ERROR, "retry",
			                bind( this._mv_cback, this, dest, cmsg ) );
			            }, this
                  )
                  //end binding commit failure to move failure callback
                 )},
               this),
            //end move success bind
            //on move failure, show an error
			bind( function () {
					status_button( "Error moving files/folders", LEVEL_ERROR, "retry",
					                bind( this._mv_cback, this, dest, cmsg )
                                 );
                  }, this
                )
		);
	}

	this.mv = function() {
		//we can only deal with one file/folder at a time, so ignore all but the first
		if (projpage.flist.selection.length != 1) {
			status_msg("You must select a single file/folder", LEVEL_ERROR);
			return;
		}

		//the file must be closed!
		var file = projpage.flist.selection[0];
		if (!editpage.close_tab(file)) {
			log('Cannot move open file: ' + file);
			return;
		}

		var b = new Browser(bind(this._mv_cback, this), {'type' : 'isFile'});
		return;

	}

	this._cp_callback1 = function() {
			status_msg("Successful Copy", LEVEL_OK);
			projpage.flist.refresh();
	}
	this._cp_callback2 = function(fname, cmsg) {
		logDebug("copying "+projpage.flist.selection[0]+" to "+fname);
		//logDebug("team is" + team + " project is " + project);

		if(fname == null || fname=="")
			return;

		IDE_backend_request("file/cp", {
				 "project": IDE_path_get_project(projpage.flist.selection[0]),
				    "team": team,
				 "message": cmsg,
				"old-path": IDE_path_get_file(projpage.flist.selection[0]),
				"new-path": IDE_path_get_file(fname)
			},
			bind(function() {
                    logDebug("in ide backend proj commit");
                    IDE_backend_request("proj/commit", {
                                                        team:team,
                                                        project:IDE_path_get_project(projpage.flist.selection[0]),
                                                        message:cmsg,
                                                        paths:[IDE_path_get_file(fname)]
                                                      },
                                            bind(
                                                this._cp_callback1
                                                ,this
                                            ),

                                            bind( function() {
                                                status_button("Error contacting server", LEVEL_ERROR, "retry",
                                                bind(this._cp_callback2, this, fname, cmsg));
                                                }, this
                                            )

                 )}, this
                ),
			bind( function() {
					status_button("Error contacting server", LEVEL_ERROR, "retry",
					bind(this._cp_callback2, this, fname, cmsg));
				  }, this
                )
		);
	}
	this.cp = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected to copy", LEVEL_ERROR);
			return;
		}
		if(projpage.flist.selection.length > 1) {
			status_msg("Multiple files selected!", LEVEL_ERROR);
			return;
		}
		var b = new Browser(bind(this._cp_callback2, this), {'type' : 'isFile'});
		return;
	}
	this.rm = function(override) {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for deletion", LEVEL_ERROR);
			return;
		}
		if(override == false) {
			status_button("Are you sure you want to delete "+projpage.flist.selection.length+" selected files/folders", LEVEL_WARN, "delete", bind(this.rm, this, true));
			return;
		}

		var death_list = new Array();
		var selection = projpage.flist.selection;
		var proj_path_len = projpage.project.length + 2;
		for( var i=0; i < selection.length; i++ ) {
			death_list.push(selection[i].substr(proj_path_len))
		};

		logDebug("will delete: "+death_list);

        IDE_backend_request("file/del",
                            { "team" : team,
        				      "project" : projpage.project,
		        			  "files" : death_list
                            },
                            bind(function() {
                                                IDE_backend_request("proj/commit",
                                                                    {
                                                                        team:team,
                                                                        project:projpage.project,
                                                                        paths:death_list,
                                                                        message:"File deletion"
                                                                    },
                                                                    bind(function(){
                                                                        status_msg("files deleted succesfully", LEVEL_OK);
			                                                            projpage.flist.refresh();
                                                                    },this),
                                                                    bind(function() {
                                                                        status_button("Error contacting server",
                                                        				LEVEL_ERROR, "retry", bind(this.rm, this, true));
                                                                                    }
                                                                    ,this)
                                                                   )
                                            },this),
                                bind(function() {
                                                  status_button("Error contacting server",
                                                  LEVEL_ERROR, "retry", bind(this.rm, this, true));
                                                },this)
                           );
	}

	this.rm_autosaves = function(override) {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for deletion", LEVEL_ERROR);
			return;
		}
		if(override == false) {
			status_button("Are you sure you want to delete "+projpage.flist.selection.length+" selected Autosaves",
						LEVEL_WARN, "delete", bind(this.rm_autosaves, this, true));
			return;
		}

		var death_list = new Array();
		var selection = projpage.flist.selection;
		var proj_path_len = projpage.project.length + 2;
		for( var i=0; i < selection.length; i++ ) {
			death_list.push(selection[i].substr(proj_path_len))
		};

		log("Will delete autosaves: "+death_list);

        IDE_backend_request("file/co",
                    { "team" : team,
				      "project" : projpage.project,
				      "files" : death_list,
                      "revision":0
                    },

		            bind(function(nodes) {
            			status_msg("Deleted Autosaves", LEVEL_OK);
	    	      	    projpage.flist.refresh();
            		}),
                    bind(function() {
                        status_button("Error contacting server",
			            LEVEL_ERROR, "retry", bind(this.rm_autosaves, this, true));
                    })
        );
    }

	this._undel_callback = function(nodes) {
		status_button("Successfully undeleted file(s)",
		LEVEL_OK, 'goto HEAD', bind(projpage.flist.change_rev, projpage.flist, 'HEAD'));
	}
	this.undel = function() {
		if(projpage.flist.selection.length == 0) {
			status_msg("There are no files/folders selected for undeletion", LEVEL_ERROR);
			return;
		}

		var files = projpage.flist.selection
        var project = projpage.project

        for (var i = 0; i < files.length; i++) {
            files[i] = IDE_path_get_file(files[i])
        }

        IDE_backend_request("file/co",
                            {
                                team:team,
                                project:project,
                                files:files,
                                revision:projpage.flist.rev
                            },
                            bind(
                                function() {
                                    IDE_backend_request("proj/commit",
                                                        {
                                                            team:team,
                                                            project:project,
					                                        message : 'Undelete '+files+' to '+IDE_hash_shrink(projpage.flist.rev),
                                                            paths:files
                                                        },
                                                        bind(this._undel_callback,this),
                                                        bind(function() {
                                                                status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.undel, this, true)
                                                                             );
                                                                        }
                                                            ,this)
                                                       )
                                }
                            ,this),
                            bind(function() { status_button("Error contacting server", LEVEL_ERROR, "retry", bind(this.undel, this, true));},this)
        )

	}

	this.check_code = function() {
		var selection = projpage.flist.selection;
		if (selection.length == 0) {
			status_msg("There are no files selected for checking", LEVEL_ERROR);
			return;
		}

		for (var i=0; i < selection.length; i++) {
			var filePath = selection[i];
			if (errorspage.can_check(filePath)) {
				errorspage.check(filePath, {switch_to : true, projpage_multifile : true});
			} else {
				status_msg("Only Python files can have their syntax checked.", LEVEL_WARN);
			}
		}
	}

	// Don't use bind on external items in case they don't exist yet.
	this.ops.push({ "name" : "Select None",
			"action" : function() { projpage.flist.select_none(); },
			"handle" : getElement("proj-select-none"),
			'isWrite' : false,
			"event" : null});

	this.ops.push({ "name" : "Select All",
			"action" : function() { projpage.flist.select_all(); },
			"handle": getElement("proj-select-all"),
			'isWrite' : false,
			"event" : null});

	this.ops.push({ "name" : "New File",
			"action" : function() { editpage.new_file(); },
			"handle" : getElement("op-newfile"),
			'isWrite' : true,
			"event" : null});

	this.ops.push({ "name" : "New Directory",
			"action" : bind(this.new_folder, this, null, null),
			"handle": getElement("op-mkdir"),
			'isWrite' : true,
			"event" : null});

	this.ops.push({ "name" : "Move",
			"action" : bind(this.mv, this),
			"handle": getElement("op-mv"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Copy",
			"action" : bind(this.cp, this),
			"handle": getElement("op-cp"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Delete",
			"action" : bind(this.rm, this, false),
			"handle": getElement("op-rm"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Undelete",
			"action" : bind(this.undel, this),
			"handle": getElement("op-undel"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Delete AutoSaves",
			"action" : bind(this.rm_autosaves, this, false),
			"handle": getElement("op-rm_autosaves"),
			'isWrite' : true,
			"event" : null });

	this.ops.push({ "name" : "Check Files' Code",
			"action" : bind(this.check_code, this),
			"handle": getElement("op-check"),
			'isWrite' : false,
			"event" : null });

	this.ops.push({ "name" : "View Log",
			"action" : bind(this.view_log, this),
			"handle": getElement("op-log"),
			'isWrite' : false,
			"event" : null });

	this.init();
}
