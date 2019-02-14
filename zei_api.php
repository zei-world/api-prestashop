<?php

if(!defined('_PS_VERSION_')) exit;

include_once "zei_debugger.php";

class zei_api {

    private static $debug = false;

    private static $timeout = 2;

    private static $api = "api.zei-world.com";

    static function request($path, $params = array()) {
        // Prestashop options
        $id = Configuration::get('zei_api_key');
        $secret = Configuration::get('zei_api_secret');
        $scheme = Configuration::get('zei_api_https') == "0" ? "http" : "https";

        $url = $scheme."://".self::$api.$path."?id=".$id."&secret=".$secret;
        foreach($params as $param => $value) $url .= "&" . $param . "=" . urlencode($value);

        if(zei_debugger::isEnabled()) {
            if(array_key_exists('zei', $_COOKIE)) {
                $url .= '&debugUser=' . $_COOKIE['zei'];
            }
            $hash = zei_debugger::getHash();
            if($hash !== null) {
                $url .= '&debugHash=' . $hash;
            }
        }

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
        $request = self::request('/v3/offers/valid');

        $response = array();

        if($request && $request['success'] && $offers = $request['message']) {
            foreach ($offers as $offerId => $offerData) {
                $response[$offerId] = $offerData['name'];
            }
        }

        zei_debugger::send("[OFFERS] [LIST]", $response);

        return $response;
    }

    static function validateOffer($offerId, $entity, $units = 1) {
        if(preg_match("/^(u|c|o)\/[0-9]+$/", $entity)) {
            $response = self::request('/v3/offers/'.$offerId.'/validate/'.$entity, array('units' => $units));
            zei_debugger::send("[OFFERS] [VALIDATION]", $response);
            return $response;
        }
        if(self::$debug) var_dump('[ZEI] Entity syntax error : \"'.$entity.'\"');
        return false;
    }

    static function getScriptUrl($b2c = true, $b2b = true) {
        if(!$b2b && !$b2c) return null;

        // Prestashop options
        $id = Configuration::get('zei_api_key');

        return "//".self::$api.'/v3/js'.
            '?id=' . $id .
            '&b2c=' . ($b2c ? 1 : 0).
            '&b2b=' . ($b2b ? 1 : 0).
            '&redirect_uri=http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                || $_SERVER['SERVER_PORT'] == 443 || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
                ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
            ;
    }

}
