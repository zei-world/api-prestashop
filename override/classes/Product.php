<?php

if(!defined('_PS_VERSION_')) exit;

class Product extends ProductCore {

    /** @var int ZEI offer id */
    public $zei_offer = 0;

    public function __construct($id_product = null, $full = false, $id_lang = null, $id_shop = null,
                                Context $context = null)
    {
        self::$definition['fields']['zei_offer'] = array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId');
        parent::__construct($id_product, $full, $id_lang, $id_shop, $context);
    }

}