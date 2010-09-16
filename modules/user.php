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
		$this->installCommand('blog-feed', array($this, 'getBlogFeed'));
		$this->installCommand('blog-feed-put', array($this, 'setBlogFeed'));
		$this->installCommand('blog-posts', array($this, 'blogPosts'));

		$auth = AuthBackend::getInstance();
		if (!($this->username = $auth->getCurrentUser()))
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
	}

	/* Get information about the user
	 */
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

	/* Save the user's settings
	 */
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
		$feeds  = Feeds::getInstance();

		$userfeed  = $feeds->findFeed('user', $this->username);
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
		$feeds  = Feeds::getInstance();

		$feedurl = $input->getInput('feedurl');

		$userfeed  = $feeds->findFeed('user', $this->username);

		if ($userfeed == null)
		{
			$userfeed = new StdClass();
		}

		$userfeed->url     = $feedurl;
		$userfeed->user    = $this->username;
		$userfeed->valid   = false;
		$userfeed->checked = false;

		$newfeeds[] = $userfeed;
		$feedsList = $feeds->getFeeds();
		foreach ($feedsList as $feed)
		{
			if ($feed->user != $this->username)
			{
				$newfeeds[] = $feed;
			}
		}

		$error = intval(!$feeds->putFeeds($newfeeds));
		$output->setOutput('feedurl', $feedurl);
		$output->setOutput('error', $error);
	}

	/* Get all the recent blog posts from the validated user blog feeds
	 */
	public function blogPosts()
	{
		$output = Output::getInstance();
		$feeds  = Feeds::getInstance();

		$urls = $feeds->getValidURLs();

		$posts = array();
		foreach ($urls as $url)
		{
			$msgs = Feeds::getRecentPosts($url, 3);
			$posts = array_merge($posts, $msgs);
		}
		$output->setOutput('posts', $posts);
	}

}
