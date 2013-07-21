<?php

function test_level($logLevel, $data)
{
	$config = Configuration::getInstance();
	$config->override('log.level', $logLevel);
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
