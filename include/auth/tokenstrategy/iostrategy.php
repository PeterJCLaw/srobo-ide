<?php

require_once("tokenstrategy.php");

class IOTokenStrategy extends TokenStrategy
{
    public function getAuthToken()
    {
        $input = Input::getInstance();
        return $input->getInput("auth-token", true);
    }

    public function setNextAuthToken($token)
    {
        $output = Output::getInstance();
        $output->setOutput("auth-token", $token);
    }
}
