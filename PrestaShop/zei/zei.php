<?php

if(!defined('_PS_VERSION_')) exit;

include "zei_api.php";

class ZEI extends Module {

    public function __construct() {
        $this->name = 'zei';
        $this->tab = 'zei_api';
        $this->version = '1.0';
        $this->author = 'Nazim from ZEI';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Zero ecoimpact');
        $this->description = $this->l('Link your company offers and rewards from Zero ecoimpact !');

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
            $this->registerHook('actionPaymentConfirmation')
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
        $helper->token = Tools::getAdminTokenLite('AdminModules');
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
            $token = zei_api::getToken($key, $secret);
            if($token) {
                $offers = zei_api::getOffersList($token);
                if($offers) {
                    $query = array(array('key' => 0, 'name' => ''));
                    foreach($offers as $key => $name) {
                        array_push($query, array('key' => $key, 'name' => $name));
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
        }

        $https = Configuration::get('zei_api_https');
        $helper->fields_value['zei_api_https'] = ($https == 0 || $https == 1) ? $https : 1;

        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        return $helper->generateForm($form);
    }

    public function alterTable($remove = false) {
        if($remove) {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product DROP COLUMN IF EXISTS `zei_offer`; ';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders DROP COLUMN IF EXISTS `zei_token`; ';
        } else {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product ADD IF NOT EXISTS `zei_offer` int NOT NULL; ';
            $sql .= 'ALTER TABLE ' . _DB_PREFIX_ . 'orders ADD IF NOT EXISTS `zei_token` text NOT NULL; ';
        }
        return Db::getInstance()->Execute($sql);
    }

    public function hookDisplayAdminProductsExtra($params) {
        $errors = "";

        if(!($key = Configuration::get('zei_api_key'))) {
            $errors .= "Your Zero ecoimpact API key is not set...".PHP_EOL;
        }

        if(!($secret = Configuration::get('zei_api_secret'))) {
            $errors .= "Your Zero ecoimpact API secret is not set...".PHP_EOL;
        }

        if(!$errors) {
            if(Configuration::get('zei_global_offer')) {
                return "You set a global offer !";
            } else if(
                ($id = (int)Tools::getValue('id_product')) ||
                ($id = (int)$params['request']->attributes->get('id'))
            ) {
                $product = new Product($id);
                if($product && isset($product->id)) {
                    $token = zei_api::getToken($key, $secret);
                    $list = zei_api::getOffersList($token);
                    $this->context->smarty->assign(array(
                        'zei_offer_list' => $list,
                        'zei_offer_product' => $product->zei_offer
                    ));
                    return $this->display(__FILE__, 'views/field.tpl');
                }
            }
        }

        return $errors;
    }

    public function hookDisplayPaymentTop($params) {
        if(
            ($cart = $params['cart']) &&
            ($key = Configuration::get('zei_api_key')) &&
            ($secret = Configuration::get('zei_api_secret')) &&
            ($token = zei_api::getToken($key, $secret))

        ) {
            foreach($params['cart']->getProducts() as $cartProduct) {
                if(($id = $cartProduct['id_product']) && ($product = new Product($id)) && $product->zei_offer) {
                    $cookie = new Cookie('zei');
                    $cookie->setExpire(time() + 20 * 60);
                    $cookie->token = $token;
                    $cookie->write();
                    $this->context->smarty->assign(array('zei_token' => zei_api::getModuleUrl($token, true, true)));
                    return $this->display(__FILE__, 'views/module.tpl');
                }
            }
        }
        return null;
    }

    public function hookDisplayOrderConfirmation($params) {
        $cookie = new Cookie('zei');
        if($cookie && $cookie->token && ($order = $params['order'])) {
            $order->zei_token = $cookie->token;
            $order->save();
            $cookie->logout();
        }
    }

    public function hookActionPaymentConfirmation($params) {
        if(($order = new Order($params['id_order'])) && $order->zei_token) {
            $globalOffer = Configuration::get('zei_global_offer');
            foreach($params['cart']->getProducts() as $cartProduct) {
                if(($product = new Product($cartProduct['id_product'])) && $product->zei_offer) {
                    // TODO : Gérer la validation de plusieurs offres
                    zei_api::validateOffer($order->zei_token, ($globalOffer ? $globalOffer : $product->zei_offer));
                    break;
                }
            }
        }
    }

}