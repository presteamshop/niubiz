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
class NiubizReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ((Tools::isSubmit('nbzheader') == false)) {
            $message = 'Error en modulo de pago: Algo ha fallado, intente nuevamente';
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'validation', ['nbz_error' => base64_encode($message)]));
        }
        
        $params_decoded = base64_decode(Tools::getValue('nbzheader'));
        $params = explode("|", $params_decoded);
        

        $module_name = $this->module->displayName;
        
        $id_cart = $params[0];
        $secure_key = $params[1];
        $transactionToken = Tools::getValue('transactionToken');
        $currency_id = (int) Context::getContext()->currency->id;

        $cart = new Cart((int) $id_cart);
        $customer = new Customer((int) $cart->id_customer);

        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // VALIDATIONS
        if ($cart->id == 0
            || $cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || $transactionToken == ''
            || $secure_key != $customer->secure_key
            || !$this->module->active) {
            
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'niubiz') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->trans('This payment method is not available.', [], 'Modules.Niubiz.Return'));
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }


        

        // NIUBIZ VALIDATION
        $authNiubizResponse = $this->authorization($_COOKIE["niubizkey-$cart->id"], $total, $transactionToken, $cart->id, $cart->id_currency);

        $dataInput = isset($authNiubizResponse['dataMap']) ? 'dataMap' : 'data';

        $sal = [];

        if (isset($_POST['transactionToken']) && isset($_POST['url'])) {
            $ps_os_payment = Configuration::get('NBZ_STATE_WAITING_CAPTURE');
            $message = "Esperando confirmación de PagoEfectivo";
        } else if (isset($authNiubizResponse[$dataInput]) && $authNiubizResponse[$dataInput]['ACTION_CODE'] == "000") {
            $ps_os_payment = Configuration::get('PS_OS_PAYMENT');
            $message = "Pago válido";
        } else {
            $message = 'Error '.$authNiubizResponse['errorCode'].': '.$authNiubizResponse['errorMessage'];
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'validation', ['nbz_error' => base64_encode($message)]));
        } 



        // PRESTASHOP VALIDATION
        $this->module->validateOrder($cart->id, $ps_os_payment, $total, $module_name, $message, array(), $currency_id, false, $secure_key);

        $order = new Order($this->module->currentOrder);
        $order_id = $order->id;

        if ($order_id && ($secure_key == $customer->secure_key)) {
            
            unset($_COOKIE["niubizkey-$cart->id"]);

            if ($ps_os_payment == Configuration::get('NBZ_STATE_WAITING_CAPTURE')) {
                $sal['data']['id_order'] = (int)$order_id;
                $urlPagoEfectivo = Tools::getValue('url');
                $explodeUrlPagoEfectivo = explode('/', $urlPagoEfectivo);
                $operationNumber = strtolower(substr($explodeUrlPagoEfectivo[3], 0, 36));
    
                Db::getInstance()->insert('niubiz_pagoefectivo', [
                    'id_order' => (int)$order_id,
                    'id_cart' => (int)$cart->id,
                    'id_customer' => (int)$customer->id,
                    'operationNumber' => $operationNumber,
                    'channel' => Tools::getValue('channel'),
                    'cip' => Tools::getValue('transactionToken'),
                    'customerEmail' => Tools::getValue('customerEmail'),
                    'url' => Tools::getValue('url'),
                ]);
                Tools::redirect($_POST['url']);
            } else {
                $sal['data']['id_order'] = (int)$order_id;
                $sal['data']['id_cart'] = (int)$cart->id;
                $sal['data']['id_customer'] = (int)$customer->id;
                $sal['data']['pan'] = $authNiubizResponse[$dataInput]['CARD'];;
                $sal['data']['numorden'] = $authNiubizResponse[$dataInput]['TRACE_NUMBER'];
                $sal['data']['dsc_cod_accion'] = $authNiubizResponse[$dataInput]['ACTION_DESCRIPTION'];
                $sal['data']['dsc_eci'] = pSQL($authNiubizResponse[$dataInput]['BRAND']);
                $sal['data']['transactionToken'] = pSQL($transactionToken);
                $sal['data']['aliasName'] = pSQL($authNiubizResponse[$dataInput]['SIGNATURE']);

                Db::getInstance()->insert('niubiz_log', $sal['data']);
    
                $rdc = 'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id;
    
                Tools::redirect($rdc.'&id_order='.$this->module->currentOrder.'&key='.$secure_key);
            }
            
        } else {
            die("else");
            $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');

            $this->setTemplate('module:niubiz/views/templates/front/error.tpl');
        }
    }

    function authorization($key, $amount, $transactionToken, $purchaseNumber, $currencyId)
    {
        $currency = new Currency($currencyId);

        $header = [
            "Content-Type: application/json",
            "Authorization: $key"
        ];

        $request_body = [
            "antifraud" => null,
            "captureType" => "manual",
            "cardHolder" => [
                "documentNumber" => "44444444",
                "documentType" => "1"
            ],
            "channel" => "web",
            "countable" => true,
            "order" => [
                "amount" => $amount,
                "tokenId" => $transactionToken,
                "purchaseNumber" => $purchaseNumber,
                "currency" => $currency->iso_code,
                "productId" => "100"
            ],
            'recurrence' => null,
            'sponsored' => null
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->module->authorization_api);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
}
