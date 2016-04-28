=== Woo Mercado Pago Module ===
Contributors: mercadopago, mercadolivre
Donate link: https://www.mercadopago.com.br/developers/
Tags: ecommerce, mercadopago
Requires at least: WooCommerce 2.1.x
Tested up to: WooCommerce 2.5.x
Stable tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is the oficial module of Mercado Pago for WooCommerce plugin.

== Description ==

This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.<br />
Mercado Pago owns the highest security standards with PCI certification level 1 and a specialized internal team working on fraud analysis. With Mercado Pago module, you will be able to accept payments from the most common brands of credit card, offer purchase installments options and receive your payment with antecipation. You can also enable your customers to pay in the web or in their mobile devices.<br />
Download now and receive payments with Mercado Pago.<br /><br />

- Online and real-time processment;<br />
- High approval rate with a robust analysis of fraud;<br />
- Potential new customers with a base of more than 120 millions of users in Latin America;<br />
- PCI Level 1 Certification;<br />
- Support to major credit card brands;<br />
- Payment installments;<br />
- Anticipation of receivables in D+2 or D+14 (According to Mercado Pago terms and conditions);<br />
- Payment in one click with Mercado Pago standard checkout;<br />
- Seller's Protection Program;<br />

== Installation ==

You can directly download and install this module from your **WordPress/WooCommerce** store by going in **Plugins > Add New** and typing "**Woo Mercado Pago Module**" in search field. <br />

To manually install, you can download the module either from: <br />

- Github: https://www.mercadopago.com/mla/herramientas/aplicaciones
- WordPress Plugin Directory: https://br.wordpress.org/plugins/woo-mercado-pago-module/

and follow bellow steps:

1. Copy **cart-woocommerce/woo-mercado-pago-module** folder to **[WordPressRootDirectory]/wp-content/plugins/** folder.

2. On your store administration, go to **Plugins** option in sidebar.

3. Search by **WooCommerce Mercado Pago** and click enable. <br />
You will receive the following message: "Plugin enabled." as a notice in your WordPress.

= Configuration =
1. Go to **WooCommerce > Configuration > Checkout Tab > Mercado Pago**. <br />
Fist of all, you need to configure your client credentials. To make it, fill your **Client_id**, **Client_secret** in Mercado Pago Credentials section.
	
	You can obtain your **Client_id** and **Client_secret**, accordingly to your country, in the following links:

	* Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones
	* Brazil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
	* Chile: https://www.mercadopago.com/mlc/herramientas/aplicaciones
	* Colombia: https://www.mercadopago.com/mco/herramientas/aplicaciones
	* Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones
	* Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones

2. Other general configurations. <br />
	* **Instant Payment Notification (IPN) URL**
	The highlighted URL is where you will get notified about payment updates.<br /><br />
	* **Checkout Options**
	**Title**: This is the title of the payment option that will be shown to your customers;<br />
	**Description**: This is the description of the payment option that will be shown to your customers;<br />
	**Store Category**: Sets up the category of the store;<br />
	**Store Identificator**: A prefix to identify your store, when you have multiple stores for only one Mercado Pago account;<br />
	**Integration Method**: How your customers will interact with Mercado Pago to pay their orders;<br />
	**iFrame Width**: The width, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**iFrame Height**: The height, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**Auto Return**: If set, the platform will return to your store when the payment is approved.<br /><br />
	* **Payment Options**
	**Max Installments**: The maximum installments allowed for your customers;<br />
	**Exclude Payment Methods**: Select the payment methods that you want to not work with Mercado Pago.<br /><br />
	* **Test and Debug Options**
	**Mercado Pago Sandboxs**: Test your payments in Mercado Pago sandbox environment;<br />
	**Debug and Log**: Enables/disables system logs.<br />

== Frequently Asked Questions ==

= What is Mercado Pago? =
Please, take a look: https://vimeo.com/125253122

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
* Proper information of shipment cost.

= v1.0.2 (23/03/2016) =
* IPN URL wasn’t triggered when topic=payment.

= v1.0.3 (23/03/2016) =
* Improving algorithm when processing IPN.

= v1.0.4 (15/04/2016) =
* Added a link to module settings page in plugin page;
* Several bug fixes;
* Fixed status change when processing with two cards.

= v1.0.5 (29/04/2016) =
* Removal of extra shipment setup in checkout view;
* Translation to es_ES;
* Some bug fixes and code improvements.
	
== Upgrade Notice ==

Please refer to our github repo.
