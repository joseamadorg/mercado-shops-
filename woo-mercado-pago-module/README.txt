=== Woo Mercado Pago Module Oficial ===
Contributors: mercadopago, mercadolivre
Donate link: https://www.mercadopago.com.br/developers/
Tags: ecommerce, mercadopago, woocommerce
Requires at least: WooCommerce 2.1.x
Tested up to: WooCommerce 2.5.x
Stable tag: 2.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is the oficial module of Mercado Pago for WooCommerce plugin.

== Description ==

This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.

= Why chose Mercado Pago =
Mercado Pago owns the highest security standards with PCI certification level 1 and a specialized internal team working on fraud analysis. With Mercado Pago, you will be able to accept payments from the most common brands of credit card, offer purchase installments options and receive your payment with antecipation. You can also enable your customers to pay in the web or in their mobile devices.

= Mercado Pago Main Features =
* Online and real-time processment through IPN mechanism;
* High approval rate with a robust fraud analysis;
* Potential new customers with a base of more than 120 millions of users in Latin America;
* PCI Level 1 Certification;
* Support to major credit card brands;
* Payment installments;
* Anticipation of receivables in D+2 or D+14 (According to Mercado Pago terms and conditions);
* Payment in one click with Mercado Pago standard and custom checkouts;
* Payment via tickets;
* Seller's Protection Program.

== Installation ==

You have two way to install this module: from your WordPress Store, or by downloading and manually copying the module directory.

[youtube https://www.youtube.com/watch?v=CgV9aVlx5SE]

= Install from WordPress =
1. On your store administration, go to **Plugins** option in sidebar;
2. Click in **Add New** button and type "Woo Mercado Pago Module" in the **Search Plugins** text field. Press Enter;
3. You should find the module read to be installed. Click install.

= Manual Download =
1. Get the module sources from a repository (<a href="https://github.com/mercadopago/cart-woocommerce/archive/master.zip">Github</a> or <a href="https://downloads.wordpress.org/plugin/woo-mercado-pago-module.2.0.0.zip">WordPress Plugin Directory</a>);
2. Unzip the folder and find "woo-mercado-pago-module" directory;
3. Copy "woo-mercado-pago-module" directory to **[WordPressRootDirectory]/wp-content/plugins/** directory.

To confirm that your module is really installed, you can click in **Plugins** item in the store administration menu, and check your just installed module. Just click **enable** to activate it and you should receive the message "Plugin enabled." as a notice in your WordPress.

= Configuration =
1. On your store administration, go to **WooCommerce > Settings > Checkout** tab. In **Checkout Options**, you can find configurations for **Mercado Pago - Standard Checkout**, **Mercado Pago - Custom Checkout**, and **Mercado Pago - Ticket**.
	* To get your **Client_id** and **Client_secret** for your country, you can go to:
		* Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones
		* Brazil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
		* Chile: https://www.mercadopago.com/mlc/herramientas/aplicaciones
		* Colombia: https://www.mercadopago.com/mco/herramientas/aplicaciones
		* Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones
		* Peru: https://www.mercadopago.com/mpe/herramientas/aplicaciones
		* Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones
	* And to get your **Public Key**/**Access Token** you can go to:
		* Argentina: https://www.mercadopago.com/mla/account/credentials?type=custom
		* Brazil: https://www.mercadopago.com/mlb/account/credentials?type=custom
		* Chile: https://www.mercadopago.com/mlc/account/credentials?type=custom
		* Colombia: https://www.mercadopago.com/mco/account/credentials?type=custom
		* Mexico: https://www.mercadopago.com/mlm/account/credentials?type=custom
		* Peru: https://www.mercadopago.com/mpe/account/credentials?type=custom
		* Venezuela: https://www.mercadopago.com/mlv/account/credentials?type=custom
2. For the solutions **Mercado Pago - Standard Checkout**, **Mercado Pago - Custom Checkout**, and **Mercado Pago - Ticket**, you can:
	* Enable/Disable you plugin (for all solutions);
	* Set up your credentials (Client_id/Client_secret for Standard, Public Key/Access Token for Custom and Ticket);
	* Check your IPN URL, where you will get notified about payment updates (for all solutions);
	* Set the title of the payment option that will be shown to your customers (for all solutions);
	* Set the description of the payment option that will be shown to your customers (for all solutions);
	* Set the description that will be shown in your customer's invoice (for Custom and Ticket);
	* Set binary mode that when charging a credit card, only [approved] or [reject] status will be taken (only for Custom);
	* Set the category of your store (for all solutions);
	* Set a prefix to identify your store, when you have multiple stores for only one Mercado Pago account (for all solutions);
	* Define how your customers will interact with Mercado Pago to pay their orders (only for Standard);
	* Configure the after-pay return behavior (only for Standard);
	* Configure the maximum installments allowed for your customers (only for Standard);
	* Configure the payment methods that you want to not work with Mercado Pago (only for Standard);
	* Enable/disable sandbox mode, where you can test your payments in Mercado Pago sandbox environment (for all solutions);
	* Enables/disable system logs (for all solutions).

== Frequently Asked Questions ==

= What is Mercado Pago? =
Please, take a look: https://vimeo.com/125253122

= Any questions? =
Please, check our FAQ at: https://www.mercadopago.com.br/ajuda/

== Screenshots ==

1. `Configuration of Standard Checkout`

2. `Custom Checkout`

3. `One Click Payment`

4. `Tickets`

== Changelog ==

= v2.0.2 (13/06/2016) =
* Rollout to Peru
* Fix a PHP version issue

= v2.0.1 (09/06/2016) =
* Customer Cards (<a href="https://www.youtube.com/watch?v=_KB8CtDei_4>One Click Payment</a>);
* SSL verifications for custom checkout and ticket.

= v2.0.0 (01/06/2016) =
* Custom Checkout for LatAm;
* <a href="https://www.youtube.com/watch?v=97VSVx5Uaj0">Ticket</a> for LatAm;
* Removed possibility to setting supportable but invalid currency.

= v1.0.5 (29/04/2016) =
* Removal of extra shipment setup in checkout view;
* Translation to es_ES;
* Some bug fixes and code improvements.

= v1.0.4 (15/04/2016) =
* Added a link to module settings page in plugin page;
* Several bug fixes;
* Fixed status change when processing with two cards.

= v1.0.3 (23/03/2016) =
* Improving algorithm when processing IPN.

= v1.0.2 (23/03/2016) =
* IPN URL wasn’t triggered when topic=payment.

= v1.0.1 (23/03/2016) =
* Added payment ID in order custom fields information;
* Removed some unused files/code;
* Redesign of the logic of preferences when creating cart, separating items;
* Proper information of shipment cost.

= v1.0.0 (16/03/2016) =
* LatAm <a href="https://www.youtube.com/watch?v=DgOsX1eXjBU>Standard Checkout</a> support;
* Title, description, category, and external reference customizations;
* Integrations via iframe, modal, and redirection, with configurable auto-returning;
* Max installments and payment method exclusion setup;
* Sandbox and debug options.
	
== Upgrade Notice ==

If you're migrating from version 1.x.x to 2.x.x, please be sure to make a backup of your site and database, as there are many additional features and modifications between these versions.