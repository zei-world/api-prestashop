<?php

if(!defined('_PS_VERSION_')) exit;

include "zei_api.php";

class ZEI extends Module {

    public function __construct() {
        $this->name = 'zei';
        $this->tab = 'zei_api';
        $this->version = '1.5.1';
        $this->author = 'Zei';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Zei');
        $this->description = $this->l('Link your company offers and rewards from Zei !');

        $this->confirmUninstall = $this->l('Are you sure to remove your link with ZEI ?');

        if (!Configuration::get('ZEI')) $this->warning = $this->l('No name provided');
    }

    public function install() {
        return 
            parent::install() &&
            $this->alterTable() &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('actionOrderStatusPostUpdate')
		;
    }

    public function uninstall() {
        return
            parent::uninstall() &&
            $this->alterTable(true)
        ;
    }

    public function getContent() {
        $output = null;
        if(Tools::isSubmit('submit'.$this->name)) {
            $error = false;

            $key = strval(Tools::getValue('zei_api_key'));
            if(!$key || empty($key) || 32 !== strlen($key) || !Validate::isGenericName($key)) {
                $output .= $this->displayError($this->l('Invalid API key'));
                $error = true;
            }

            $secret = strval(Tools::getValue('zei_api_secret'));
            if(!$error && (!$secret || empty($secret) || 45 !== strlen($secret) || !Validate::isGenericName($secret))) {
                $output .= $this->displayError($this->l('Invalid API secret'));
                $error = true;
            }

            $https = strval(Tools::getValue('zei_api_https'));
            if(!$error && ($https != 0 || $https != 1) && !Validate::isGenericName($https)) {
                $output .= $this->displayError($this->l('Invalid HTTPS option'));
                $error = true;
            }

            if(!$error) {
                $output .= $this->displayConfirmation($this->l('Settings updated'));

                Configuration::updateValue('zei_api_key', $key);
                Configuration::updateValue('zei_api_secret', $secret);
                Configuration::updateValue('zei_api_https', $https);

                Configuration::updateValue('zei_global_offer', Tools::getValue('zei_global_offer'));
            }
        }
        return $output.$this->displayForm();
    }

    public function displayForm() {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = $token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;

        $form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings')
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'desc' => 'Enter your ZEI API Key from your company tools.',
                    'name' => 'zei_api_key',
                    'size' => 32,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API Secret'),
                    'desc' => 'Enter your ZEI API Secret from your company tools.',
                    'name' => 'zei_api_secret',
                    'size' => 45,
                    'required' => true
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Use HTTPS'),
                    'desc' => 'Use or not secure API requests.',
                    'name' => 'zei_api_https',
                    'required'  => true,
                    'is_bool'   => true,
                    'values'    => array(
                        array(
                            'id'    => 'zei_api_https_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id'    => 'zei_api_https_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $key = $helper->fields_value['zei_api_key'] = Configuration::get('zei_api_key');
        $secret = $helper->fields_value['zei_api_secret'] = Configuration::get('zei_api_secret');

        if($key && $secret) {
            $offers = zei_api::getOffersList();
            if($offers) {
                $query = array(array('key' => 0, 'name' => ''));
                foreach($offers as $id => $name) {
                    array_push($query, array('key' => $id, 'name' => $name));
                }

                array_push($form[0]['form']['input'], array(
                    'type' => 'select',
                    'label' => $this->l('Global offer'),
                    'desc' => $this->l('Use a ZEI offer for the whole store.'),
                    'name' => 'zei_global_offer',
                    'required' => false,
                    'options' => array(
                        'query' => $query,
                        'id' => 'key',
                        'name' => 'name'
                    )
                ));

                $global = Configuration::get('zei_global_offer');
                $helper->fields_value['zei_global_offer'] = $global ? $global : 0;
            }
        }

        $https = Configuration::get('zei_api_https');
        $helper->fields_value['zei_api_https'] = ($https == 0 || $https == 1) ? $https : 1;

        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.$token
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.$token,
                'desc' => $this->l('Back to list')
            )
        );

        return $helper->generateForm($form);
    }

    public function alterTable($remove = false) {
        if($remove) {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product DROP COLUMN `zei_offer`;';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders DROP COLUMN `zei_profile`;';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders DROP COLUMN `zei_validation`;';
        } else {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product ADD COLUMN `zei_offer` int NOT NULL;';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders ADD COLUMN `zei_profile` text NOT NULL;';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders ADD COLUMN `zei_validation` text NOT NULL;';
        }
        return Db::getInstance()->Execute($sql);
    }

    public function hookDisplayAdminProductsExtra($params) {
        $errors = "";

        if(!($key = Configuration::get('zei_api_key'))) {
            $errors .= "Your Zei API key is not set...".PHP_EOL;
        }

        if(!($secret = Configuration::get('zei_api_secret'))) {
            $errors .= "Your Zei API secret is not set...".PHP_EOL;
        }

        if(!$errors) {
            /*if(Configuration::get('zei_global_offer')) {
                return "You set a global offer :)";
            }*/

            // First try
            $id = Tools::getValue('id_product');

            // Second try
            if(!$id) {
                if(is_object($params)) {
                    $id = $params['request']->attributes->get('id');
                } else if(is_array($params['request']) && array_key_exists('id', $params['request'])) {
                    $id = $params['request']['id'];
                } else {
                    return "Error : Unavailable product id";
                }
            }

            // Getting the product
            $product = new Product((int)$id);

            // Updating the product
            if($product && isset($product->id)) {
                $this->context->smarty->assign(array(
                    'zei_offer_list' => zei_api::getOffersList(),
                    'zei_offer_product' => $product->zei_offer
                ));
                return $this->display(__FILE__, 'views/field.tpl');
            }
        }

        return $errors;
    }

    public function hookDisplayPaymentTop($params) {
        if(
            ($cart = $params['cart']) &&
            ($key = Configuration::get('zei_api_key')) &&
            ($secret = Configuration::get('zei_api_secret'))
        ) {
            if($globalOffer = Configuration::get('zei_global_offer')) {
                $this->context->smarty->assign(array('zei_script' => zei_api::getScriptUrl()));
                return $this->display(__FILE__, 'views/module.tpl');
            } else {
                foreach($params['cart']->getProducts() as $cartProduct) {
                    if(($id = $cartProduct['id_product']) && ($product = new Product($id)) && $product->zei_offer) {
                        $this->context->smarty->assign(array('zei_script' => zei_api::getScriptUrl()));
                        return $this->display(__FILE__, 'views/module.tpl');
                    }
                }
            }
        }
        return null;
    }

    public function hookDisplayOrderConfirmation($params) {
        $keyParam = key_exists('order', $params) ? 'order' : 'objOrder';
        if(($cookie = $_COOKIE["zei"]) && ($order = $params[$keyParam])) {
            $order->zei_profile = $cookie;
            $order->save();
            unset($_COOKIE['zei']);
        }
    }

    public function hookActionOrderStatusPostUpdate($params) {
        if(key_exists("id_order", $params) && key_exists("newOrderStatus", $params)) {
            // Ordered by Zei User
            if(($order = new Order($params['id_order'])) && $order->zei_profile) {
                // Order not validated yet
                if($params['newOrderStatus']->paid) {
                    $validation = $order->zei_validation === '' ? [] : json_decode($order->zei_validation);
                    $globalOffer = Configuration::get('zei_global_offer');

                    foreach($order->getCartProducts() as $index => $cartProduct) {
                        if(($product = new Product($cartProduct['id_product'])) &&
                            (!key_exists($index, $validation) || $validation[$index] != 1)) {
                            //PRODUCT PRIORITY BEFORE GLOBAL
                            $offerId = $product->zei_offer ? $product->zei_offer : ($globalOffer ? $globalOffer : null);

                            if($offerId) {
                                $response = zei_api::validateOffer($offerId, $order->zei_profile, $cartProduct['product_quantity']);
                                if (!$response) $validation[$index] = 0;
                                elseif (isset($response['code'])) $validation[$index] = $response['code'];
                                else $validation[$index] = 1;
                            }
                        }
                    }
                    $order->zei_validation = json_encode($validation);
                    $order->save();
                }
            }
        }
    }

}
