<?php

class UserInfo
{
    private static $instance = null;


    //TODO: things here
    private function __construct($user_auth_token) {
        $this->info = array();
    }

    public static function getInstance() {
        if (self::instance == null) {
            $input = Input::getInstance();
            self::$instance = new UserInfo($input->getInput("auth_token"));
        }

        return self::$instance;

    }


    public function getInfo($info) {
        if (isset($this->info, $info)) {
            return $this->info[$info];
        } else {
            throw new Exception("key in user info did not exist", 4);
        }

    }

}
