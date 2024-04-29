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
class NiubizValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        if ($this->module->active == false) {
            die;
        }

        $nbz_error = base64_decode(Tools::getValue('nbz_error'));

        $cart = Context::getContext()->cart;
        $customer = Context::getContext()->customer;

        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $securityKey = $this->module->securityKey();
        setcookie("niubizkey-".$cart->id, $securityKey);

        $sessionToken = $this->module->createToken($amount, $securityKey);
        $userTokenId = $this->module->userTokenId();

        $session = array();
        $session['id_cart'] = (int)$cart->id;
        $session['id_customer'] = (int)$customer->id;
        $session['sessiontoken'] = pSQL($sessionToken);
        $session['sessionkey'] = pSQL($userTokenId);

        Db::getInstance()->insert('niubiz_session', $session);
        
        $variables = array(
            'userTokenId' => $userTokenId,
            'sessionToken' => $sessionToken,
            'merchantId' => $this->module->merchantid,
            'urlScript' => $this->module->urlScript,
            'numOrden' => (int)$cart->id,
            'monto' => $amount,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
        );
        
        $params_encoded = base64_encode($cart->id.'|'.$customer->secure_key);
        
        $this->context->smarty->assign(array(
            'logo' => Configuration::get('NBZ_LOGO'),
            'nbz_error' => $nbz_error,
            'nbz_debug' => (bool) Configuration::get('NBZ_DEBUG'),
            'psVersion' => $this->module->psVersion,
            'var' => $variables,
            'linkActionReturn' => $this->context->link->getModuleLink($this->module->name, 'return', ['nbzheader' => $params_encoded], true),
        ));

        $this->setTemplate('module:niubiz/views/templates/front/validation.tpl');
    }
}