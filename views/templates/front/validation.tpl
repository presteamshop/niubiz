{*
* 2007-2021 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2019 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends file='page.tpl'}

{block name='page_title'}
  {l s='Confirmacion de pago' d='Modules.Niubiz.Validation'}
{/block}

{block name='page_content'}

<style>
    .blockcart.dropdown_wrap.top_bar_item.shopping_cart_style_0.clearfix {
        display: none;
}
</style>

{if isset($nbz_error) && $nbz_error != ''}
<p class="alert alert-danger">
  {$nbz_error}
</p>
{/if}

<div class="text-center">
    {if isset($nbz_error) && $nbz_error != ''}
        <p>{l s='Vuelva a intentar el pago con otra tarjeta o verifique que los datos fueron ingresados correctamente.' d='Modules.Niubiz.Validation'}</p>
    {else}
        <p>{l s='Para procesar el pago por favor haga click en el siguiente boton y complete los datos de su tarjeta.' d='Modules.Niubiz.Validation'}</p>
    {/if}
    <br>
    <div class="row">
        <div class="col-xs-12 col-md-12">
        <button class='btn btn-success start-js-btn modal-opener default' onclick='openNiubiz()'>PAGA AQUÍ</button>
        <script src="{$var.urlScript|escape:'html':'UTF-8'}"></script>
        </div>
    </div>
</div>

<br></br>
<a class="fl" href="{$link->getPageLink('order', true, null, 'step=3')}"> <i class="fto-left fto_mar_lr2"></i>{l s='Cambiar metodo de pago' d='Modules.Niubiz.Validation'}</a>

  <script> 
  window.onload=openNiubiz;
    function openNiubiz() {
      VisanetCheckout.configure({
          hidexbutton: true,
        sessiontoken:'{$var.sessionToken|escape:'htmlall':'UTF-8'}',
        merchantid:'{$var.merchantId|escape:'htmlall':'UTF-8'}',
        channel:'web',
        buttonsize:'',
        buttoncolor:'' ,
        merchantlogo:'http://{$logo|escape:'htmlall':'UTF-8'}',
        merchantname: "",
        formbuttoncolor: "#0A0A2A",
        showamount: "{$var.monto|escape:'htmlall':'UTF-8'}",
        purchasenumber:"{$var.numOrden|escape:'htmlall':'UTF-8'}",
        amount:"{$var.monto|escape:'htmlall':'UTF-8'}",
        recurrence: "",
        recurrencefrequency: "",
        recurrencetype: "",
        recurrenceamount: "",
        recurrencemaxamount: "",
        timeouturl: "{$linkActionReturn}",
        action: "{$linkActionReturn}"
      });
      VisanetCheckout.open();
    }
  </script>
  

{if $nbz_debug}
  <br>
  <pre style="background: #eeeeee;">
  {$var|print_r:true}
  linkReturn={$linkReturn}
  </pre>
{/if}

<br></br>
{/block}
