<?php

class InfoModule extends Module
{
	public function __construct()
	{
		$this->installCommand('about', array($this, 'about'));
	}

	public function about()
	{
		$info = array();
		$info['Version'] = shell_exec('git log -1 --pretty=format:"%h on %aD"');

		if ($username = AuthBackend::getInstance()->getCurrentUser())
		{
			$info['User'] = $username;
		}

		Output::getInstance()->setOutput('info', $info);
	}
}
