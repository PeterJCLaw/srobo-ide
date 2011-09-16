<?php

/**
 * This module covers 'administration' commands available only to blueshirts.
 */
class AdminModule extends Module
{
    /**
     * The username of the user currently using the module.
     * @todo Remove this, and use $auth->getCurrentUser() in all instances?
     */
    private $username;

    /**
     * Standard constructor: installs commands.
     */
    public function __construct()
    {
        $this->installCommand('team-name-put',   array($this, 'saveTeamName'));
        $this->installCommand('feed-status-get', array($this, 'getBlogFeeds'));
        $this->installCommand('feed-status-put', array($this, 'setFeedStatus'));
    }

    /**
     * Ensures that we have a valid user.
     * You can't do anything user related without being authed, but putting
     * this in the constructor causes issues, since construction occurs
     * before the auth cycle does.
     */
    private function ensureAuthed()
    {
        $auth = AuthBackend::getInstance();
        if (!($this->username = $auth->getCurrentUser()))
        {
            throw new Exception('You are not logged in', E_AUTH_REQUIRED);
        }
        if (!$auth->isCurrentUserAdmin())
        {
            throw new Exception('You do not have admin privileges',
                                E_PERM_DENIED);
        }
    }

    /**
     * Handles a request for a user team name change via the default strategy.
     *
     * This can be considered asynchronous: the actual name change is likely to
     * be affected manually, a significant amount of time after this call is
     * made.
     */
    public function saveTeamName()
    {
        $this->ensureAuthed();

        $auth   = AuthBackend::getInstance();
        $input  = Input::getInstance();
        $output = Output::getInstance();

        $team = $input->getInput('id');
        $name = $input->getInput('name');

        TeamNameStrategy::getDefaultInstance()->writeNameChangeRequest($team,
                                                                       $name);

        $output->setOutput('success', true);
    }

    /**
     * Get all the info for all user blog feeds we know about
     */
    public function getBlogFeeds()
    {
        $this->ensureAuthed();
        $output = Output::getInstance();
        $feeds  = Feeds::getInstance()->getFeeds();
        $output->setOutput('feeds', $feeds);
    }

    /**
     * Updates the status flags on a feed after confirmation by the user
     * (a blueshirt).
     */
    public function setFeedStatus()
    {
        $this->ensureAuthed();
        $input  = Input::getInstance();
        $output = Output::getInstance();
        $feeds  = Feeds::getInstance();

        // get the URL to update and its declared status from input
        $feedurl    = $input->getInput('url');
        $feedstatus = $input->getInput('status');

        // gets the object for the feed associated with $feedurl
        $userfeed = $feeds->findFeed('url', $feedurl);

        // if the object returned is non-null (ie the feed is found)
        if ($userfeed != null)
        {
            // get the feed's flags by its status
            self::updateFeedStatusFlagsByStatusString($userfeed, $feedstatus);

            // write out the new feeds list
            self::rewriteMasterFeedsList();
            $success = true;
        }
        else
        {
            $success = false;
        }
        $output->setOutput('success', $success);
    }

    /**
     * Causes the Feeds object to re-write the master feeds list after updates.
     */
    private static function rewriteMasterFeedsList()
    {
        $feeds = Feeds::getInstance();
        $feeds->putFeeds($feeds->getFeeds());
    }

    /**
     * Updates a feed status by the status enumeration which has come from
     * a blueshirt via the frontend. This can be one of three values:
     *   "unchecked", meaning no checking has been done on the URL (the default)
     *   "valid", meaning that the feed is a valid feed but no rigorous checking
     *            has taken place;
     *   "checked", meaning the feed has been checked by a blueshirt.
     */
    private static function updateFeedStatusFlagsByStatusString($userfeed,
                                                                $status)
    {
        $userfeed->checked = ($status != 'unchecked');
        $userfeed->valid   = ($status == 'valid');
    }
}
