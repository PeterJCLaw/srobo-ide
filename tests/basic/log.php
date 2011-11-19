<?php

function test_level($logLevel, $data)
{
	$config = Configuration::getInstance();
	$config->override('log_level', $logLevel);
	foreach ($data as $level => $shouldLog)
	{
		$levelName = Logger::$names[$level];
		$logLevelName = Logger::$names[$logLevel];
		test_equal(Logger::isLogging($level), $shouldLog, "Logging '$levelName' item with config at '$logLevelName'");
	}
}

test_level(LOG_DEBUG,	// everygthing
	array(LOG_DEBUG => true,
	      LOG_INFO => true,
	      LOG_ERR => true)
	);

test_level(LOG_INFO,	// info and errors only
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => true,
	      LOG_ERR => true)
	);

test_level(LOG_ERR,	// errors only
	array(LOG_DEBUG => FALSE,
	      LOG_INFO => FALSE,
	      LOG_ERR => true)
	);
