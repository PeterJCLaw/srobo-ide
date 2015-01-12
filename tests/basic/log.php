<?php

class TestLogger extends Logger
{
	public function __construct($location, $level)
	{
		parent::__construct($location, $level);
	}
}

function test_level($logLevel, $data)
{
	$logger = new TestLogger(null, $logLevel);
	foreach ($data as $level => $shouldLog)
	{
		$levelName = Logger::$names[$level];
		$logLevelName = Logger::$names[$logLevel];
		test_equal($logger->isLogging($level), $shouldLog, "Logging '$levelName' item with config at '$logLevelName'");
	}
}

section("Test Level Handling");
test_level(LOG_DEBUG,	// everygthing
	array(LOG_DEBUG => true,
	      LOG_INFO => true,
	      LOG_NOTICE => true,
	      LOG_WARNING => true,
	      LOG_ERR => true)
	);

test_level(LOG_INFO,	// no debug
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => true,
	      LOG_NOTICE => true,
	      LOG_WARNING => true,
	      LOG_ERR => true)
	);

test_level(LOG_NOTICE,	// notices, warnings & errors only
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => FALSE,
	      LOG_NOTICE => true,
	      LOG_WARNING => true,
	      LOG_ERR => true)
	);

test_level(LOG_WARNING,	// warnings & errors only
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => FALSE,
	      LOG_NOTICE => FALSE,
	      LOG_WARNING => true,
	      LOG_ERR => true)
	);

test_level(LOG_ERR,	// errors only
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => FALSE,
	      LOG_NOTICE => FALSE,
	      LOG_WARNING => FALSE,
	      LOG_ERR => true)
	);

section("Test Output");
$dest = $testWorkPath . "out.log";

$logger = new TestLogger($dest, LOG_DEBUG);

$msg = "bacon";
$logger->log(LOG_INFO, $msg);

test_existent($dest, "Should have created output log");

$content = file_get_contents($dest);
$wasLogged = strpos($content, $msg) !== FALSE;
test_true($wasLogged, "Failed to log '$msg', got: '$content'.");
