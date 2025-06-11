<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Niubiz extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'niubiz';
        $this->tab = 'payments_gateways';
        $this->version = '3.1.0';
        $this->author = 'Victor Castro';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        $this->views = _MODULE_DIR_.$this->name.'/views/';
        $this->domain = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__;
        $this->url_return = $this->domain.'index.php?fc=module&module='.$this->name.'&controller=notifier';
        $this->callback = $this->domain.'index.php?fc=module&module='.$this->name.'&controller=callback';

        parent::__construct();

        $this->displayName = $this->l('Niubiz - Pagos con tarjeta de crédito y débito');
        $this->description = $this->l('Realiza pagos con tu tarjeta de crédito y débito en Perú');
        $this->email = "integraciones.niubiz@necomplus.com";
        $this->github = "https://github.com/IntegracionesVisaNet";

        // $this->limited_countries = array('PE');
        $this->limited_currencies = array('USD', 'PEN');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->psVersion = _PS_VERSION_;

        if (function_exists('curl_init') == false) {
            $this->warning = $this->trans('In order to use this module, activate cURL (PHP extension).', array(), 'Modules.Niubiz.Admin');
        }


        $currency = new Currency($this->context->cookie->id_currency);

        switch ($currency->iso_code) {
            case 'PEN':
                $this->merchantid = Configuration::get('NBZ_MERCHANTID_PEN');
                $this->vsauser = Configuration::get('NBZ_USER_PEN');
                $this->vsapassword = Configuration::get('NBZ_PASSWORD_PEN');
                break;

            case 'USD':
                $this->merchantid = Configuration::get('NBZ_MERCHANTID_USD');
                $this->vsauser = Configuration::get('NBZ_USER_USD');
                $this->vsapassword = Configuration::get('NBZ_PASSWORD_USD');
                break;

            default:
                $this->merchantid = '';
                $this->vsauser = '';
                $this->vsapassword = '';
                break;
        }

        switch (Configuration::get('NBZ_ENVIROMENT')) {
            case 'PRD':
                $this->security_api = 'https://apiprod.vnforapps.com/api.security/v1/security';
                $this->session_api = 'https://apiprod.vnforapps.com/api.ecommerce/v2/ecommerce/token/session/'.$this->merchantid;
                $this->authorization_api = 'https://apiprod.vnforapps.com/api.authorization/v3/authorization/ecommerce/'.$this->merchantid;
                $this->urlScript = 'https://static-content.vnforapps.com/v2/js/checkout.js';
                break;

            case 'DEV':
                $this->security_api = 'https://apisandbox.vnforappstest.com/api.security/v1/security';
                $this->session_api = 'https://apisandbox.vnforappstest.com/api.ecommerce/v2/ecommerce/token/session/'.$this->merchantid;
                $this->authorization_api = 'https://apisandbox.vnforappstest.com/api.authorization/v3/authorization/ecommerce/'.$this->merchantid;
                $this->urlScript = 'https://static-content-qas.vnforapps.com/v2/js/checkout.js?qa=true';
                break;
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        $link = new Link;
        Configuration::updateValue('NBZ_LOGO', $link->getMediaLink(_PS_IMG_.Configuration::get('PS_LOGO')));
        Configuration::updateValue('NBZ_PAYMENT_OPTION_TEXT', $this->displayName);

        $this->createStateIfNotExist();

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayAdminOrderSideBottom') &&
            $this->registerHook('displayPaymentReturn');
    }

    private function createStateIfNotExist()
    {
        if (!Configuration::get('NBZ_STATE_WAITING_CAPTURE')) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
              $order_state->name[$language['id_lang']] = 'En espera de pago por Niubiz';
            }
            $order_state->module_name = $this->name;
            $order_state->color = '#4169E1';
            $order_state->send_email = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->pdf_invoice = false;
            $order_state->add();
            Configuration::updateValue('NBZ_STATE_WAITING_CAPTURE', (int)$order_state->id);
        }
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('NBZ_LOGO')
        || !Configuration::deleteByName('NBZ_PAYMENT_OPTION_TEXT')
        || !Configuration::deleteByName('NBZ_PAYMENT_OPTION_LOGO')
         || !Configuration::deleteByName('NBZ_MERCHANTID_PEN')
         || !Configuration::deleteByName('NBZ_USER_PEN')
         || !Configuration::deleteByName('NBZ_PEN')
         || !Configuration::deleteByName('NBZ_USD')
         || !Configuration::deleteByName('NBZ_DEBUG')
         || !Configuration::deleteByName('NBZ_PASSWORD_PEN')
         || !Configuration::deleteByName('NBZ_MERCHANTID_USD')
         || !Configuration::deleteByName('NBZ_ACCESSKEY_USD')
         || !Configuration::deleteByName('NBZ_SECRETKEY_USD')
         || !parent::uninstall()) {
            return false;
        }

        return parent::uninstall();
    }

    public function hookDisplayAdminOrderSideBottom($params)
    {
        $orderId = $params['id_order'];

        $request = 'SELECT * FROM `'._DB_PREFIX_.'niubiz_log` WHERE id_order = "'.$orderId.'"';

        $row = Db::getInstance()->getRow($request);

        $this->context->smarty->assign(array(
            'logNbz' => $row,
            'params' => $params,
            'logoNiubiz' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/.docs/').'niubiz.jpg'
        ));

        return $this->display(__FILE__, 'displayAdminOrderSideBottom.tpl');
    }

    private function postValidation()
    {
        $errors = array();

        if ((bool)Tools::isSubmit('submitNiubizModule')) {
            if (empty(Tools::getValue('NBZ_LOGO'))) {
                $errors[] = $this->trans('El Logo es obligatorio');
            }
        }

        if (count($errors)) {
            $this->html .= $this->displayError(implode('<br />', $errors));
            return false;
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if ($this->postValidation()) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitNiubizModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {

        $fields_form = array();

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->trans('FRONT CONFIGURATION', array(), 'Modules.Niubiz.Admin'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->trans('Logo URL', array(), 'Modules.Niubiz.Admin'),
                    'desc' => 'La url tiene que ir sin '.Tools::getShopProtocol(). ', solo coloque el dominio y la ruta de la imagen',
                    'name' => 'NBZ_LOGO',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Nombre de pago', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Nombre que saldra en las opciones de pago', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_TEXT',
                    'required' => true,
                    'lang' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Mostrar logo', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_LOGO_SHOW',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'file_lang',
                    'label' => $this->trans('Logo de pago', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_LOGO',
                    'desc' => 'Si estás usando el tema predeterminado las dimensiones recomendadas son 26x220 px.',
                    'lang' => true,
                ),
            ),
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->trans('Configuracion de Modal', array(), 'Modules.Niubiz.Admin'),
                'icon' => 'icon-credit-card'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label'=>  $this->trans('Enviroment', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_ENVIROMENT',
                    'options' => array(
                        'query' => array(
                            array('id' => 'DEV', 'name' => $this->trans('Integration', array(), 'Modules.Niubiz.Admin')),
                            array('id' => 'PRD', 'name' => $this->trans('Production', array(), 'Modules.Niubiz.Admin')),
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Show pop-up in PaymentOption', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Mostrará la opción de pagar dentro del resumen del pedido al seleccionarlo', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_ENABLE_PAYMENTOPTION',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('URL PagoEfectivo', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Send this url to Niubiz to capture the remote payment of PagoEfectivo.', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_CALLBACK',
                    'required' => false,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Debugger', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_DEBUG',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->trans('SOLES CONFIGURATION', array(), 'Modules.Niubiz.Admin'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Active', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PEN',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Commerce', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_MERCHANTID_PEN',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Email', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USER_PEN',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Password', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PASSWORD_PEN',
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->trans('DOLARES CONFIGURATION', array(), 'Modules.Niubiz.Admin'),

            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Active', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USD',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Commerce', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_MERCHANTID_USD',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Email', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USER_USD',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Password', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PASSWORD_USD',
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        return $fields_form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $languages = Language::getLanguages(false);
        $fields = array();

        $fields['NBZ_DEBUG'] = Tools::getValue('NBZ_DEBUG', Configuration::get('NBZ_DEBUG'));
        $fields['NBZ_LOGO'] = Tools::getValue('NBZ_LOGO', Configuration::get('NBZ_LOGO'));
        $fields['NBZ_ENVIROMENT'] = Tools::getValue('NBZ_ENVIROMENT', Configuration::get('NBZ_ENVIROMENT'));
        $fields['NBZ_MERCHANTID_PEN'] = Tools::getValue('NBZ_MERCHANTID_PEN', trim(Configuration::get('NBZ_MERCHANTID_PEN')));
        $fields['NBZ_USER_PEN'] = Tools::getValue('NBZ_USER_PEN', trim(Configuration::get('NBZ_USER_PEN')));
        $fields['NBZ_PASSWORD_PEN'] = Tools::getValue('NBZ_PASSWORD_PEN', trim(Configuration::get('NBZ_PASSWORD_PEN')));
        $fields['NBZ_MERCHANTID_USD'] = Tools::getValue('NBZ_MERCHANTID_USD', trim(Configuration::get('NBZ_MERCHANTID_USD')));
        $fields['NBZ_USER_USD'] = Tools::getValue('NBZ_USER_USD', trim(Configuration::get('NBZ_USER_USD')));
        $fields['NBZ_PASSWORD_USD'] = Tools::getValue('NBZ_PASSWORD_USD', Configuration::get('NBZ_PASSWORD_USD'));
        $fields['NBZ_PEN'] = Tools::getValue('NBZ_PEN', Configuration::get('NBZ_PEN'));
        $fields['NBZ_USD'] = Tools::getValue('NBZ_USD', Configuration::get('NBZ_USD'));
        $fields['FREE'] = Tools::getValue('FREE', Configuration::get('FREE'));
        $fields['NBZ_CALLBACK'] = Tools::getValue('NBZ_CALLBACK', $this->callback);
        $fields['NBZ_PAYMENT_OPTION_LOGO_SHOW'] = Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_SHOW', Configuration::get('NBZ_PAYMENT_OPTION_LOGO_SHOW'));
        $fields['NBZ_ENABLE_PAYMENTOPTION'] = Tools::getValue('NBZ_ENABLE_PAYMENTOPTION', Configuration::get('NBZ_ENABLE_PAYMENTOPTION'));

        foreach ($languages as $lang) {
            $fields['NBZ_PAYMENT_OPTION_TEXT'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_TEXT_'.$lang['id_lang'], Configuration::get('NBZ_PAYMENT_OPTION_TEXT', $lang['id_lang']));
            $fields['NBZ_PAYMENT_OPTION_LOGO'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang'], Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']));
        }

        return $fields;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $languages = Language::getLanguages(false);
      $values = array();
      $update_images_values = false;

        if (Tools::isSubmit('submitNiubizModule')) {
            Configuration::updateValue('NBZ_LOGO', Tools::getValue('NBZ_LOGO'));
            Configuration::updateValue('NBZ_ENVIROMENT', Tools::getValue('NBZ_ENVIROMENT'));
            Configuration::updateValue('NBZ_MERCHANTID_PEN', Tools::getValue('NBZ_MERCHANTID_PEN'));
            Configuration::updateValue('NBZ_DEBUG', Tools::getValue('NBZ_DEBUG'));
            Configuration::updateValue('NBZ_USER_PEN', Tools::getValue('NBZ_USER_PEN'));
            Configuration::updateValue('NBZ_PASSWORD_PEN', Tools::getValue('NBZ_PASSWORD_PEN'));
            Configuration::updateValue('NBZ_MERCHANTID_USD', Tools::getValue('NBZ_MERCHANTID_USD'));
            Configuration::updateValue('NBZ_USER_USD', Tools::getValue('NBZ_USER_USD'));
            Configuration::updateValue('NBZ_PASSWORD_USD', Tools::getValue('NBZ_PASSWORD_USD'));
            Configuration::updateValue('NBZ_PEN', Tools::getValue('NBZ_PEN'));
            Configuration::updateValue('NBZ_USD', Tools::getValue('NBZ_USD'));
            Configuration::updateValue('NBZ_CALLBACK', $this->callback);
            Configuration::updateValue('NBZ_PAYMENT_OPTION_LOGO_SHOW', Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_SHOW'));
            Configuration::updateValue('NBZ_ENABLE_PAYMENTOPTION', Tools::getValue('NBZ_ENABLE_PAYMENTOPTION'));

            foreach ($languages as $lang) {
                if (isset($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']])
                    && isset($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'])
                    && !empty($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'])) {
                    if ($error = ImageManager::validateUpload($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']], 4000000)) {
                        return $error;
                    } else {
                        $ext = substr($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name'], strrpos($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name'], '.') + 1);
                        $file_name = md5($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name']).'.'.$ext;
                        if (!move_uploaded_file($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR .'img' . DIRECTORY_SEPARATOR.$file_name)) {
                            return $this->displayError($this->trans('An error occurred while attempting to upload the file.', array(), 'Admin.Notifications.Error'));
                        } else {
                            if (Configuration::hasContext('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang'], Shop::getContext())
                                && Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']) != $file_name) {
                                @unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']));
                            }

                            $values['NBZ_PAYMENT_OPTION_LOGO'][$lang['id_lang']] = $file_name;
                        }
                    }

                    $update_images_values = true;
                }

                $values['NBZ_PAYMENT_OPTION_TEXT'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_TEXT_'.$lang['id_lang']);
            }

            if ($update_images_values) {
                Configuration::updateValue('NBZ_PAYMENT_OPTION_LOGO', $values['NBZ_PAYMENT_OPTION_LOGO']);
            }

            Configuration::updateValue('NBZ_PAYMENT_OPTION_TEXT', $values['NBZ_PAYMENT_OPTION_TEXT']);

            return $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $cart = $this->context->cart;
        $customer = Context::getContext()->customer;
        $currency = new Currency($this->context->cookie->id_currency);
        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $securityKey = $this->securityKey();
        if (!$securityKey) {
            return;
        }
        setcookie("niubizkey-$cart->id", $securityKey);

        $sessionToken = $this->createToken($amount, $securityKey);
        if (!$sessionToken) {
            return;
        }
        $userTokenId = $this->userTokenId();

        if (Configuration::get('NBZ_USD'))
            $this->acceptedCurrency[] = 'USD';
        if (Configuration::get('NBZ_PEN'))
            $this->acceptedCurrency[] = 'PEN';

        $isModuleConfigured = in_array($currency->iso_code, $this->acceptedCurrency);

        $variables = array(
            'userTokenId' => $userTokenId,
            'sessionToken' => $sessionToken,
            'merchantId' => $this->merchantid,
            'urlScript' => $this->urlScript,
            'numOrden' => (int)$cart->id,
            'monto' => $amount,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
        );

        $this->context->smarty->assign(array(
            'logo' => Configuration::get('NBZ_LOGO'),
            'debug' => Configuration::get('NBZ_DEBUG'),
            'psVersion' => $this->psVersion,
            'var' => $variables,
            'checkTotal' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            'linkReturn' => $this->context->link->getModuleLink($this->name, 'confirmation', ['cart_id' => $cart->id, 'secure_key' => $customer->secure_key,], true)
        ));

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setModuleName($this->name)
            ->setCallToActionText($isModuleConfigured ? Configuration::get('NBZ_PAYMENT_OPTION_TEXT', $this->context->language->id) : $this->l('WARNING!! Niubiz OPTION_TEXT is not configured for current languaje'))
            ->setAction($this->context->link->getModuleLink($this->name, 'confirmation', array(), true));

            if (Configuration::get('NBZ_ENABLE_PAYMENTOPTION')) {
                $option->setAdditionalInformation($this->fetch('module:niubiz/views/templates/hook/paymentoption.tpl'));
            }

            if (Configuration::get('NBZ_PAYMENT_OPTION_LOGO_SHOW')) {
                $option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/img/'.Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $this->context->language->id)));
            }

        return [
            $option
        ];
    }

    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = array(
            'cta_text' => Configuration::get('NBZ_PAYMENT_OPTION_TEXT', $this->context->language->id),
            'logo' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/img/'.Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $this->context->language->id)),
            'action' => $this->context->link->getModuleLink($this->name, 'checkout', array(), true)
        );

        return $payment_options;
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $cart = new Cart($params['order']->id_cart);
        $currency = new Currency($params['order']->id_currency);
        $state = $params['order']->getCurrentState();
        $sql = 'SELECT * FROM '._DB_PREFIX_.$this->name.'_log WHERE id_order='.$params['order']->id;
        $total_to_pay = Tools::displayPrice($params['order']->total_paid, $currency, false);

        $in_array = in_array(
            $state,
            array(
                Configuration::get('PS_OS_PAYMENT'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
                Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')
            )
        );

        if ($in_array) {
            $this->smarty->assign('status', 'ok');
        } else {
            $this->smarty->assign('status', 'failed');
        }

        $result = Db::getInstance()->getRow($sql);

        $cms_condiions = new CMS(Configuration::get('PS_CONDITIONS_CMS_ID'), $this->context->language->id);

        $this->context->smarty->assign(array(
            // 'customerName' => $this->context->customer->firstname.' '.$this->context->customer->lastname,
            // 'total_to_pay' => $total_to_pay,
            // 'moneda' => Currency::getCurrencyInstance($this->context->currency->id)->name,
            'link_conditions' => $this->context->link->getCMSLink($cms_condiions, $cms_condiions->link_rewrite, Configuration::get('PS_SSL_ENABLED')),
            // 'products' => $cart->getProducts(),
            'order_id' => $params['order']->id,
            // 'result' => $result,
            // 'total' => $cart->getOrderTotal(),
        ));

        return $this->display(__FILE__, 'confirmation.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    private function logError($message, $context = array()) {
        PrestaShopLogger::addLog(
            'Niubiz: ' . $message,
            3, // Error level
            null,
            'Niubiz',
            null,
            true
        );
    }

    public function securityKey()
    {
        $currency = new Currency($this->context->cookie->id_currency);

        if ($this->vsauser == '') {
            $this->logError('User not found for currency ' . $currency->iso_code);
            return false;
        }
        if ($this->vsapassword == '') {
            $this->logError('Password not found for currency ' . $currency->iso_code);
            return false;
        }

        $header = array("Content-Type: application/json");
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->security_api);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->vsauser:$this->vsapassword");
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $key = curl_exec($ch);

        if ($key === 'Unauthorized access') {
            $this->logError('Unauthorized access to Niubiz API');
            return false;
        }

        return $key;
    }

    public function createToken($amount, $key)
    {
        if (!$key) {
            $this->logError('Could not create token: Invalid security key');
            return false;
        }

        $header = ["Content-Type: application/json", "Authorization: $key"];
        $request_body = '{
            "amount" : '.$amount.',
            "channel" : "web",
            "antifraud" : {
                "clientIp" : "'.$_SERVER["REMOTE_ADDR"].'",
                "merchantDefineData" : {
                    "MDD1" : "web",
                    "MDD2" : "Canl",
                    "MDD3" : "Canl"
                }
            }
        }';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->session_api);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($ch);
        $json = json_decode($response);

        if (!isset($json->sessionKey)) {
            $this->logError('Error creating session token', [
                'session_api' => $this->session_api,
                'response' => $json
            ]);
            return false;
        }

        return $json->sessionKey;
    }

    public function userTokenId()
    {
        mt_srand((double)microtime()*10000);
        $charid = Tools::strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);
        $uuid = chr(123)
            .Tools::substr($charid, 0, 8).$hyphen
            .Tools::substr($charid, 8, 4).$hyphen
            .Tools::substr($charid, 12, 4).$hyphen
            .Tools::substr($charid, 16, 4).$hyphen
            .Tools::substr($charid, 20, 12).$hyphen
            .chr(125);
        $uuid = Tools::substr($uuid, 1, 36);

        return $uuid;
    }
}
