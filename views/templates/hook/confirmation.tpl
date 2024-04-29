{*
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
*}

{if (isset($status) == true) && ($status == 'ok')}
	<div class="row">
		<div class="col-sm-12 col-sm-offset-3 clearfix">
			{l s='Guarde y/o imprima esta informaci&oacute;n como recibo de transacci&oacute;n. Tambi&eacute;n puedes consultar nuestro' d='Modules.Niubiz.Confirmation'} <a href="{$link_conditions|escape:'html':'UTF-8'}" class="iframe" data-ajax="false" target="_blank">{l s='T&eacute;rminos y condiciones' d='Modules.Niubiz.Confirmation'}</a>.
			<br /><br />
			<a class="btn btn-primary" href="{$link->getPageLink('order-detail', true, null, "id_order={$order_id|intval}")}" title="{l s='Ver más detalles' d='Modules.Niubiz.Confirmation'}" data-ajax="false">{l s='Ver más detalles' d='Modules.Niubiz.Confirmation'}</a><br />
	
			<br /><br />{l s='Para resolver cualquier duda no dudes en contactar con nosotros' d='Modules.Niubiz.Confirmation'} <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}" data-ajax="false" target="_blank">{l s='Atenci&oacute;n al cliente' d='Modules.Niubiz.Confirmation'}</a>.
		</div>
	</div>
{/if}
	