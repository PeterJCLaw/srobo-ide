<?php

class UserModule extends Module
{
	private $username;

	public function __construct()
	{
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
		if (!($this->username = $auth->getCurrentUser()))
		{
			throw new Exception('you are not logged in', E_AUTH_REQUIRED);
		}
		return $auth;
	}

	/**
	 * Get information about the user
	 */
	public function getInfo()
	{
		$output = Output::getInstance();
		$auth = $this->ensureAuthed();

		$teamNumbers = $auth->getCurrentUserTeams();
		$teams = array();
		foreach ($teamNumbers as $id)
		{
			$teams[$id] = $auth->displayNameForTeam($id);
		}

		$output->setOutput('display-name', $auth->displayNameForUser($this->username));
		$output->setOutput('email', $auth->emailForUser($this->username));
		$output->setOutput('teams', $teams);
		$output->setOutput('is-admin', $auth->isCurrentUserAdmin());
		$settingsManager = Settings::getInstance();
		$settings = $settingsManager->getSettings($this->username);
		$output->setOutput('settings', $settings);
	}

	/**
	 * Save the user's settings
	 */
	public function saveSettings()
	{
		$this->ensureAuthed();
		$input = Input::getInstance();
		$settings = $input->getInput('settings');
		$settingsManager = Settings::getInstance();
		$settingsManager->setSettings($this->username, $settings);
	}

	/**
	 * Gets the user's blog feed, set on the switchboard page
	 */
	public function getBlogFeed()
	{
		$this->ensureAuthed();
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

	/**
	 * Sets the user's blog feed, for the switchboard page
	 */
	public function setBlogFeed()
	{
		$this->ensureAuthed();
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

	/**
	 * Get all the recent blog posts from the validated user blog feeds
	 */
	public function blogPosts()
	{
		$this->ensureAuthed();
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
