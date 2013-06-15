<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
	<title>Robotics IDE Login</title>
	<!-- Style Sheets -->
	<link rel="stylesheet" href="web/css/newstyle.css" type="text/css">

	<!-- Javascript Source Files -->
<?php
	require_once('include/html-utils.php');
	$javaScripts[] = "web/javascript/json2.js";
	$javaScripts[] = "web/javascript/ide.js";
	$javaScripts[] = "web/javascript/MochiKit.js";
	$javaScripts[] = "web/javascript/status.js";
	$javaScripts[] = "web/javascript/login.js";

	output_statics($javaScripts, 'js_tag', 'web/cache/login-combined.js');

	$root_url = Configuration::getInstance()->getConfig('host_root_url');
?>
</head>
<body id="login-back">

	<form id="login-box" method="POST" action="./">
		<strong id="login-title">Student Robotics IDE Login:</strong>
		<em id="login-feedback">You must be logged in to use the IDE</em>
		<input type="text" name="username" value="username" id="username">
		<input type="password" name="password" id="password">
		<button type="submit" id="login-button">Log In</button>
		<br />
		<a id="forgotten-password-button" class="smaller" href="#">&raquo; Forgotten password</a>
		<p id="forgotten-password-help" class="smaller">
			If you have forogtten your password, you should contact your
			<a href="<?=$root_url ?>/schools/team-leaders/">team leader</a>.
			Team leaders can reset passwords for users in the teams they lead using Student Robotics'
			<a href="<?=$root_url ?>/userman/">User Management Page</a>.
		</p>
	</form>

	<iframe id="background-load"></iframe>

</body>
</html>
