<?php

if(!defined('_PS_VERSION_')) exit;

class zei_api {

    private static $debug = false;

    private static $timeout = 2;

    private static $api = "api.zei-world.com/v3/";

    static private function request($path, $params = array()) {

        // Prestashop options
        $id = Configuration::get('zei_api_key');
        $secret = Configuration::get('zei_api_secret');
        $scheme = Configuration::get('zei_api_https') == "0" ? "http" : "https";

        $url = $scheme."://".self::$api.$path."?id=".$id."&secret=".$secret;
        foreach($params as $param => $value) $url .= "&".$param."=".$value;
        $response = file_get_contents($url, false, stream_context_create([
            'http' => [ 'method' => "GET", 'timeout' => self::$timeout, 'ignore_errors' => true ],
            'ssl' => [ "verify_peer" => false, "verify_peer_name" => false ]
        ]));

        if($response) {
            $data = json_decode($response, true);
            if(isset($data['success'])) return $data;
            if(self::$debug) var_dump('[ZEI] Server reached with an error', $data);
        } else if(self::$debug) {
            var_dump('[ZEI] Server not reached...');
        }

        return false;
    }

    public static function getOffersList() {
        $request = self::request('offers/valid');
        if($request && $request['success'] && $offers = $request['message']) {
            $list = array();
            foreach ($offers as $offerId => $offerData) {
                $list[$offerId] = $offerData['name'];
            }
            return $list;
        }
        return null;
    }

    static function getScriptUrl($b2c = true, $b2b = true) {
        if(!$b2b && !$b2c) return null;

        // Prestashop options
        $id = Configuration::get('zei_api_key');

        return "//".self::$api.'js'.
            '?id=' . $id .
            '&b2c=' . ($b2c ? 1 : 0).
            '&b2b=' . ($b2b ? 1 : 0).
            '&redirect_uri=http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                || $_SERVER['SERVER_PORT'] == 443 || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
                ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
            ;
    }

    static function validateOffer($offerId, $entity, $units = 1) {
        if(preg_match("/^(u|c|o)\/[0-9]+$/", $entity)) {
            return self::request('offers/'.$offerId.'/validate/'.$entity, array('units' => $units));
        }
        if(self::$debug) var_dump('[ZEI] Entity syntax error : \"'.$entity.'\"');
        return false;
    }
}
