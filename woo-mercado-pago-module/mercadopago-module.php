<?php

/**
 * Plugin Name: Woo Mercado Pago Module
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: This is the <strong>oficial</strong> module of Mercado Pago for WooCommerce plugin. This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.
 * Author: Mercado Pago
 * Author URI: https://www.mercadopago.com.br/developers/
 * Developer: Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright: Copyright(c) MercadoPago [https://www.mercadopago.com]
 * Version: 2.2.2
 * License: https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * Text Domain: woocommerce-mercadopago-module
 * Domain Path: /languages/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/mercadopago/sdk/lib/mercadopago.php';

// Load module class if it wasn't loaded yet.
if ( ! class_exists( 'WC_WooMercadoPago_Module' ) ) :

	/**
	 * Summary: WooCommerce MercadoPago Module main class.
	 * Description: Used as a kind of manager to enable/disable each Mercado Pago gateway.
	 * @since 1.0.0
	 */
	class WC_WooMercadoPago_Module {

		const VERSION = '2.2.2';

		// Singleton design pattern
		protected static $instance = null;
		public static function init_mercado_pago_gateway_class() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		// Class constructor.
		private function __construct() {

			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Verify if WooCommerce is already installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {

				// Gateways
				include_once dirname( __FILE__ ) . '/mercadopago/mercadopago-gateway.php';
 				include_once dirname( __FILE__ ) . '/mercadopago/mercadopago-custom-gateway.php';
				include_once dirname( __FILE__ ) . '/mercadopago/mercadopago-ticket-gateway.php';
				include_once dirname( __FILE__ ) . '/mercadopago/mercadopago-subscription-gateway.php';

				include_once dirname( __FILE__ ) . '/mercadopago/class-wc-product-mp_recurrent.php';

				// Shipping.
				include_once dirname( __FILE__ ) . '/shipment/abstract-wc-mercadoenvios-shipping.php';
				include_once dirname( __FILE__ ) . '/shipment/class-wc-mercadoenvios-shipping-normal.php';
				include_once dirname( __FILE__ ) . '/shipment/class-wc-mercadoenvios-shipping-express.php';
				include_once dirname( __FILE__ ) . '/shipment/class-wc-mercadoenvios-package.php';

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_filter(
					'woomercadopago_settings_link_' . plugin_basename( __FILE__ ),
					array( $this, 'woomercadopago_settings_link' ) );

				add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping' ) );
				add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_method_by_shipping' ) );

			} else {
				add_action( 'admin_notices', array( $this, 'notify_woocommerce_miss' ) );
			}

			if ( is_admin() ) {
				$this->admin_includes();
			}

		}

		/**
		 * Admin includes.
		 */
		private function admin_includes() {
			include_once dirname( __FILE__ ) . '/admin/class-wc-mercadoenvios-admin-orders.php';
		}

		// As well as defining your class, you need to also tell WooCommerce (WC) that
		// it exists. Do this by filtering woocommerce_payment_gateways.
		public function add_gateway( $methods ) {
			$methods[] = 'WC_WooMercadoPago_Gateway';
			$methods[] = 'WC_WooMercadoPagoCustom_Gateway';
			$methods[] = 'WC_WooMercadoPagoTicket_Gateway';
			$methods[] = 'WC_WooMercadoPagoSubscription_Gateway';
			return $methods;
		}

		// woocommerce_shipping_methods
		public function add_shipping( $methods ) {
			$methods['mercadoenvios-normal'] = 'WC_MercadoEnvios_Shipping_Normal';
			$methods['mercadoenvios-express'] = 'WC_MercadoEnvios_Shipping_Express';
			return $methods;
		}

		// When selected Mercado Envios the payment can be made only with Mercado Pago Basic (Standard)
		public function filter_payment_method_by_shipping( $methods ) {

			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];

			// Check shipping methods is a Mercado Envios.
			if ( strpos( $chosen_shipping, 'mercadoenvios-normal' ) !== false || strpos( $chosen_shipping, 'mercadoenvios-express' ) !== false ) {
				$new_array = array();
				foreach ( $methods as $payment_method => $payment_method_object ) {
					if ( $payment_method == 'woocommerce-mercadopago-module' ) {
						$new_array['woocommerce-mercadopago-module'] = $payment_method_object;
					}
				}
				// Return new array shipping methods (only Mercado Pago Basic).
				return $new_array;
			}
			// Return all shipping methods.
			return $methods;
		}

		/**
		 * Summary: Places a warning error to notify user that WooCommerce is missing.
		 * Description: Places a warning error to notify user that WooCommerce is missing.
		 */
		public function notify_woocommerce_miss() {
			echo
				'<div class="error"><p>' .
				sprintf(
					__( 'Woo Mercado Pago Module depends on the last version of %s to execute!', 'woocommerce-mercadopago-module' ),
					'<a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
				) .
				'</p></div>';
		}

		// Multi-language plugin.
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-mercadopago-module' );
			$module_root = 'woocommerce-mercadopago-module/woocommerce-mercadopago-module-';
			load_textdomain(
				'woocommerce-mercadopago-module',
				trailingslashit( WP_LANG_DIR ) . $module_root . $locale . '.mo'
			);
			load_plugin_textdomain(
				'woocommerce-mercadopago-module',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages/'
			);
		}

		/**
		 * Summary: Get store categories from Mercado Pago.
		 * Description: Trigger API to get available categories and proper description.
		 * @return an array with found categories and a description for its selector title.
		 */
		public static function get_categories() {

			$store_categories_id = array();
			$store_categories_description = array();

			// Get Mercado Pago store categories.
			$categories = MPRestClient::get(
				array( 'uri' => '/item_categories' ),
				WC_WooMercadoPago_Module::get_module_version()
			);
			foreach ( $categories['response'] as $category ) {
				array_push(
					$store_categories_id, str_replace( '_', ' ', $category['id'] )
				);
				array_push(
					$store_categories_description, str_replace( '_', ' ', $category['description'] )
				);
			}

			return array(
				'store_categories_id' => $store_categories_id,
				'store_categories_description' => $store_categories_description
			);

		}

		/**
		 * Summary: Get the rate of conversion between two currencies.
		 * Description: The currencies are the one used in WooCommerce and the one used in $site_id.
		 * @return a float that is the rate of conversion.
		 */
		public static function get_conversion_rate( $used_currency ) {
			$currency_obj = MPRestClient::get(
				array( 'uri' => '/currency_conversions/search?' .
					'from=' . get_woocommerce_currency() .
					'&to=' . $used_currency
				),
				WC_WooMercadoPago_Module::get_module_version()
			);
			if ( isset( $currency_obj['response'] ) ) {
				$currency_obj = $currency_obj['response'];
				if ( isset( $currency_obj['ratio'] ) ) {
					return ( (float) $currency_obj['ratio'] );
				}
			}
			return -1;
		}

		// Get WooCommerce instance
		public static function woocommerce_instance() {
			if ( function_exists( 'WC' ) ) {
				return WC();
			} else {
				global $woocommerce;
				return $woocommerce;
			}
		}

		/**
		 * Summary: Find template's folder.
		 * Description: Find template's folder.
		 * @return a string that identifies the path.
		 */
		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		/**
		 * Summary: Get module's version.
		 * Description: Get module's version.
		 * @return a string with the given version.
		 */
		public static function get_module_version() {
			return WC_WooMercadoPago_Module::VERSION;
		}

		/**
		 * Summary: Get client id from access token.
		 * Description: Get client id from access token.
		 * @return the client id.
		 */
		public static function get_client_id( $at ) {
			$t = explode ( '-', $at );
			if ( count( $t ) > 0 ) {
				return $t[1];
			}
			return '';
		}

		/**
		 * Summary: Builds up the array for the mp_install table, with info related with checkout.
		 * Description: Builds up the array for the mp_install table, with info related with checkout.
		 * @return an array with the module informations.
		 */
		public static function get_common_settings() {

			$w = WC_WooMercadoPago_Module::woocommerce_instance();

			$infra_data = array(
				'module_version' => WC_WooMercadoPago_Module::VERSION,
				'platform' => 'WooCommerce',
				'platform_version' => $w->version,
				'code_version' => phpversion(),
				'so_server' => PHP_OS
			);

			return $infra_data;

		}

		/**
		 * Summary: Get preference data for a specific country.
		 * Description: Get preference data for a specific country.
		 * @return an array with sponsor id, country name, banner image for checkout, and currency.
		 */
		public static function get_country_config( $site_id ) {
			switch ( $site_id ) {
				case 'MLA':
					return array(
						'sponsor_id' => 208682286,
						'country_name' => __( 'Argentine', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLA/standard_mla.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLA/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'ARS'
					);
				case 'MLB':
					return array(
						'sponsor_id' => 208686191,
						'country_name' => __( 'Brazil', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLB/standard_mlb.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLB/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'BRL'
					);
				case 'MCO':
					return array(
						'sponsor_id' => 208687643,
						'country_name' => __( 'Colombia', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MCO/standard_mco.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MCO/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'COP'
					);
				case 'MLC':
					return array(
						'sponsor_id' => 208690789,
						'country_name' => __( 'Chile', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLC/standard_mlc.gif',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLC/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'CLP'
					);
				case 'MLM':
					return array(
						'sponsor_id' => 208692380,
						'country_name' => __( 'Mexico', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLM/standard_mlm.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLM/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'MXN'
					);
				case 'MLV':
					return array(
						'sponsor_id' => 208692735,
						'country_name' => __( 'Venezuela', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLV/standard_mlv.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLV/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'VEF'
					);
				case 'MPE':
					return array(
						'sponsor_id' => 216998692,
						'country_name' => __( 'Peru', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MPE/standard_mpe.png',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MPE/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'PEN'
					);
				case 'MLU':
					return array(
						'sponsor_id' => 243692679,
						'country_name' => __( 'Uruguay', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLU/standard_mlu.png',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLU/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'UYU'
					);
				default: // set Argentina as default country
					return array(
						'sponsor_id' => 208682286,
						'country_name' => __( 'Argentine', 'woocommerce-mercadopago-module' ),
						'checkout_banner' => plugins_url(
							'woo-mercado-pago-module/images/MLA/standard_mla.jpg',
							plugin_dir_path( __FILE__ )
						),
						'checkout_banner_custom' => plugins_url(
							'woo-mercado-pago-module/images/MLA/credit_card.png',
							plugin_dir_path( __FILE__ )
						),
						'currency' => 'ARS'
					);
			}
		}

		public static function build_currency_conversion_err_msg( $currency ) {
			return '<img width="12" height="12" src="' .
				plugins_url( 'woo-mercado-pago-module/images/error.png', plugin_dir_path( __FILE__ ) ) .
				'"> ' .
				__( 'ERROR: It was not possible to convert the unsupported currency', 'woocommerce-mercadopago-module' ) .
				' ' . get_woocommerce_currency() . ' '	.
				__( 'to', 'woocommerce-mercadopago-module' ) . ' ' . $currency . '. ' .
				__( 'Currency conversions should be made outside this module.', 'woocommerce-mercadopago-module' );
		}

		public static function build_currency_not_converted_msg( $currency, $country_name ) {
			return '<img width="12" height="12" src="' .
				plugins_url( 'woo-mercado-pago-module/images/warning.png', plugin_dir_path( __FILE__ ) ) .
				'"> ' .
				__( 'ATTENTION: The currency', 'woocommerce-mercadopago-module' ) .
				' ' . get_woocommerce_currency() . ' ' .
				__( 'defined in WooCommerce is different from the one used in your credentials country.<br>The currency for transactions in this payment method will be', 'woocommerce-mercadopago-module' ) .
				' ' . $currency . ' (' . $country_name . '). ' .
				__( 'Currency conversions should be made outside this module.', 'woocommerce-mercadopago-module' );
		}

		public static function build_currency_converted_msg( $currency, $currency_ratio ) {
			return '<img width="12" height="12" src="' .
				plugins_url( 'woo-mercado-pago-module/images/check.png', plugin_dir_path( __FILE__ ) ) .
				'"> ' .
				__( 'CURRENCY CONVERTED: The currency conversion ratio from', 'woocommerce-mercadopago-module' )  .
				' ' . get_woocommerce_currency() . ' ' .
				__( 'to', 'woocommerce-mercadopago-module' ) . ' ' . $currency .
				__( ' is: ', 'woocommerce-mercadopago-module' ) . $currency_ratio . ".";
		}

		public static function build_valid_credentials_msg( $country_name, $site_id ) {
			return '<img width="12" height="12" src="' .
				plugins_url( 'woo-mercado-pago-module/images/check.png', plugin_dir_path( __FILE__ ) ) .
				'"> ' .
				__( 'Your credentials are <strong>valid</strong> for', 'woocommerce-mercadopago-module' ) .
				': ' . $country_name . ' <img width="18.6" height="12" src="' . plugins_url(
					'woo-mercado-pago-module/images/' . $site_id . '/' . $site_id . '.png',
					plugin_dir_path( __FILE__ )
				) . '"> ';
		}

		// Check if an order is recurrent.
		public static function is_subscription( $items ) {
			$is_subscription = false;
			if ( sizeof( $items ) == 1 ) {
				foreach ( $items as $cart_item_key => $cart_item ) {
					$is_recurrent = get_post_meta( $cart_item['product_id'], '_mp_recurring_is_recurrent', true );
					if ( $is_recurrent == 'yes' ) {
						$is_subscription = true;
					}
				}
			}
			return $is_subscription;
		}

		public static function build_invalid_credentials_msg() {
			return '<img width="12" height="12" src="' .
				plugins_url( 'woo-mercado-pago-module/images/error.png', plugin_dir_path( __FILE__ ) ) .
				'"> ' .
				__( 'Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module' );
		}

		// Fix to URL Problem : #038; replaces & and breaks the navigation.
		public static function workaround_ampersand_bug( $link ) {
			return str_replace( '\/', '/', str_replace( '&#038;', '&', $link) );
		}

		// Converts HTML entities to readable UTF8
		public static function utf8_ansi( $str = '' ) {
			$str = str_replace( '\u', 'u', $str );
			$str = preg_replace( '/u([\da-fA-F]{4})/', '&#x\1;', $str );
			return html_entity_decode( $str );
		}

	}

	// ==========================================================================================

	// add our own item to the order actions meta box
	add_action( 'woocommerce_order_actions', 'add_mp_order_meta_box_actions' );
	// define the item in the meta box by adding an item to the $actions array
	function add_mp_order_meta_box_actions( $actions ) {
		$actions['cancel_order'] = __( 'Cancel Order', 'woocommerce-mercadopago-module' );
		return $actions;
	}

	// Payment gateways should be created as additional plugins that hook into WooCommerce.
	// Inside the plugin, you need to create a class after plugins are loaded.
	add_action(
		'plugins_loaded',
		array( 'WC_WooMercadoPago_Module', 'init_mercado_pago_gateway_class' ), 0
	);

	// Add settings link on plugin page
	function woomercadopago_settings_link( $links ) {
		$plugin_links = array();
		$plugin_links[] = '<a href="' . esc_url( admin_url(
			'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPago_Gateway' ) ) .
			'">' . __( 'Basic Checkout', 'woocommerce-mercadopago-module' ) . '</a>';
		$plugin_links[] = '<a href="' . esc_url( admin_url(
			'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoCustom_Gateway' ) ) .
			'">' . __( 'Custom Checkout', 'woocommerce-mercadopago-module' ) . '</a>';
		$plugin_links[] = '<a href="' . esc_url( admin_url(
			'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoTicket_Gateway' ) ) .
			'">' . __( 'Ticket', 'woocommerce-mercadopago-module' ) . '</a>';
		$plugin_links[] = '<a href="' . esc_url( admin_url(
			'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoSubscription_Gateway' ) ) .
			'">' . __( 'Subscription', 'woocommerce-mercadopago-module' ) . '</a>';
		$plugin_links[] = '<br><a target="_blank" href="' .
			'https://github.com/mercadopago/cart-woocommerce#installation' .
			'">' . __( 'Tutorial', 'woocommerce-mercadopago-module' ) . '</a>';
		$plugin_links[] = '<a target="_blank" href="' .
			'https://wordpress.org/support/view/plugin-reviews/woo-mercado-pago-module?filter=5#postform' .
			'">' . sprintf(
				__( 'Rate Us', 'woocommerce-mercadopago-module' ) . ' %s',
				'&#9733;&#9733;&#9733;&#9733;&#9733;'
			) . '</a>';
		$plugin_links[] = '<a target="_blank" href="' .
			'https://wordpress.org/support/plugin/woo-mercado-pago-module#postform' .
			'">' . __( 'Report Issue', 'woocommerce-mercadopago-module' ) . '</a>';
		return array_merge($plugin_links, $links);
	}
	$plugin = plugin_basename( __FILE__ );
	add_filter("plugin_action_links_$plugin", 'woomercadopago_settings_link' );

endif;
