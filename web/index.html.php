<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
	<!-- Style Sheets -->
	<link rel="stylesheet" href="web/css/newstyle.css" type="text/css">
	<!-- Everything above here should be in that order so things don't explode,
	     but below here things matter less, so go alphabetical... -->
	<link rel="stylesheet" type="text/css" href="web/css/admin.css">
	<link rel="stylesheet" type="text/css" href="web/css/browser.css">
	<link rel="stylesheet" type="text/css" href="web/css/calendar.css">
	<link rel="stylesheet" type="text/css" href="web/css/diff.css">
	<link rel="stylesheet" type="text/css" href="web/css/editmode.css">
	<link rel="stylesheet" type="text/css" href="web/css/errors.css">
	<link rel="stylesheet" type="text/css" href="web/css/log.css">
	<link rel="stylesheet" type="text/css" href="web/css/menubar.css">
	<link rel="stylesheet" type="text/css" href="web/css/projpage.css">
	<link rel="stylesheet" type="text/css" href="web/css/settings.css">
	<link rel="stylesheet" type="text/css" href="web/css/team-status.css">
	<!-- Javascript Source Files -->
	<script src="web/javascript/base64.js" type="text/javascript"></script>
	<script src="web/javascript/json2.js" type="text/javascript"></script>
	<script src="web/javascript/ide.js" type="text/javascript"></script>
	<script src="web/javascript/MochiKit.js" type="text/javascript"></script>
	<script src="web/javascript/gui.js" type="text/javascript"></script>
	<!-- Everything above here should be in that order so things don't explode,
	     but below here things matter less, so go alphabetical... -->
	<script type="text/javascript" src="web/javascript/admin.js"></script>
	<script type="text/javascript" src="web/javascript/browser.js"></script>
	<script type="text/javascript" src="web/javascript/calendar.js"></script>
	<script type="text/javascript" src="web/javascript/checkout.js"></script>
	<script type="text/javascript" src="web/javascript/diff.js"></script>
	<script type="text/javascript" src="web/javascript/edit.js"></script>
	<script type="text/javascript" src="web/javascript/errors.js"></script>
	<script type="text/javascript" src="web/javascript/log.js"></script>
	<script type="text/javascript" src="web/javascript/poll.js"></script>
	<script type="text/javascript" src="web/javascript/projpage.js"></script>
	<script type="text/javascript" src="web/javascript/settings.js"></script>
	<script type="text/javascript" src="web/javascript/team-status.js"></script>
	<script type="text/javascript" src="web/javascript/status.js"></script>
	<script type="text/javascript" src="web/javascript/tabs.js"></script>

	<!-- external editor component - ACE - http://ace.ajax.org -->
	<script type="text/javascript" src="web/javascript/ace/src/ace.js"></script>
	<script type="text/javascript" src="web/javascript/ace/src/mode-python.js"></script>

	<title>Robotics IDE</title>
<?php if (Configuration::getInstance()->getConfig('usage_tracking')): ?>
	<!-- TODO: support some form of DNT? -->
	<script type="text/javascript">
		var pkBaseURL = (("https:" == document.location.protocol) ? "https://www.studentrobotics.org/piwik/" : "http://www.studentrobotics.org/piwik/");
		document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
	</script>
<?php endif ?>
</head>
<body>

	<!-- File Browser	-->
	<div id="file-browser">
		<div style="clear: both">&nbsp;</div>
		<span id="browser-title"></span>
		<span id="save-path">
			Save Path: <select id="browser-project-select"><option>loading...</option></select>
			<span id="selected-dir"></span>
		</span>
		<div id="left-pane">
			<ul id="left-pane-list" ><li class="dummy"></li></ul>
		</div>
		<div id="right-pane">
			<ul id="right-pane-list"><li class="dummy"></li></ul>
		</div>
		<pre id="browser-diff" class="diff-box"></pre>
		<textarea id="new-commit-msg" rows="3" cols="50"></textarea>
		<span id="browser-status">New Filename: </span>
		<div id="new-file-inputs">
			<input type="text" id="new-file-name" value="new.py">
			<button id="save-new-file" >Save</button>
			<button id="cancel-new-file" >Cancel</button>
		</div>
	</div>

	<div id="grey-out"></div>

	<!-- About Box -->
	<div id="about-box" class="hidden">
		<span id="about-title">Student Robotics IDE</span>
		<fieldset>
			<legend>About the IDE</legend>
			<dl id="about-list"><dt>Loading...</dt><dd>&nbsp;</dd></dl>
		</fieldset>
		<fieldset id="about-shortcuts">
			<legend>Keyboard Shortcuts</legend>
			<dl>
				<dt>Ctrl+E</dt><dd>Export the current Project</dd>
				<dt>Alt+PageUp</dt><dd>Switch one tab to the left</dd>
				<dt>Alt+PageDown</dt><dd>Switch one tab to the right</dd>
			</dl><dl>
				<dt>Ctrl+S</dt><dd>Save the current file</dd>
			</dl><dl>
				<dt>Esc</dt><dd>Close (and cancel) the Browser</dd>
			</dl>
		</fieldset>
		<span>Click anywhere in this box to close</span>
	</div>

	<div id="top">
		<ul id="topleft">
			<li><a href="control.php/auth/deauthenticate" id="logout-button">Logout</a></li>
			<li id="teaminfo">Loading...</li>
		</ul>
		<ul id="top-links">
<?php if (Configuration::getInstance()->getConfig('gui.ticket_link')): ?>
			<li>
				<a href="https://www.studentrobotics.org/tickets/" title="Get your ticket now!">Ticket System</a>
			</li>
<?php endif ?>
			<li>
				<a href="https://www.studentrobotics.org/forum/" title="Meet other competitors, get help from mentors and share ideas in the forums">Forum</a>
			</li>
			<li>
				<a href="https://www.studentrobotics.org/docs/" title="Find out more about the kit in the docs">Docs</a>
			</li>
		</ul>
		<div id="static-box"><img src="web/images/static.png" alt="logo"></div>
		<div id="rotating-box"><img src="web/images/anim.gif" alt="logo"></div>
	</div>

	<div id="tabs">
		<ul id="tab-list"><li class="dummy"></li></ul>
	</div>

	<div id="dropShortcuts" class="hidden">
	</div>

	<div id="status" >
		<span id="status-span"></span>
	</div>

	<!-- Almost everything below the top banner is a `page` -->
	<div id="page">

		<div id="edit-mode" class="page">
			<div id="editpage-menu-bar" class="menu-bar">
				<ul id="filemenu">
					<li><button id="save-file" title="Save the changes you've made as a new revision. (Ctrl+S)">Save</button></li>
					<li><button id="check-syntax" title="Check that the code you've writen is valid python.">Check Syntax</button></li>
					<li><button id="edit-diff" title="See an overview of the changes you've made.">View Changes</button></li>
					<li><button id="close-edit-area" title="Close this file.">Close</button></li>
					<li><div id="tab-filename">new file</div></li>
					<li class= "file-history">
						<select name="history" id="history">
							<option value="1" title="Log Msg: [commit msg]">hh:mm:ss dd/mm/yyyy [user]</option>
						</select>
					</li>
				</ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<div id="editpage-acebox"></div>
		</div>

		<div id="log-mode" class="page">
			<!-- Log Viewer -->
			<div class="menu-bar">
				<ul id="logmenu">
					<li><button id="revert" title="Revert to the selected revision.">Revert To</button></li>
					<li><button id="log-diff" title="View the changes the selected revision introduces.">View Diff</button></li>
					<li><button id="log-open" title="View the file at the selected revision.">View</button></li>
					<li><button id="log-close" title="Close this log view.">Close</button></li>
					<li class="log-navigate">
						<button id="older">&lt;&lt;&lt; Older</button>
						<button id="newer">Newer &gt;&gt;&gt;</button>
					</li>
					<li class="log-user">
						<select id="repo-users"><option>Dummy Option</option></select>
					</li>
				</ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<div id="logs">
				<em id="log-summary"> Displaying [n] revisions between [datetime] & [datetime] Page [n] of [n]</em>
				<ul id="log-list"><li class="dummy"></li></ul>
			</div>
		</div><!-- end log viewer -->

		<div id="projects-page" class="page">

			<div id="proj-sidebar">

				<div id="proj-options">
					<div>Project:<select id="project-select"><option>loading...</option></select></div>
					<hr />
					<button id="new-project">New Project</button>
					<button id="export-project" title="Create a zip file for the robot that contains your code. (Ctrl+E)">Export Project</button>
					<!-- button id="archive-project" disabled="disabled">Archive Project</button -->
					<button id="copy-project">Copy Project</button>
					<button id="check-code">Check Code</button>
				</div>

				<hr />
				<div id="proj-fileops">
					<div>
						File selection:
						<ul>
							<li>
								<a id="proj-select-all">Select All</a>
							</li>
							<li>
								<a id="proj-select-none">Select None</a>
							</li>
						</ul>
					</div>
					<br>
					<div>
						File operations:
						<ul>
							<li><a id="op-newfile"      href="#">New File</a></li>
							<li><a id="op-mkdir"        href="#">New Directory</a></li>
							<li><a id="op-mv"           href="#">Move (including rename)</a></li>
							<li><a id="op-cp"           href="#">Copy</a></li>
							<li><a id="op-rm"           href="#">Delete</a></li>
							<li><a id="op-undel"        href="#">Undelete</a></li>
							<li><a id="op-rm_autosaves" href="#">Delete Autosaves</a></li>
							<li><a id="op-check"        href="#">Check Files' Syntax</a></li>
							<li><a id="op-log"          href="#">View Log</a></li>
						</ul>
					</div>
				</div>
				<hr/>

				<div id="proj-revselect">
					<select id="cal-revs">
						<option value="v1">Select a date: </option>
					</select>
					<table id="calendar">
						<tr><td id="cal-prev-month">&lt;&lt;</td><td colspan="5" id="cal-header" class="calendar-month"> September </td><td id="cal-next-month">&gt;&gt;</td></tr>
						<tr class="calendar-days">
							<td title="Sunday">S</td>
							<td title="Monday">M</td>
							<td title="Tuesday">T</td>
							<td title="Wednesday">W</td>
							<td title="Thursday">T</td>
							<td title="Friday">F</td>
							<td title="Saturday">S</td>
						</tr>
						<tr id="cal-row-0"><td colspan="7">&nbsp;</td></tr>
						<tr id="cal-row-1"><td colspan="7">&nbsp;</td></tr>
						<tr id="cal-row-2"><td colspan="7">&nbsp;</td></tr>
						<tr id="cal-row-3"><td colspan="7">&nbsp;</td></tr>
						<tr id="cal-row-4"><td colspan="7">&nbsp;</td></tr>
						<tr id="cal-row-5"><td colspan="7">&nbsp;</td></tr>
					</table>
				</div>

			</div> <!-- end of "proj-sidebar" -->

			<div id="proj-rpane">
				<div id="proj-rpane-header">
					<div id="proj-name"></div>
					Ctrl+Click to Select (Cmd+Click on a Mac)<br>
					Click to Open<br>
				</div>
				<ul id="proj-filelist"><li class="dummy"></li></ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>

		</div> <!-- end of "projects-page" -->

		<div id="errors-page" class="page">
			<div class="menu-bar">
				<ul id="errors-menu">
					<li><button id="collapse-errors-page" title="Click to collapse all files">Collapse All</button></li>
					<li><button id="expand-errors-page" title="Click to expand all files">Expand All</button></li>
					<li><button id="check-errors-page" title="Click to re-check the current saved version of all files">Check All Again</button></li>
					<li><button id="close-errors-page" title="Click to close page">Close</button></li>
				</ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<em>Click a filename to view the file, double click an error to view it in the file.</em>
			<dl id="errors-listing">
				<dt class="dummy"></dt>
				<dd class="dummy"></dd>
			</dl>
		</div> <!-- end of "errors-page" -->

		<div id="settings-page" class="page">
			<div class="menu-bar">
				<ul>
					<li><em id="settings-title">View or edit user settings for the IDE.</em></li>
					<li class="settings-save"><button id="settings-save">Save</button></li>
				</ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<div id="settings-table">
				User settings appear here!
			</div>
		</div> <!-- end of "settings-page" -->

		<div id="team-status-page" class="page">
			<div class="menu-bar">
				<ul>
					<li><em id="team-status-title">Set public information about your team.</em></li>
					<li class="save-button"><button id="team-status-save">Save</button></li>
				</ul>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<p class="info">
				The following information will be publicly available on the SR website, in the <a href="http://www.studentrobotics.org/teams">Teams Area</a>.
				Be sensible with the information you add on this page, avoid anything objectionable/obscene. All content is moderated before appearing on the website and the following icons will appear to the right of the field after moderation:
			</p>
			<ul class="info">
				<li><img src="web/images/icons/tick.png" alt="Tick" /> The change has been accepted and is now visible on the website.</li>
				<li><img src="web/images/icons/cross.png" alt="Cross" /> The change has been rejected. Please contact us if you would like to know why.</li>
			</ul>
			<table>
				<tr id="team-status-name">
					<th>Team Name:</th>
					<td><input type="text" id="team-status-name-input" /></td>
				</tr>
				<tr id="team-status-image">
					<th>Image:</th>
					<td>
						<form action="./upload.php" target="upload-helper" id="team-status-image-upload-form" method="POST" enctype="multipart/form-data">
							<input type="file" name="team-status-image-input" id="team-status-image-input" />
							<p class="info">
								Upload an image to show on your Team page. It can be of anything you wish, within reason. Ideally it'd show the current state of your robot to keep the other teams on their toes! Please avoid faces appearing in the image.
							</p>
							<p class="info">
								Max. file size:
								<?php echo ini_get('upload_max_filesize'); ?>B.
								PNG, JPEG and GIF are supported. The image will be resized to fit within 480x320.
							</p>
							<input type="hidden" name="_command" id="team-status-image-command" value="" />
							<input type="hidden" name="team" id="team-status-image-team" value="" />
						</form>
					</td>
				</tr>
				<tr id="team-status-description">
					<th>About the team:</th>
					<td>
						<textarea id="team-status-description-input" cols="70" rows="4"></textarea>
						<p class="info">
							Tell the world about your team! Who are you? How did you form? Got any interesting facts?
						</p>
					</td>
				</tr>
				<tr id="team-status-url">
					<th>Website:</th>
					<td>
						<input type="text" name="team-status-url-input" id="team-status-url-input" />
						<p class="info">
							If you have a website please give us the full URL to it (including the http://)
						</p>
					</td>
				</tr>
				<tr id="team-status-feed">
					<th>Blog Feed:</th>
					<td>
						<input type="text" name="team-status-feed-input" id="team-status-feed-input" />
						<p class="info">
							Enter the URL (including the http://) of your blog's RSS/Atom feed here to make it easier for other teams to find out about your progress.
							If you do not have a blog there are many free sites where you can create one, such as <a href="http://www.blogger.com" target="_blank">Blogger</a> and <a href="http://wordpress.com/" target="_blank">Wordpress</a>.
						</p>
					</td>
				</tr>
			</table>
		</div> <!-- end of "team-status-page" -->

		<div id="admin-page" class="page">
			<div class="menu-bar">
				Teams with content to reivew:
				<select id="admin-page-team-select">
					<option value="dummy">Please select..</option>
				</select>
			</div>
			<table id="admin-page-review">
				<tr>
					<td>Items to review will appear here.</td>
				</tr>
			</table>
		</div> <!-- end of "admin-page" -->

		<div id="diff-page" class="page">
			<div class="menu-bar">
				<em id="diff-page-summary">Displaying differences on [file] between [old-rev] and [new-rev].</em>
				<div class="ie6-prop-clear" style="clear:both;"></div>
			</div>
			<pre id="diff-page-diff" class="diff-box"></pre>
		</div> <!--end "diff-page" -->

	</div> <!-- end of "page" div -->

	<iframe id="upload-helper" name="upload-helper" style="display:none;"></iframe>

	<iframe id="robot-zip" style="display:none;"></iframe>

	<div id="applet" style="visibility: hidden;"></div>

</body>
</html>
