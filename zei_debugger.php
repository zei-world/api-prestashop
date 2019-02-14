<?php

if(!defined('_PS_VERSION_')) exit;

include_once "zei_api.php";

class zei_debugger {
    static $isLoaded = false;
    static $hash = null;

    public function __construct() {
		if(Configuration::get('zei_api_debugger') == 1) {
            self::$isLoaded = true;
        }

        if(self::$isLoaded) {
            if(array_key_exists('zei_debugger' , $_COOKIE)) {
                self::$hash = $_COOKIE['zei_debugger'];
            } else {
                self::$hash = md5(uniqid(rand(), true));
                setcookie('zei_debugger', self::$hash);
                self::send("Init");
            }
        }
    }

    public static function send($title, $data = null) {
        $content = array('hash' => self::$hash, 'message' => $title . ($data !== null ? (" " . json_encode($data)) : ""));
        if(array_key_exists('zei', $_COOKIE)) {
            $content['user'] = $_COOKIE['zei'];
        }
        if(self::$isLoaded) {
            zei_api::request('/v4/debugger', $content);
        }
    }
}
