=== Woo Mercado Pago Module ===
Contributors: mercadopago, mercadolivre
Donate link: https://www.mercadopago.com.br/developers/
Tags: ecommerce, mercadopago
Requires at least: WooCommerce 2.1.x
Tested up to: WooCommerce 2.5.x
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is the oficial module of Mercado Pago for WooCommerce plugin.

== Description ==

This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.

== Installation ==

1. Copy **cart-woocommerce/mercadopago** folder to **[WordPressRootDirectory]/wp-content/plugins/** folder.

2. On your store administration, go to **Plugins** option in sidebar.

3. Search by **WooCommerce Mercado Pago** and click enable. <br />
You will receive the following message: "Plugin enabled." as a notice in your WordPress.

== Frequently Asked Questions ==

= Any questions? =

Please, check our FAQ at: https://www.mercadopago.com.br/ajuda/

== Screenshots ==

1. `/README.img/wc_mp_settings.png`

== Changelog ==

= v1.0.0 (16/03/2016) =
* LatAm support;
* Title, description, category, and external reference customizations;
* Integrations via iframe, modal, and redirection, with configurable auto-returning;
* Max installments and payment method exclusion setup;
* Sandbox and debug options.

= v1.0.1 (23/03/2016) =
* Added payment ID in order custom fields information;
* Removed some unused files/code;
* Redesign of the logic of preferences when creating cart, separating items;
* Proper information of shipment cost

= v1.0.2 (23/03/2016) =
* IPN URL wasn’t triggered when topic=payment

= v1.0.3 (23/03/2016) =
* Improving algorithm when processing IPN

= v1.0.4 (05/04/2016) =
* Added a link to module settings page in plugin page

== Upgrade Notice ==

Please refer to our github repo.

== Installation ==

1. Go to **WooCommerce > Configuration > Checkout Tab > Mercado Pago**. <br />
Fist of all, you need to configure your client credentials. To make it, fill your **Client_id**, **Client_secret** in Mercado Pago Credentials section.
	
	![Installation Instructions](/README.img/wc_setup_credentials.png) <br />
	
	You can obtain your **Client_id** and **Client_secret**, accordingly to your country, in the following links:

	* Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones
	* Brazil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
	* Chile: https://www.mercadopago.com/mlc/herramientas/aplicaciones
	* Colombia: https://www.mercadopago.com/mco/herramientas/aplicaciones
	* Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones
	* Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones

2. Other general configurations. <br />
	* **Instant Payment Notification (IPN) URL**
	![Installation Instructions](/README.img/wc_setup_ipn.png) <br />
	The highlighted URL is where you will get notified about payment updates.<br /><br />
	* **Checkout Options**
	![Installation Instructions](/README.img/wc_setup_checkout.png) <br />
	**Title**: This is the title of the payment option that will be shown to your customers;<br />
	**Description**: This is the description of the payment option that will be shown to your customers;<br />
	**Store Category**: Sets up the category of the store;<br />
	**Store Identificator**: A prefix to identify your store, when you have multiple stores for only one Mercado Pago account;<br />
	**Integration Method**: How your customers will interact with Mercado Pago to pay their orders;<br />
	**iFrame Width**: The width, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**iFrame Height**: The height, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**Auto Return**: If set, the platform will return to your store when the payment is approved.<br /><br />
	* **Payment Options**
	![Installation Instructions](/README.img/wc_setup_payment.png) <br />
	**Max Installments**: The maximum installments allowed for your customers;<br />
	**Exclude Payment Methods**: Select the payment methods that you want to not work with Mercado Pago.<br /><br />
	* **Test and Debug Options**
	![Installation Instructions](/README.img/wc_setup_testdebug.png) <br />
	**Mercado Pago Sandboxs**: Test your payments in Mercado Pago sandbox environment;<br />
	**Debug and Log**: Enables/disables system logs.<br />
	
