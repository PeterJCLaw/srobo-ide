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

		$auth = AuthBackend::getInstance();
		if ($username = $auth->getCurrentUser())
		{
			$info['User'] = $username;

			$teamNumbers = $auth->getCurrentUserTeams();
			$info['Teams'] = implode(', ', $teamNumbers);
		}

		$info['Privacy Policy'] = file_get_contents('web/common-cookies-policy.html');

		Output::getInstance()->setOutput('info', $info);
	}
}
