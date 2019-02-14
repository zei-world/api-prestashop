<?php

if(!defined('_PS_VERSION_')) exit;

include_once "zei_api.php";

class zei_debugger {

    public static function isEnabled() {
        return Configuration::get('zei_api_debugger') == 1;
    }

    public static function getHash() {
        if(array_key_exists('zei_debugger' , $_COOKIE)) {
            return $_COOKIE['zei_debugger'];
        }

        $hash = md5(uniqid(rand(), true));
        setcookie('zei_debugger', $hash);
        self::send("Init");
        
        return $hash;
    }

    public static function send($title, $data = null) {
        if(self::isEnabled()) {
            $content = array(
                'hash' => self::getHash(),
                'message' => $title . ($data !== null ? (" " . json_encode($data)) : "")
            );
            if(array_key_exists('zei', $_COOKIE)) {
                $content['user'] = $_COOKIE['zei'];
            }
            zei_api::request('/v4/debugger', $content);
        }
    }
}
