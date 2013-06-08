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
	require_once('include/cache-utils.php');
	$javaScripts[] = "web/javascript/base64.js";
	$javaScripts[] = "web/javascript/json2.js";
	$javaScripts[] = "web/javascript/ide.js";
	$javaScripts[] = "web/javascript/MochiKit.js";
	$javaScripts[] = "web/javascript/status.js";
	$javaScripts[] = "web/javascript/login.js";

	$combined_js = 'web/cache/login-combined.js';
	combine_into($javaScripts, $combined_js);
	echo '<script type="text/javascript" src="' . $combined_js . '"></script>', PHP_EOL;
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
		<a href="https://www.studentrobotics.org/forgotpassword/">&raquo; Forgotten password</a>
	</form>

	<iframe id="background-load"></iframe>

</body>
</html>
