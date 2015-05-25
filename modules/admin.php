<?php

/**
 * This module covers 'administration' commands available only to blueshirts.
 */
class AdminModule extends Module
{
    /**
     * The username of the user currently using the module.
     * @todo Remove this, and use $auth->getCurrentUserName() in all instances?
     */
    private $username;

    /**
     * Standard constructor: installs commands.
     */
    public function __construct()
    {
        $this->installCommand('review-teams-get',   array($this, 'getTeamsWithStuffToReview'));
        $this->installCommand('review-items-get', array($this, 'getItemsToReview'));
        $this->installCommand('review-item-set', array($this, 'setItemReviewState'));
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
        if (!($this->username = $auth->getCurrentUserName()))
        {
            throw new Exception('You are not logged in', E_PERM_DENIED);
        }
        if (!$auth->isCurrentUserAdmin())
        {
            throw new Exception('You do not have admin privileges',
                                E_PERM_DENIED);
        }
    }

    /**
     * Handles a request for the list of teams that need things reviewing.
     */
    public function getTeamsWithStuffToReview()
    {
        $this->ensureAuthed();

        $allTeams = TeamStatus::listAllTeams();
        $teams = array_filter($allTeams, function($team) {
            $status = new TeamStatus($team);
            return $status->needsReview();
        });

        $output = Output::getInstance();

        $teams = array_values($teams);
        $output->setOutput('teams', $teams);
        return true;
    }

    /**
     * Get all the items that need review for a given team.
     */
    public function getItemsToReview()
    {
        $this->ensureAuthed();
        $input = Input::getInstance();

        $team = $input->getInput('team');
        $status = new TeamStatus($team);

        $itemsToReview = $status->itemsForReview();

        // If the image needs review, we need to handle things a bit differently
        // The current value is the md5, we need to actually get the image data
        if (isset($itemsToReview['image']))
        {
            $itemsToReview['image'] = self::getImageForReview($team);
            // If we couldn't get the image data, remove the entry
            if ($itemsToReview['image'] == null)
            {
                unset($itemsToReview['image']);
            }
        }

        $output = Output::getInstance();
        $output->setOutput('items', $itemsToReview);
        return true;
    }

    /**
     * Get all the items that need review for a given team.
     */
    public function setItemReviewState()
    {
        $this->ensureAuthed();
        $input = Input::getInstance();

        $team = $input->getInput('team');
        $status = new TeamStatus($team);

        $isValid = $input->getInput('valid');
        $value = $input->getInput('value');
        $item = $input->getInput('item');

        $status->setReviewState($item, $value, $isValid);
        $userName = AuthBackend::getInstance()->getCurrentUserName();
        $saved = $status->save($userName);
        if (!$saved)
        {
            throw new Exception('Failed to save review', E_INTERNAL_ERROR);
        }
        if ($item == 'image' && $isValid)
        {
            self::saveImageFromReview($team, $value);
        }
        return true;
    }

    /**
     * Load a team's pre-review image.
     * @param team: The team to get the image for.
     * @returns: An object containing the image data, or null.
     */
    private static function loadImage($team)
    {
        $config = Configuration::getInstance();
        $uploadLocation = $config->getConfig('team.status_images.dir');
        $imagePath = "$uploadLocation/$team.png";
        if (!is_dir($uploadLocation) || !file_exists($imagePath))
        {
            return null;
        }

        if ( ($fileData = file_get_contents($imagePath)) === false )
        {
            return null;
        }

        return $fileData;
    }

    /**
     * Get the image data to review.
     * This contains a base64 encoded version of the image and the md5.
     * @param team: The team to get the image for.
     * @returns: An object containing the image data, or null.
     */
    private static function getImageForReview($team)
    {
        $fileData = self::loadImage($team);
        if ($fileData == null)
        {
            return null;
        }

        $fileData64 = base64_encode($fileData);

        $md5 = md5($fileData);

        $info = new stdClass();
        $info->md5 = $md5;
        $info->base64 = $fileData64;

        return $info;
    }

    /**
     * Copy a reviewed image that is valid into the destination folder.
     * @param team: The team to move the image for.
     * @param reviewed_md5: The md5 of the image that the reviewer checked.
     */
    private static function saveImageFromReview($team, $reviewed_md5)
    {
        $fileData = self::loadImage($team);
        if ($fileData == null)
        {
            ide_log(LOG_ERR, "Failed to load image for '$team'");
            return false;
        }

        $current_md5 = md5($fileData);
        if ($current_md5 != $reviewed_md5)
        {
            ide_log(LOG_INFO, "Bad md5 - current: $current_md5 != reviewed: $reviewed_md5");
            return false;
        }

        $config = Configuration::getInstance();
        $liveLocation = $config->getConfig('team.status_images.live_dir');
        if (!is_dir($liveLocation) && !mkdir_full($liveLocation)
         || !is_writable($liveLocation))
        {
            ide_log(LOG_ERR, "Cannot copy status image to bad location '$liveLocation'");
            return false;
        }

        $imagePath = "$liveLocation/$team.png";
        file_put_contents($imagePath, $fileData);

        $height = $config->getConfig('team.status_thumbs.height');
        $width = $config->getConfig('team.status_thumbs.width');

        $image = new ResizableImage($imagePath);
        $dest = str_insert($imagePath, '_thumb', -4);
        $image->resizeInto($width, $height, $dest);

        return true;
    }
}
