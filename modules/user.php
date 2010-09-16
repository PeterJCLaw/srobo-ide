<?php

class UserModule extends Module
{
	private $settingsPath;

	public function __construct()
	{
		$config = Configuration::getInstance();
		$this->settingsPath = $config->getConfig('settingspath');
		$this->installCommand('info', array($this, 'getInfo'));
		$this->installCommand('settings-put', array($this, 'saveSettings'));
		$this->installCommand('blog-feed', array($this, 'blogFeed'));
		$this->installCommand('blog-posts', array($this, 'blogPosts'));

		$auth = AuthBackend::getInstance();
		if (!($this->username = $auth->getCurrentUser()))
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
	}

	public function getInfo()
	{
		$output = Output::getInstance();
		$auth = AuthBackend::getInstance();

		$output->setOutput('display-name', $auth->displayNameForUser($this->username));
		$output->setOutput('email', $auth->emailForUser($this->username));
		$output->setOutput('teams', $auth->getCurrentUserTeams());
		$output->setOutput('is-admin', false);
		if (file_exists("$this->settingsPath/$this->username.json"))
		{
			$data = file_get_contents("$this->settingsPath/$this->username.json");
			$settings = json_decode($data);
		}
		else
		{
			$settings = array();
		}
		$output->setOutput('settings', $settings);
	}

	public function saveSettings()
	{
		$input = Input::getInstance();
		$settings = $input->getInput('settings');
		$data = json_encode($settings);
		file_put_contents("$this->settingsPath/$this->username.json", $data);
	}

	public function blogFeed()
	{
		$output = Output::getInstance();
		$output->setOutput('url', 'file:///dev/null');
		$output->setOutput('checked', false);
		$output->setOutput('valid', false);
	}

	public function blogPosts()
	{
		$output = Output::getInstance();
		$output->setOutput('posts', array(
			array(
				'link'   => 'http://example.com',
				'title'  => 'The Blog Post',
				'body'   => 'of doom',
				'author' => 'pony man\'s youngest son'
			)
		));
	}
}
