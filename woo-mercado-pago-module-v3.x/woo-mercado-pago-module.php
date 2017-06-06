<?php
/**
 * Plugin Name: WooCommerce Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: The <strong>oficial</strong> plugin of Mercado Pago for WooCommerce. Enables WooCommerce to use Mercado Pago as payment gateway.
 * Version: 3.0.0
 * Author: Mercado Pago
 * Author URI: https://www.mercadopago.com.br/developers/
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * Text Domain: woo-mercado-pago-module
 * Domain Path: /i18n/languages/
 *
 * @package MercadoPago
 * @category Core
 * @author Mercado Pago
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Mercado Pago SDK
require_once dirname( __FILE__ ) . '/includes/sdk/lib/mercadopago.php';

// Load module class if it wasn't loaded yet.
if ( ! class_exists( 'WC_Woo_Mercado_Pago_Module' ) ) :

	/**
	 * Summary: WooCommerce MercadoPago Module main class.
	 * Description: Used as a kind of manager to enable/disable each Mercado Pago gateway.
	 * Available Public Static Functions:
	 * - validate_credentials_v0()
	 * - validate_credentials_v1()
	 * - woocommerce_instance()
	 * - get_conversion_rate( $used_currency )
	 * - get_common_settings()
	 * - get_categories()
	 * - get_site_data( $is_v1 = false )
	 * - workaround_ampersand_bug( $link )
	 * - get_module_version()
	 * - is_supported_currency( $site_id )
	 * - build_currency_conversion_err_msg( $currency )
	 * - build_currency_not_converted_msg( $currency, $country_name )
	 * - build_currency_converted_msg( $currency )
	 * - get_country_name( $site_id )
	 * - build_log_path_string( $gateway_id, $gateway_name )
	 * - get_map( $selector_id )

	 * @since 1.0.0
	 */
	class WC_Woo_Mercado_Pago_Module {

		// ============================================================

		// General constants.
		const VERSION = '3.0.0';
		const MIN_PHP = 5.6;

		// Arrays to hold configurations for LatAm environment.
		// As this array contains runtime data, we can't set it as a class constant.
		public static $categories = array();
		public static $country_configs = array();

		// ============================================================

		// A singleton design pattern to access this class in global scope.
		protected static $instance = null;
		public static function init_mercado_pago_class() {
			if ( self::$instance === null ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		// Class constructor.
		private function __construct() {

			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			WC_Woo_Mercado_Pago_Module::$categories = WC_Woo_Mercado_Pago_Module::get_categories();
			WC_Woo_Mercado_Pago_Module::$country_configs = array(
				'MCO' => array(
					'site_id'                => 'MCO',
					'sponsor_id'             => 208687643,
					'checkout_banner'        => plugins_url( 'assets/images/MCO/standard_mco.jpg', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MCO/credit_card.png', __FILE__ ),
					'currency'               => 'COP'
				),
				'MLA' => array(
					'site_id'                => 'MLA',
					'sponsor_id'             => 208682286,
					'checkout_banner'        => plugins_url( 'assets/images/MLA/standard_mla.jpg', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLA/credit_card.png', __FILE__ ),
					'currency'               => 'ARS'
				),
				'MLB' => array(
					'site_id'                => 'MLB',
					'sponsor_id'             => 208686191,
					'checkout_banner'        => plugins_url( 'assets/images/MLB/standard_mlb.jpg', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLB/credit_card.png', __FILE__ ),
					'currency'               => 'BRL'
				),
				'MLC' => array(
					'site_id'                => 'MLC',
					'sponsor_id'             => 208690789,
					'checkout_banner'        => plugins_url( 'assets/images/MLC/standard_mlc.gif', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLC/credit_card.png', __FILE__ ),
					'currency'               => 'CLP'
				),
				'MLM' => array(
					'site_id'                => 'MLM',
					'sponsor_id'             => 208692380,
					'checkout_banner'        => plugins_url( 'assets/images/MLM/standard_mlm.jpg', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLM/credit_card.png', __FILE__ ),
					'currency'               => 'MXN'
				),
				'MLU' => array(
					'site_id'                => 'MLU',
					'sponsor_id'             => 243692679,
					'checkout_banner'        => plugins_url( 'assets/images/MLU/standard_mlu.png', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLU/credit_card.png', __FILE__ ),
					'currency'               => 'UYU'
				),
				'MLV' => array(
					'site_id'                => 'MLV',
					'sponsor_id'             => 208692735,
					'checkout_banner'        => plugins_url( 'assets/images/MLV/standard_mlv.jpg', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MLV/credit_card.png', __FILE__ ),
					'currency'               => 'VEF'
				),
				'MPE' => array(
					'site_id'                => 'MPE',
					'sponsor_id'             => 216998692,
					'checkout_banner'        => plugins_url( 'assets/images/MPE/standard_mpe.png', __FILE__ ),
					'checkout_banner_custom' => plugins_url( 'assets/images/MPE/credit_card.png', __FILE__ ),
					'currency'               => 'PEN'
				)
			);

			// First of all, verify if WooCommerce is already installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				
				// Adds each Mercado Pago gateway as available payment method.
				include_once dirname( __FILE__ ) . '/includes/WC_WooMercadoPago_BasicGateway.php';
				include_once dirname( __FILE__ ) . '/includes/WC_WooMercadoPago_CustomGateway.php';
				include_once dirname( __FILE__ ) . '/includes/WC_WooMercadoPago_TicketGateway.php';
				include_once dirname( __FILE__ ) . '/includes/WC_WooMercadoPago_SubscriptionGateway.php';
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				
				// This adds custom links in the plugin page.
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'woomercadopago_settings_link' ) );

			}

		}

		/**
		 * Summary: As well as defining your class, you need to also tell WooCommerce (WC) that
		 * it exists. Do this by filtering woocommerce_payment_gateways.
		 * Description: As well as defining your class, you need to also tell WooCommerce (WC) that
		 * it exists. Do this by filtering woocommerce_payment_gateways.
		 * @return an array containing the payment methods.
		 */
		public function add_gateway( $methods ) {
			$methods[] = 'WC_WooMercadoPago_BasicGateway';
			$methods[] = 'WC_WooMercadoPago_CustomGateway';
			$methods[] = 'WC_WooMercadoPago_TicketGateway';
			$methods[] = 'WC_WooMercadoPago_SubscriptionGateway';
			return $methods;
		}

		// Multi-language setup.
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'woo-mercado-pago-module', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );
		}

		// Add settings link on plugin page.
		public function woomercadopago_settings_link( $links ) {
			$plugin_links = array();
			$plugin_links[] = '<a href="' . esc_url( admin_url(
				'admin.php?page=mercado-pago-settings' ) ) .
				'">' . __( 'Mercado Pago Settings', 'woo-mercado-pago-module' ) . '</a>';
			$plugin_links[] = '<a target="_blank" href="' .
				'https://wordpress.org/support/view/plugin-reviews/woo-mercado-pago-module?filter=5#postform' .
				'">' . sprintf(
					__( 'Rate Us', 'woo-mercado-pago-module' ) . ' %s',
					'&#9733;&#9733;&#9733;&#9733;&#9733;'
				) . '</a>';
			$plugin_links[] = '<br><a target="_blank" href="' .
				'https://github.com/mercadopago/cart-woocommerce#installation' .
				'">' . __( 'Tutorial', 'woo-mercado-pago-module' ) . '</a>';
			$plugin_links[] = '<a target="_blank" href="' .
				'https://wordpress.org/support/plugin/woo-mercado-pago-module#postform' .
				'">' . __( 'Report Issue', 'woo-mercado-pago-module' ) . '</a>';
			return array_merge( $plugin_links, $links );
		}

		// ============================================================

		/**
		 * Summary: Check if we have valid credentials for v0.
		 * Description: Check if we have valid credentials.
		 * @return boolean true/false depending on the validation result.
		 */
		public static function validate_credentials_v0() {
			$client_id = get_option( '_mp_client_id', '' );
			$client_secret = get_option( '_mp_client_secret', '' );
			try {
				$mp_v0 = new MP( WC_Woo_Mercado_Pago_Module::VERSION, $client_id, $client_secret );
				$access_token = $mp_v0->get_access_token();
				$get_request = $mp_v0->get( '/users/me?access_token=' . $access_token );
				if ( isset( $get_request['response']['site_id'] ) && ! empty( $access_token ) ) {
					update_option( '_access_token_v0', $access_token, true );
					update_option( '_test_user_v0', in_array( 'test_user', $get_request['response']['tags'], true ) );
					update_option( '_site_id_v0', $get_request['response']['site_id'], true );
					update_option( '_collector_id_v0', $get_request['response']['id'], true );
					// Get available payment methods.
					$payment_methods = $mp_v0->get( '/v1/payment_methods/?access_token=' . $access_token );
					$arr = array();
					$arr[] = 'n/d';
					foreach ( $payment_methods['response'] as $payment ) {
						$arr[] = $payment['id'];
					}
					update_option( '_all_payment_methods_v0', implode( ',', $arr ), true );
					// Check for auto converstion of currency.
					$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate(
						WC_Woo_Mercado_Pago_Module::$country_configs[$get_request['response']['site_id']]['currency']
					);
					if ( $currency_ratio > 0 ) {
						update_option( '_can_do_currency_conversion_v0', true, true );
					} else {
						update_option( '_can_do_currency_conversion_v0', false, true );
					}
					return true;
				}
			} catch ( MercadoPagoException $e ) {
				// TODO: should we handle an exception here?
			}
			update_option( '_access_token_v0', '', true, true );
			update_option( '_test_user_v0', '', true );
			update_option( '_site_id_v0', '', true );
			update_option( '_collector_id_v0', '', true );
			update_option( '_all_payment_methods_v0', array(), true );
			update_option( '_can_do_currency_conversion_v0', false, true );
			return false;
		}

		/**
		 * Summary: Check if we have valid credentials for v1.
		 * Description: Check if we have valid credentials.
		 * @return boolean true/false depending on the validation result.
		 */
		public static function validate_credentials_v1() {
			$public_key = get_option( '_mp_public_key', '' );
			$access_token = get_option( '_mp_access_token', '' );
			try {
				$mp_v1 = new MP( WC_Woo_Mercado_Pago_Module::VERSION, $access_token );
				$access_token = $mp_v1->get_access_token();
				$get_request = $mp_v1->get( '/users/me?access_token=' . $access_token );
				if ( isset( $get_request['response']['site_id'] ) && ! empty( $public_key ) ) {
					update_option( '_access_token_v1', $access_token, true );
					update_option( '_test_user_v1', in_array( 'test_user', $get_request['response']['tags'] ), true );
					update_option( '_site_id_v1', $get_request['response']['site_id'], true );
					update_option( '_collector_id_v1', $get_request['response']['id'], true );
					// Get available payment methods.
					$payments = $mp_v1->get( '/v1/payment_methods/?access_token=' . $access_token );
					$payment_methods_ticket = array();
					foreach ( $payments['response'] as $payment ) {
						if ( isset( $payment['payment_type_id'] ) ) {
							if ( $payment['payment_type_id'] != 'account_money' &&
								$payment['payment_type_id'] != 'credit_card' &&
								$payment['payment_type_id'] != 'debit_card' &&
								$payment['payment_type_id'] != 'prepaid_card' ) {
								array_push( $payment_methods_ticket, $payment );
							}
						}
					}
					// TODO: resolve how to save payment methods for tickets
					/*update_option( '_all_payment_methods_v0', implode( ',', $arr ), true );*/
					// Check for auto converstion of currency.
					$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate(
						WC_Woo_Mercado_Pago_Module::$country_configs[$get_request['response']['site_id']]['currency']
					);
					if ( $currency_ratio > 0 ) {
						update_option( '_can_do_currency_conversion_v1', true, true );
					} else {
						update_option( '_can_do_currency_conversion_v1', false, true );
					}
					return true;
				}
			} catch ( MercadoPagoException $e ) {
				// TODO: should we handle an exception here?
			}
			update_option( '_access_token_v1', '', true );
			update_option( '_test_user_v1', 'abc', true );
			update_option( '_site_id_v1', '', true );
			update_option( '_collector_id_v1', '', true );
			update_option( '_all_payment_methods_v1', array(), true );
			update_option( '_can_do_currency_conversion_v1', false, true );
			return false;
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
				WC_Woo_Mercado_Pago_Module::get_module_version()
			);
			if ( isset( $currency_obj['response'] ) ) {
				$currency_obj = $currency_obj['response'];
				if ( isset( $currency_obj['ratio'] ) ) {
					return ( (float) $currency_obj['ratio'] );
				}
			}
			return -1;
		}

		/**
		 * Summary: Builds up the array for the mp_install table, with info related with checkout.
		 * Description: Builds up the array for the mp_install table, with info related with checkout.
		 * @return an array with the module informations.
		 */
		public static function get_common_settings() {
			$w = WC_Woo_Mercado_Pago_Module::woocommerce_instance();
			$infra_data = array(
				'module_version' => WC_Woo_Mercado_Pago_Module::VERSION,
				'platform' => 'WooCommerce',
				'platform_version' => $w->version,
				'code_version' => phpversion(),
				'so_server' => PHP_OS
			);
			return $infra_data;
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
				WC_Woo_Mercado_Pago_Module::get_module_version()
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
		 * Summary: Get information about the used Mercado Pago account based in its site.
		 * Description: Get information about the used Mercado Pago account based in its site.
		 * @return an array with the information.
		 */
		public static function get_site_data( $is_v1 = false ) {
			if ( ! $is_v1 ) {
				$site_id = get_option( '_site_id_v0', '' );
			} else {
				$site_id = get_option( '_site_id_v1', '' );
			}
			if ( isset( $site_id ) ) {
				return WC_Woo_Mercado_Pago_Module::$country_configs[$site_id];
			} else {
				return null;
			}
		}

		// Fix to URL Problem : #038; replaces & and breaks the navigation.
		public static function workaround_ampersand_bug( $link ) {
			return str_replace( '\/', '/', str_replace( '&#038;', '&', $link) );
		}

		/**
		 * Summary: Get module's version.
		 * Description: Get module's version.
		 * @return a string with the given version.
		 */
		public static function get_module_version() {
			return WC_Woo_Mercado_Pago_Module::VERSION;
		}

		// Return boolean indicating if currency is supported.
		public static function is_supported_currency( $site_id ) {
			return get_woocommerce_currency() == WC_Woo_Mercado_Pago_Module::$country_configs[$site_id]['currency'];
		}

		public static function build_currency_conversion_err_msg( $currency ) {
			return '<img width="14" height="14" src="' .
				plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
				__( 'ERROR: It was not possible to convert the unsupported currency', 'woo-mercado-pago-module' ) .
				' ' . get_woocommerce_currency() . ' '	.
				__( 'to', 'woo-mercado-pago-module' ) . ' ' . $currency . '. ' .
				__( 'Currency conversions should be made outside this module.', 'woo-mercado-pago-module' );
		}

		public static function build_currency_not_converted_msg( $currency, $country_name ) {
			return '<img width="14" height="14" src="' .
				plugins_url( 'assets/images/warning.png', __FILE__ ) . '"> ' .
				__( 'ATTENTION: The currency', 'woo-mercado-pago-module' ) .
				' ' . get_woocommerce_currency() . ' ' .
				__( 'defined in WooCommerce is different from the one used in your credentials country.<br>The currency for transactions in this payment method will be', 'woo-mercado-pago-module' ) .
				' ' . $currency . ' (' . $country_name . '). ' .
				__( 'Currency conversions should be made outside this module.', 'woo-mercado-pago-module' );
		}

		public static function build_currency_converted_msg( $currency ) {
			return '<img width="14" height="14" src="' .
				plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
				__( 'CURRENCY CONVERTED: Your store is converting currency from', 'woo-mercado-pago-module' )  .
				' ' . get_woocommerce_currency() . ' ' .
				__( 'to', 'woo-mercado-pago-module' ) . ' ' . $currency;
		}

		public static function get_country_name( $site_id ) {
			switch ( $site_id ) {
				case 'MCO':
					return __( 'Colombia', 'woo-mercado-pago-module' );
				case 'MLA':
					return __( 'Argentine', 'woo-mercado-pago-module' );
				case 'MLB':
					return __( 'Brazil', 'woo-mercado-pago-module' );
				case 'MLC':
					return __( 'Chile', 'woo-mercado-pago-module' );
				case 'MLM':
					return __( 'Mexico', 'woo-mercado-pago-module' );
				case 'MLU':
					return __( 'Uruguay', 'woo-mercado-pago-module' );
				case 'MLV':
					return __( 'Venezuela', 'woo-mercado-pago-module' );
				case 'MPE':
					return __( 'Peru', 'woo-mercado-pago-module' );
			}
			return '';
		}

		// Build the string representing the path to the log file.
		public static function build_log_path_string( $gateway_id, $gateway_name ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' .
				esc_attr( $gateway_id ) . '-' . sanitize_file_name( wp_hash( $gateway_id ) ) . '.log' ) ) . '">' .
				$gateway_name . '</a>';
		}

		public static function get_wc_status_for_mp_status( $mp_status ) {
			$defaults = array(
				'pending' => 'pending',
				'approved' => 'processing',
				'inprocess' => 'on_hold',
				'inmediation' => 'on_hold',
				'rejected' => 'failed',
				'cancelled' => 'cancelled',
				'refunded' => 'refunded',
				'chargedback' => 'refunded'
			);
			$status = get_option( '_mp_order_status_' . $mp_status . '_map', $defaults[$mp_status] );
			return str_replace( '_', '-', $status );
		}

		public static function get_map( $selector_id ) {
			$arr = explode( '_', $selector_id );
			$defaults = array(
				'pending' => 'pending',
				'approved' => 'processing',
				'inprocess' => 'on_hold',
				'inmediation' => 'on_hold',
				'rejected' => 'failed',
				'cancelled' => 'cancelled',
				'refunded' => 'refunded',
				'chargedback' => 'refunded'
			);
			$selection = get_option( '_mp_' . $selector_id, $defaults[$arr[2]] );
			return
				'<option value="pending"' . ( $selection == 'pending' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'PENDING
				</option>
				<option value="processing"' . ( $selection == 'processing' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'PROCESSING
				</option>
				<option value="on_hold"' . ( $selection == 'on_hold' ? 'selected="selected"' : '' ) . '>' . 
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'ON-HOLD
				</option>
				<option value="completed"' . ( $selection == 'completed' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'COMPLETED
				</option>
				<option value="cancelled"' . ( $selection == 'cancelled' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'CANCELLED
				</option>
				<option value="refunded"' . ( $selection == 'refunded' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'REFUNDED
				</option>
				<option value="failed"' . ( $selection == 'failed' ? 'selected="selected"' : '' ) . '>' .
					__( "Update WooCommerce order to ", "woo-mercado-pago-module" ) . 'FAILED
				</option>';
		}

	}

	//=====

	// Create Mercado Pago option menu.
	add_action( 'admin_menu', function() {
		add_options_page(
			'Mercado Pago Options', 'Mercado Pago', 'manage_options', 'mercado-pago-settings',
			function() {

				// Verify permissions.
				if ( ! current_user_can( 'manage_options' ) )  {
					wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
				}

				// Check for submits.
				if ( isset( $_POST['submit'] ) ) {
					if ( isset( $_POST['client_id'] ) ) {
						update_option( '_mp_client_id', $_POST['client_id'], true );
					} else {
						update_option( '_mp_client_id', '', true );
					}
					if ( isset( $_POST['client_secret'] ) ) {
						update_option( '_mp_client_secret', $_POST['client_secret'], true );
					} else {
						update_option( '_mp_client_secret', '', true );
					}
					if ( isset( $_POST['public_key'] ) ) {
						update_option( '_mp_public_key', $_POST['public_key'], true );
					} else {
						update_option( '_mp_public_key', '', true );
					}
					if ( isset( $_POST['access_token'] ) ) {
						update_option( '_mp_access_token', $_POST['access_token'], true );
					} else {
						update_option( '_mp_access_token', '', true );
					}
					if ( isset( $_POST['success_url'] ) ) {
						update_option( '_mp_success_url', $_POST['success_url'], true );
					} else {
						update_option( '_mp_success_url', '', true );
					}
					if ( isset( $_POST['fail_url'] ) ) {
						update_option( '_mp_fail_url', $_POST['fail_url'], true );
					} else {
						update_option( '_mp_fail_url', '', true );
					}
					if ( isset( $_POST['pending_url'] ) ) {
						update_option( '_mp_pending_url', $_POST['pending_url'], true );
					} else {
						update_option( '_mp_pending_url', '', true );
					}
					if ( isset( $_POST['order_status_pending_map'] ) ) {
						update_option( '_mp_order_status_pending_map', $_POST['order_status_pending_map'], true );
					} else {
						update_option( '_mp_order_status_pending_map', '', true );
					}
					if ( isset( $_POST['order_status_approved_map'] ) ) {
						update_option( '_mp_order_status_approved_map', $_POST['order_status_approved_map'], true );
					} else {
						update_option( '_mp_order_status_approved_map', '', true );
					}
					if ( isset( $_POST['order_status_inprocess_map'] ) ) {
						update_option( '_mp_order_status_inprocess_map', $_POST['order_status_inprocess_map'], true );
					} else {
						update_option( '_mp_order_status_inprocess_map', '', true );
					}
					if ( isset( $_POST['order_status_inmediation_map'] ) ) {
						update_option( '_mp_order_status_inmediation_map', $_POST['order_status_inmediation_map'], true );
					} else {
						update_option( '_mp_order_status_inmediation_map', '', true );
					}
					if ( isset( $_POST['order_status_rejected_map'] ) ) {
						update_option( '_mp_order_status_rejected_map', $_POST['order_status_rejected_map'], true );
					} else {
						update_option( '_mp_order_status_rejected_map', '', true );
					}
					if ( isset( $_POST['order_status_cancelled_map'] ) ) {
						update_option( '_mp_order_status_cancelled_map', $_POST['order_status_cancelled_map'], true );
					} else {
						update_option( '_mp_order_status_cancelled_map', '', true );
					}
					if ( isset( $_POST['order_status_refunded_map'] ) ) {
						update_option( '_mp_order_status_refunded_map', $_POST['order_status_refunded_map'], true );
					} else {
						update_option( '_mp_order_status_refunded_map', '', true );
					}
					if ( isset( $_POST['order_status_chargedback_map'] ) ) {
						update_option( '_mp_order_status_chargedback_map', $_POST['order_status_chargedback_map'], true );
					} else {
						update_option( '_mp_order_status_chargedback_map', '', true );
					}
					if ( isset( $_POST['statement_descriptor'] ) ) {
						update_option( '_mp_statement_descriptor', $_POST['statement_descriptor'], true );
					} else {
						update_option( '_mp_statement_descriptor', '', true );
					}
					if ( isset( $_POST['category_id'] ) ) {
						update_option( '_mp_category_id', $_POST['category_id'], true );
						$categories_data = WC_Woo_Mercado_Pago_Module::$categories;
						update_option( '_mp_category_name', $categories_data['store_categories_id'][$_POST['category_id']], true );
					} else {
						update_option( '_mp_category_id', '', true );
						update_option( '_mp_category_name', 'others', true );
					}
					if ( isset( $_POST['store_identificator'] ) ) {
						update_option( '_mp_store_identificator', $_POST['store_identificator'], true );
					} else {
						update_option( '_mp_store_identificator', '', true );
					}
					if ( isset( $_POST['currency_conversion_v0'] ) ) {
						update_option( '_mp_currency_conversion_v0', $_POST['currency_conversion_v0'], true );
					} else {
						update_option( '_mp_currency_conversion_v0', '', true );
					}
					if ( isset( $_POST['currency_conversion_v1'] ) ) {
						update_option( '_mp_currency_conversion_v1', $_POST['currency_conversion_v1'], true );
					} else {
						update_option( '_mp_currency_conversion_v1', '', true );
					}
					if ( isset( $_POST['debug_mode'] ) ) {
						update_option( '_mp_debug_mode', $_POST['debug_mode'], true );
					} else {
						update_option( '_mp_debug_mode', '', true );
					}
					if ( isset( $_POST['sandbox_mode'] ) ) {
						update_option( '_mp_sandbox_mode', $_POST['sandbox_mode'], true );
					} else {
						update_option( '_mp_sandbox_mode', '', true );
					}
				}

				// Mercado Pago logo.
				$mp_logo = '<img width="185" height="48" src="' . plugins_url( 'assets/images/mplogo.png', __FILE__ ) . '">';
				// Check WooCommerce.
				$has_woocommerce_message = class_exists( 'WC_Payment_Gateway' ) ?
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
					__( 'WooCommerce is installed and enabled.', 'woo-mercado-pago-module' ) :
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
					__( 'You don\'t have WooCommerce installed and enabled.', 'woo-mercado-pago-module' );			
				// Creating PHP version message.
				$min_php_message = phpversion() >= WC_Woo_Mercado_Pago_Module::MIN_PHP ?
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
					__( 'Your PHP version is OK.', 'woo-mercado-pago-module' ) :
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
					__( 'Your PHP version do not support this module.', 'woo-mercado-pago-module' );
				// Check cURL.
				$curl_message = in_array( 'curl', get_loaded_extensions() ) ?
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
					__( 'cURL is installed.', 'woo-mercado-pago-module' ) :
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
					__( 'cURL is not installed.', 'woo-mercado-pago-module' );
				// Check SSL.
				$is_ssl_message = empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ?
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', __FILE__ ) . '"> ' .
					__( 'SSL is missing in your site.', 'woo-mercado-pago-module' ) :
					'<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
					__( 'Your site has SSL enabled.', 'woo-mercado-pago-module' );
				// Create links for internal redirections to each payment solution.
				$gateway_buttons = '<strong>' .
					'<a class="button button-primary" href="' . esc_url( admin_url(
						'admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-basic' ) ) .
						'">' . __( 'Basic Checkout', 'woo-mercado-pago-module' ) . '</a>' . ' ' .
					'<a class="button button-primary" href="' . esc_url( admin_url(
						'admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom' ) ) .
						'">' . __( 'Custom Checkout', 'woo-mercado-pago-module' ) . '</a>' . ' ' .
					'<a class="button button-primary" href="' . esc_url( admin_url(
						'admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-ticket' ) ) .
						'">' . __( 'Ticket', 'woo-mercado-pago-module' ) . '</a>' . ' ' .
					'<a class="button button-primary" href="' . esc_url( admin_url(
						'admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-subscription' ) ) .
						'">' . __( 'Subscription', 'woo-mercado-pago-module' ) . '</a>' .
				'</strong>';
				// Statement descriptor.
				$statement_descriptor = get_option( '_mp_statement_descriptor', 'Mercado Pago' );
				// Get categories.
				$store_categories_id = WC_Woo_Mercado_Pago_Module::$categories['store_categories_id'];
				$category_id = get_option( '_mp_category_id', 0 );
				if ( count( $store_categories_id ) == 0 ) {
					$store_category_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', __FILE__ ) . '">' . ' ' .
						__( 'Configure your Client_id and Client_secret to have access to more options.', 'woo-mercado-pago-module' );
				} else {
					$store_category_message = __( 'Define which type of products your store sells.', 'woo-mercado-pago-module' );
				}
				// Store identification.
				$store_identificator = get_option( '_mp_store_identificator', 'WC-' );
				// Debug mode.
				$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
				if ( empty( $_mp_debug_mode ) ) {
					$is_debug_mode = '';
				} else {
					$is_debug_mode = 'checked="checked"';
				}
				// Sandbox mode.
				$_mp_sandbox_mode = get_option( '_mp_sandbox_mode', '' );
				if ( empty( $_mp_sandbox_mode ) ) {
					$is_sandbox_mode = '';
				} else {
					$is_sandbox_mode = 'checked="checked"';
				}

				// ===== v0 verifications =====
				// Trigger v0 API to validate credentials.
				if ( WC_Woo_Mercado_Pago_Module::validate_credentials_v0() ) {
					$site_id_v0 = get_option( '_site_id_v0', '' );
					$v0_credentials_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
						__( 'Your <strong>client_id</strong> and <strong>client_secret</strong> are <strong>valid</strong> for', 'woo-mercado-pago-module' ) . ': ' .
						'<img style="margin-top:2px;" width="18.6" height="12" src="' .
						plugins_url( 'assets/images/' . $site_id_v0 . '/' . $site_id_v0 . '.png', __FILE__ ) . '"> ' .
						WC_Woo_Mercado_Pago_Module::get_country_name( $site_id_v0 );
				} else {
					$v0_credentials_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
						__( 'Your <strong>client_id</strong> and <strong>client_secret</strong> are <strong>not valid</strong>!', 'woo-mercado-pago-module' );
				}
				$v0_credential_locales = sprintf(
					'%s <a href="https://www.mercadopago.com/mla/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlb/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlc/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mco/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlm/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mpe/account/credentials?type=basic" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlu/account/credentials?type=basic" target="_blank">%s</a> %s ' .
					'<a href="https://www.mercadopago.com/mlv/account/credentials?type=basic" target="_blank">%s</a>',
					__( 'These credentials are used in <strong>Basic Checkout</strong> and <strong>Subscriptions</strong>. Access it for your country:<br>', 'woo-mercado-pago-module' ),
					__( 'Argentine', 'woo-mercado-pago-module' ),
					__( 'Brazil', 'woo-mercado-pago-module' ),
					__( 'Chile', 'woo-mercado-pago-module' ),
					__( 'Colombia', 'woo-mercado-pago-module' ),
					__( 'Mexico', 'woo-mercado-pago-module' ),
					__( 'Peru', 'woo-mercado-pago-module' ),
					__( 'Uruguay', 'woo-mercado-pago-module' ),
					__( 'or', 'woo-mercado-pago-module' ),
					__( 'Venezuela', 'woo-mercado-pago-module' )
				);
				// Currency conversion.
				$_mp_currency_conversion_v0 = get_option( '_mp_currency_conversion_v0', '' );
				if ( empty( $_mp_currency_conversion_v0 ) ) {
					$is_currency_conversion_v0 = '';
				} else {
					$is_currency_conversion_v0 = 'checked="checked"';
				}
				$_site_id_v0 = get_option( '_site_id_v0', null );
				$_can_do_currency_conversion_v0 = get_option( '_can_do_currency_conversion_v0', false );
				if ( $_site_id_v0 != null ) {
					if ( ! WC_Woo_Mercado_Pago_Module::is_supported_currency( $site_id_v0 ) ) {
						if ( empty( $_mp_currency_conversion_v0 ) ) {
							$currency_conversion_v0_message = WC_Woo_Mercado_Pago_Module::build_currency_not_converted_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v0]['currency'],
								WC_Woo_Mercado_Pago_Module::get_country_name( $site_id_v0 )
							);
						} elseif ( ! empty( $_mp_currency_conversion_v0 ) && $_can_do_currency_conversion_v0 ) {
							$currency_conversion_v0_message = WC_Woo_Mercado_Pago_Module::build_currency_converted_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v0]['currency']
							);
						} else {
							$currency_conversion_v0_message = WC_Woo_Mercado_Pago_Module::build_currency_conversion_err_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v0]['currency']
							);
						}
					} else {
						$currency_conversion_v0_message = '';
					}
				} else {
					$currency_conversion_v0_message = '';
				}

				// ===== v1 verifications =====
				// Trigger v1 API to validate credentials.
				if ( WC_Woo_Mercado_Pago_Module::validate_credentials_v1() ) {
					$site_id_v1 = get_option( '_site_id_v1', '' );
					$v1_credentials_message = WC_Woo_Mercado_Pago_Module::validate_credentials_v1() ?
						'<img width="14" height="14" src="' . plugins_url( 'assets/images/check.png', __FILE__ ) . '"> ' .
						__( 'Your <strong>public_key</strong> and <strong>access_token</strong> are <strong>valid</strong> for', 'woo-mercado-pago-module' ) . ': ' .
						'<img style="margin-top:2px;" width="18.6" height="12" src="' .
						plugins_url( 'assets/images/' . $site_id_v1 . '/' . $site_id_v1 . '.png', __FILE__ ) . '"> ' .
						WC_Woo_Mercado_Pago_Module::get_country_name( $site_id_v1 ) :
						'<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
						__( 'Your <strong>public_key</strong> and <strong>access_token</strong> are <strong>not valid</strong>!', 'woo-mercado-pago-module' );
				} else {
					$v1_credentials_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/error.png', __FILE__ ) . '"> ' .
						__( 'Your <strong>Public_key</strong> and <strong>Access_token</strong> are <strong>not valid</strong>!', 'woo-mercado-pago-module' );
				}
				$v1_credential_locales = sprintf(
					'%s <a href="https://www.mercadopago.com/mla/account/credentials?type=custom" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlb/account/credentials?type=custom" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlc/account/credentials?type=custom" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mco/account/credentials?type=custom" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mlm/account/credentials?type=custom" target="_blank">%s</a>, ' .
					'<a href="https://www.mercadopago.com/mpe/account/credentials?type=custom" target="_blank">%s</a> %s ' .
					'<a href="https://www.mercadopago.com/mlv/account/credentials?type=custom" target="_blank">%s</a>',
					__( 'These credentials are used in <strong>Custom Checkout</strong> and <strong>Tickets</strong>. Access it for your country:<br>', 'woo-mercado-pago-module' ),
					__( 'Argentine', 'woo-mercado-pago-module' ),
					__( 'Brazil', 'woo-mercado-pago-module' ),
					__( 'Chile', 'woo-mercado-pago-module' ),
					__( 'Colombia', 'woo-mercado-pago-module' ),
					__( 'Mexico', 'woo-mercado-pago-module' ),
					__( 'Peru', 'woo-mercado-pago-module' ),
					__( 'or', 'woo-mercado-pago-module' ),
					__( 'Venezuela', 'woo-mercado-pago-module' )
				);
				// Currency conversion.
				$_mp_currency_conversion_v1 = get_option( '_mp_currency_conversion_v1', '' );
				if ( empty( $_mp_currency_conversion_v1 ) ) {
					$is_currency_conversion_v1 = '';
				} else {
					$is_currency_conversion_v1 = 'checked="checked"';
				}
				$_site_id_v1 = get_option( '_site_id_v1', null );
				$_can_do_currency_conversion_v1 = get_option( '_can_do_currency_conversion_v1', false );
				if ( $_site_id_v1 != null ) {
					if ( ! WC_Woo_Mercado_Pago_Module::is_supported_currency( $site_id_v1 ) ) {
						if ( empty( $_mp_currency_conversion_v1 ) ) {
							$currency_conversion_v1_message = WC_Woo_Mercado_Pago_Module::build_currency_not_converted_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v1]['currency'],
								WC_Woo_Mercado_Pago_Module::get_country_name( $site_id_v1 )
							);
						} elseif ( ! empty( $_mp_currency_conversion_v1 ) && $_can_do_currency_conversion_v1 ) {
							$currency_conversion_v1_message = WC_Woo_Mercado_Pago_Module::build_currency_converted_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v1]['currency']
							);
						} else {
							$currency_conversion_v1_message = WC_Woo_Mercado_Pago_Module::build_currency_conversion_err_msg(
								WC_Woo_Mercado_Pago_Module::$country_configs[$site_id_v1]['currency']
							);
						}
					} else {
						$currency_conversion_v1_message = '';
					}
				} else {
					$currency_conversion_v1_message = '';
				}

				require_once( 'templates/mp_main_settings.php' );

			}
		);
	});

	// Payment gateways should be created as additional plugins that hook into WooCommerce.
	// Inside the plugin, you need to create a class after plugins are loaded.
	add_action(
		'plugins_loaded',
		array( 'WC_Woo_Mercado_Pago_Module', 'init_mercado_pago_class' )
	);

endif;
