<?php

if(!defined('_PS_VERSION_')) exit;

class Order extends OrderCore {

    /** @var int ZEI token */
    public $zei_profile = 0;

    /**
     * @var string $zei_validation
     * is validation send to zei api
     */
    public $zei_validation;

    public function __construct($id = null, $id_lang = null) {
        self::$definition['fields']['zei_profile'] = array('type' => self::TYPE_STRING, 'validate' => 'isMessage');
        self::$definition['fields']['zei_validation'] = array('type' => self::TYPE_STRING, 'validate' => 'isMessage');
        parent::__construct($id, $id_lang);
    }

}