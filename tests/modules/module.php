<?php

class TestModule extends Module
{
	private $preconditionPassed = false;

	public function __construct()
	{
		$this->installCommand('test',
		                      array($this, 'body'),
		                      array($this, 'precondition'));
	}

	private function precondition()
	{
		$this->preconditionPassed = true;
	}

	private function body()
	{
		test_true($this->preconditionPassed, 'precondition was not evaluated');
	}
}

$config = Configuration::getInstance();

$mm = ModuleManager::getInstance();
$mm->addModule('test', new TestModule());
test_true($mm->moduleExists('test'), "test module was not found");

$module = $mm->getModule('test');

$module->dispatchCommand('test');

