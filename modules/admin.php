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
        if (!($this->username = $auth->getCurrentUser()))
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
        $user = AuthBackend::getInstance()->getCurrentUser();
        $saved = $status->save($user);
        if (!$saved)
        {
            throw new Exception('Failed to save review', E_INTERNAL_ERROR);
        }
        return true;
    }

    /**
     * Get the image data to review.
     * This contains a base64 encoded version of the image and the md5.
     * @param team: The team to get the image for.
     * @returns: An object containing the image data, or null.
     */
    private static function getImageForReview($team)
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
        $fileData64 = base64_encode($fileData);

        $md5 = md5($fileData);

        $info = new stdClass();
        $info->md5 = $md5;
        $info->base64 = $fileData64;

        return $info;
    }
}
