<?php

class UserModule extends Module
{
	private $settingsPath;
	private $username;

	public function __construct()
	{
		$config = Configuration::getInstance();
		$this->settingsPath = $config->getConfig('settingspath');

		$this->installCommand('info', array($this, 'getInfo'));
		$this->installCommand('settings-put', array($this, 'saveSettings'));
		$this->installCommand('blog-feed', array($this, 'getBlogFeed'));
		$this->installCommand('blog-feed-put', array($this, 'setBlogFeed'));
		$this->installCommand('blog-posts', array($this, 'blogPosts'));
	}

	/**
	 * Ensures that we have a valid user.
	 * You can't do anything user related without being authed, but putting
	 * this in the constructor causes issues, since construction occurs
	 * before the auth cycle does.
	 * Returns the AuthBackend instance for convenience.
	 */
	private function ensureAuthed()
	{
		$auth = AuthBackend::getInstance();
		$this->username = $auth->getCurrentUser();
	}

	/* Get information about the user
	 */
	public function getInfo()
	{
		$output = Output::getInstance();
		$auth = $this->ensureAuthed();

		if (!$this->username)
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}

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
<<<<<<< .merge_file_4Fbxdg
		if (!$this->username)
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}
=======
		$this->ensureAuthed();
>>>>>>> .merge_file_clLbgd
		$input = Input::getInstance();
		$settings = $input->getInput('settings');
		$data = json_encode($settings);
		file_put_contents("$this->settingsPath/$this->username.json", $data);
	}

	/* Gets the user's blog feed, set on the switchboard page
	 */
	public function getBlogFeed()
	{
		$this->ensureAuthed();
		$output = Output::getInstance();
		$feeds  = Feeds::getInstance();

		if (!$this->username)
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}

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
		$this->ensureAuthed();
		$input  = Input::getInstance();
		$output = Output::getInstance();
		$feeds  = Feeds::getInstance();

		if (!$this->username)
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}

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
<<<<<<< .merge_file_4Fbxdg
		if (!$this->username)
		{
			throw new Exception('you are not logged in', E_PERM_DENIED);
		}

=======
		$this->ensureAuthed();
>>>>>>> .merge_file_clLbgd
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
