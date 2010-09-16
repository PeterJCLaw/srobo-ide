<?php

class UserModule extends Module
{
	private $settingsPath;
	private $feedsPath;

	public function __construct()
	{
		$config = Configuration::getInstance();
		$this->settingsPath = $config->getConfig('settingspath');
		$this->feedsPath    = $this->settingsPath.'/blog-feeds.json';

		$this->installCommand('info', array($this, 'getInfo'));
		$this->installCommand('settings-put', array($this, 'saveSettings'));
		$this->installCommand('blog-feed', array($this, 'getBlogFeed'));
		$this->installCommand('blog-feed-put', array($this, 'setBlogFeed'));
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

	/* Gets the user's blog feed, set on the switchboard page
	 */
	public function getBlogFeed()
	{
		$output = Output::getInstance();

		$feeds = $this->getFeeds();
		$userfeed = findFeed($feeds, 'user', $this->username);
		if ($userfeed == null)
		{
			return;
		}

		foreach ($userfeed as $k => $v)
		{
			$output->setOutput($k, $v);
		}
	}

	/* Sets the user's blog feed, for the switchboard page
	 */
	public function setBlogFeed()
	{
		$input  = Input::getInstance();
		$output = Output::getInstance();

		$feedurl = $input->getInput('feedurl');

		$feeds = $this->getFeeds();
		$userfeed = findFeed($feeds, 'user', $this->username);

		if ($userfeed == null)
		{
			$userfeed = new StdClass();
		}

		$userfeed->url     = $feedurl;
		$userfeed->user    = $this->username;
		$userfeed->valid   = false;
		$userfeed->checked = false;

		$newfeeds[] = $userfeed;
		foreach ($feeds as $feed)
		{
			if ($feed->user != $this->username)
			{
				$newfeeds[] = $feed;
			}
		}

		$error = intval(!$this->putFeeds($newfeeds));
		$output->setOutput('feedurl', $feedurl);
		$output->setOutput('error', $error);
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

	public function getFeeds()
	{
		if (file_exists($this->feedsPath))
		{
			$data = file_get_contents($this->feedsPath);
			return empty($data) ? array() : json_decode($data);
		}
		else
		{
			return array();
		}
	}

	public function putFeeds($feeds)
	{
		return file_put_contents($this->feedsPath, json_encode($feeds));
	}

}

function findFeed($feeds, $key, $value)
{
	foreach ($feeds as $feed)
	{
		if ($feed->$key == $value)
		{
			return $feed;
		}
	}
	return null;
}
