<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// This include Mercado Pago library SDK
require_once 'sdk/lib/mercadopago.php';

/**
 * Summary: Extending from WooCommerce Payment Gateway class.
 * Description: This class implements Mercado Pago Basic checkout.
 * @since 1.0.0
 */
class WC_WooMercadoPago_Gateway extends WC_Payment_Gateway {

	public function __construct() {

		// Mercado Pago fields.
		$this->mp = null;
		$this->site_id = null;
		$this->collector_id = null;
		$this->currency_ratio = -1;
		$this->is_test_user = false;

		// Auxiliary fields.
		$this->currency_message = '';
		$this->payment_methods = array();
		$this->country_configs = array();
		$this->store_categories_id = array();
		$this->store_categories_description = array();

		// WooCommerce fields.
		$this->id = 'woocommerce-mercadopago-module';
		$this->domain = get_site_url() . '/index.php';
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'images/mercadopago.png', plugin_dir_path( __FILE__ ) )
		);
		$this->method_title = __( 'Mercado Pago - Basic Checkout', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url(
				'images/mplogo.png',
				plugin_dir_path( __FILE__ )
			) . '"><br><br>' . '<strong>' .
			wordwrap(
				__( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ),
				80, '\n'
			) . '</strong>';

		// Fields used in Mercado Pago Module configuration page.
		$this->client_id = $this->get_option( 'client_id' );
		$this->client_secret = $this->get_option( 'client_secret' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->category_id = $this->get_option( 'category_id' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->method = $this->get_option( 'method', 'iframe' );
		$this->iframe_width = $this->get_option( 'iframe_width', 640 );
		$this->iframe_height = $this->get_option( 'iframe_height', 800 );
		$this->auto_return = $this->get_option( 'auto_return', true );
		$this->success_url = $this->get_option( 'success_url', '' );
		$this->failure_url = $this->get_option( 'failure_url', '' );
		$this->pending_url = $this->get_option( 'pending_url', '' );
		$this->currency_conversion = $this->get_option( 'currency_conversion', false );
		$this->installments = $this->get_option( 'installments', '24' );
		$this->ex_payments = $this->get_option( 'ex_payments', 'n/d' );
		$this->payment_split_mode = 'inactive';
		$this->sandbox = $this->get_option( 'sandbox', false );
		$this->debug = $this->get_option( 'debug' );

		// Logging and debug.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_MercadoPago_Module::woocommerce_instance()->logger();
			}
		}

		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();

		// Used by IPN to receive IPN incomings.
		add_action(
			'woocommerce_api_wc_woomercadopago_gateway',
			array( $this, 'check_ipn_response' )
		);
		// Used by IPN to process valid incomings.
		add_action( 'valid_mercadopago_ipn_request', array( $this, 'successful_request' ) );
		// process the cancel order meta box order action
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// process the refund order meta box order action
		add_action(
			'woocommerce_order_action_refund_order',
			array( $this, 'process_refund_order_meta_box_actions' )
		);
		// Used by WordPress to render the custom checkout page.
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		// Used to fix CSS in some older WordPress/WooCommerce versions.
		add_action( 'wp_head', array( $this, 'css' ) );
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);
		// Scripts for order configuration.
		add_action(
			'woocommerce_after_checkout_form',
			array( $this, 'add_checkout_script' )
		);
		// Checkout updates.
		add_action(
			'woocommerce_thankyou',
			array( $this, 'update_checkout_status' )
		);

		// Verify if client_id or client_secret is empty.
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			if ( ! empty( $this->settings['enabled'] ) && 'yes' == $this->settings['enabled'] ) {
				add_action( 'admin_notices', array( $this, 'client_id_or_secret_missing_message' ) );
			}
		}

	}

	/**
	 * Summary: Initialise Gateway Settings Form Fields.
	 * Description: Initialise Gateway settings form fields with a customized page.
	 */
	public function init_form_fields() {

		// If module is disabled, we do not need to load and process the settings page.
		if ( empty( $this->settings['enabled'] ) || 'no' == $this->settings['enabled'] ) {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Basic Checkout', 'woocommerce-mercadopago-module' ),
					'default' => 'no'
				)
			);
			return;
		}

		$api_secret_locale = sprintf(
			'<a href="https://www.mercadopago.com/mla/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlb/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlc/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mco/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlm/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mpe/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlv/account/credentials?type=basic" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com/mlu/account/credentials?type=basic" target="_blank">%s</a>',
			__( 'Argentine', 'woocommerce-mercadopago-module' ),
			__( 'Brazil', 'woocommerce-mercadopago-module' ),
			__( 'Chile', 'woocommerce-mercadopago-module' ),
			__( 'Colombia', 'woocommerce-mercadopago-module' ),
			__( 'Mexico', 'woocommerce-mercadopago-module' ),
			__( 'Peru', 'woocommerce-mercadopago-module' ),
			__( 'Venezuela', 'woocommerce-mercadopago-module' ),
			__( 'or', 'woocommerce-mercadopago-module' ),
			__( 'Uruguay', 'woocommerce-mercadopago-module' )
		);

		// Trigger API to get payment methods and site_id, also validates Client_id/Client_secret.
		if ( $this->validate_credentials() ) {
			// Checking the currency.
			$this->currency_message = '';
			if ( ! $this->is_supported_currency() && 'yes' == $this->settings['enabled'] ) {
				if ( $this->currency_conversion == 'no' ) {
					$this->currency_ratio = -1;
					$this->currency_message .= WC_WooMercadoPago_Module::build_currency_not_converted_msg(
						$this->country_configs['currency'],
						$this->country_configs['country_name']
					);
				} elseif ( $this->currency_conversion == 'yes' && $this->currency_ratio != -1) {
					$this->currency_message .= WC_WooMercadoPago_Module::build_currency_converted_msg(
						$this->country_configs['currency'],
						$this->currency_ratio
					);
				} else {
					$this->currency_ratio = -1;
					$this->currency_message .= WC_WooMercadoPago_Module::build_currency_conversion_err_msg(
						$this->country_configs['currency']
					);
				}
			} else {
				$this->currency_ratio = -1;
			}
			$this->credentials_message = WC_WooMercadoPago_Module::build_valid_credentials_msg(
				$this->country_configs['country_name'],
				$this->site_id
			);
			$this->payment_desc =
				__( 'Select the payment methods that you <strong>don\'t</strong> want to receive with Mercado Pago.', 'woocommerce-mercadopago-module' );
		} else {
			array_push( $this->payment_methods, 'n/d' );
			$this->credentials_message = WC_WooMercadoPago_Module::build_invalid_credentials_msg();
			$this->payment_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'Configure your Client_id and Client_secret to have access to more options.', 'woocommerce-mercadopago-module' );
		}

		// fill categories (can be handled without credentials).
		$categories = WC_WooMercadoPago_Module::get_categories();
		$this->store_categories_id = $categories['store_categories_id'];
		$this->store_categories_description = $categories['store_categories_description'];

		// Checks validity of iFrame width/height fields.
		if ( ! is_numeric( $this->iframe_width) ) {
			$this->iframe_width_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
		} else {
			$this->iframe_width_desc =
				__( 'If your integration method is iFrame, please inform the payment iFrame width.', 'woocommerce-mercadopago-module' );
		}
		if ( ! is_numeric( $this->iframe_height) ) {
			$this->iframe_height_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
		} else {
			$this->iframe_height_desc =
				__( 'If your integration method is iFrame, please inform the payment iFrame height.', 'woocommerce-mercadopago-module' );
		}

		// Checks if max installments is a number.
		if ( ! is_numeric( $this->installments ) ) {
			$this->installments_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
		} else {
			$this->installments_desc =
				__( 'Select the max number of installments for your customers.', 'woocommerce-mercadopago-module' );
		}

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Basic Checkout', 'woocommerce-mercadopago-module' ),
				'default' => 'no'
			),
			'credentials_title' => array(
				'title' => __( 'Mercado Pago Credentials', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => sprintf( '%s', $this->credentials_message ) . '<br>' . sprintf(
					__( 'You can obtain your credentials for', 'woocommerce-mercadopago-module' ) .
					' %s.', $api_secret_locale
				)
			),
			'client_id' => array(
				'title' => 'Client_id',
				'type' => 'text',
				'description' =>
					__( 'Insert your Mercado Pago Client_id.', 'woocommerce-mercadopago-module' ),
				'default' => '',
				'required' => true
			),
			'client_secret' => array(
				'title' => 'Client_secret',
				'type' => 'text',
				'description' =>
					__( 'Insert your Mercado Pago Client_secret.', 'woocommerce-mercadopago-module' ),
				'default' => '',
				'required' => true
			),
			'ipn_url' => array(
				'title' =>
					__( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) .
					'<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPago_Gateway' ) . '</code>.'
				)
			),
			'checkout_options_title' => array(
				'title' => __( 'Checkout Options', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => ''
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' =>
					__( 'Title shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Mercado Pago', 'woocommerce-mercadopago-module' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-mercadopago-module' ),
				'type' => 'textarea',
				'description' =>
					__( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' )
			),
			'category_id' => array(
				'title' => __( 'Store Category', 'woocommerce-mercadopago-module' ),
				'type' => 'select',
				'description' =>
					__( 'Define which type of products your store sells.', 'woocommerce-mercadopago-module' ),
				'options' => $this->store_categories_id
			),
			'invoice_prefix' => array(
				'title' => __( 'Store Identificator', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' =>
					__( 'Please, inform a prefix to your store.', 'woocommerce-mercadopago-module' )
					. ' ' .
					__( 'If you use your Mercado Pago account on multiple stores you should make sure that this prefix is unique as Mercado Pago will not allow orders with same identificators.', 'woocommerce-mercadopago-module' ),
				'default' => 'WC-'
			),
			'method' => array(
				'title' => __( 'Integration Method', 'woocommerce-mercadopago-module' ),
				'type' => 'select',
				'description' => __( 'Select how your clients should interact with Mercado Pago. Modal Window (inside your store), Redirect (Client is redirected to Mercado Pago), or iFrame (an internal window is embedded to the page layout).', 'woocommerce-mercadopago-module' ),
				'default' => 'iframe',
				'options' => array(
					'iframe' => __( 'iFrame', 'woocommerce-mercadopago-module' ),
					'modal' => __( 'Modal Window', 'woocommerce-mercadopago-module' ),
					'redirect' => __( 'Redirect', 'woocommerce-mercadopago-module' )
				)
			),
			'iframe_width' => array(
				'title' => __( 'iFrame Width', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => $this->iframe_width_desc,
				'default' => '640'
			),
			'iframe_height' => array(
				'title' => __( 'iFrame Height', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => $this->iframe_height_desc,
				'default' => '800'
			),
			'auto_return' => array(
				'title' => __( 'Auto Return', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Automatic Return After Payment', 'woocommerce-mercadopago-module' ),
				'default' => 'yes',
				'description' =>
					__( 'After the payment, client is automatically redirected.', 'woocommerce-mercadopago-module' ),
			),
			'back_url_title' => array(
				'title' => __( 'Back URL Options', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => ''
			),
			'success_url' => array(
				'title' => __( 'Sucess URL', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => __( 'Where customers should be redirected after a successful purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago-module' ),
				'default' => ''
			),
			'failure_url' => array(
				'title' => __( 'Failure URL', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => __( 'Where customers should be redirected after a failed purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago-module' ),
				'default' => ''
			),
			'pending_url' => array(
				'title' => __( 'Pending URL', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => __( 'Where customers should be redirected after a pending purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago-module' ),
				'default' => ''
			),
			'payment_title' => array(
				'title' => __( 'Payment Options', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => ''
			),
			'currency_conversion' => array(
				'title' => __( 'Currency Conversion', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' =>
					__( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio.', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' => sprintf( '%s', $this->currency_message )
			),
			'installments' => array(
				'title' => __( 'Max installments', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => $this->installments_desc,
				'default' => '24'
			),
			'ex_payments' => array(
			  'title' => __( 'Exclude Payment Methods', 'woocommerce-mercadopago-module' ),
			  'description' => $this->payment_desc,
			  'type' => 'multiselect',
			  'options' => $this->payment_methods,
			  'default' => ''
			),
			'payment_split_mode' => array(
				'title' => __( 'Two Cards Mode', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Payments with Two Cards', 'woocommerce-mercadopago-module' ),
				'default' => ( $this->payment_split_mode == 'active' ? 'yes' : 'no' ),
				'description' =>
					__( 'Your customer will be able to use two different cards to pay the order.', 'woocommerce-mercadopago-module' ),
			),
			'testing' => array(
				'title' => __( 'Test and Debug Options', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => ''
			),
			'sandbox' => array(
				'title' => __( 'Mercado Pago Sandbox', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Mercado Pago Sandbox', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' =>
					__( 'This option allows you to test payments inside a sandbox environment.', 'woocommerce-mercadopago-module' ),
			),
			'debug' => array(
				'title' => __( 'Debug and Log', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable log', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' => sprintf(
					__( 'Register event logs of Mercado Pago, such as API requests, in the file', 'woocommerce-mercadopago-module' ) .
					' %s.', $this->build_log_path_string() . '.<br>' .
					__( 'File location: ', 'woocommerce-mercadopago-module' ) .
					'<code>wordpress/wp-content/uploads/wc-logs/' . $this->id . '-' .
					sanitize_file_name( wp_hash( $this->id) ) . '.log</code>' )
			)
		);

	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the
	 * erroring field out.
	 * @return bool was anything saved?
	 */
	public function custom_process_admin_options() {
		$this->init_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					if ( $key == 'payment_split_mode' ) {
						// We dont save split mode as it should come from api.
						$value = $this->get_field_value( $key, $field, $post_data );
						$this->payment_split_mode = ( $value == 'yes' ? 'active' : 'inactive' );
					} else {
						$this->settings[$key] = $this->get_field_value( $key, $field, $post_data );
					}
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}

		if ( ! empty( $this->settings['client_id'] ) && ! empty( $this->settings['client_secret'] ) ) {
			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->settings['client_id'],
				$this->settings['client_secret']
			);
		} else {
			$this->mp = null;
		}

		// analytics
		if ( $this->mp != null ) {
			$infra_data = WC_WooMercadoPago_Module::get_common_settings();
			$infra_data['checkout_basic'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			// $infra_data['mercado_envios'] = 'false';
			$infra_data['two_cards'] = ( $this->payment_split_mode == 'active' ? 'true' : 'false' );
			$response = $this->mp->analytics_save_settings( $infra_data );
			if ( 'yes' == $this->debug) {
				$this->log->add(
					$this->id,
					'[custom_process_admin_options] - analytics response: ' .
					json_encode( $response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
		}

		// two cards mode
		if ( $this->mp != null ) {
			$response = $this->mp->set_two_cards_mode( $this->payment_split_mode );
		}

		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
		);
	}

	/**
	 * Handles the manual order refunding in server-side.
	 */
	public function process_refund_order_meta_box_actions( $order ) {

		if ( get_post_meta( $order->id, '_used_gateway', true ) != 'WC_WooMercadoPago_Gateway' )
			return;

		$payments = get_post_meta(
			$order->id,
			__( 'Mercado Pago Payment ID', 'woocommerce-mercadopago-module' ),
			true
		);

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_refund_order_meta_box_actions] - refunding payments for ' . $payments
			);
		}

		if ( $this->mp != null && ! empty( $payments ) ) {
			$payment_ids = explode( ', ', $payments );
			foreach ( $payment_ids as $p_id ) {
				$response = $this->mp->refund_payment( $p_id );
				$message = $response['response']['message'];
				$status = $response['status'];
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[process_refund_order_meta_box_actions] - refund payment of id ' . $p_id .
						' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
					);
				}
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_refund_order_meta_box_actions] - no payments or credentials invalid'
				);
			}
		}

	}

	/**
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {

		if ( get_post_meta( $order->id, '_used_gateway', true ) != 'WC_WooMercadoPago_Gateway' )
			return;

		$payments = get_post_meta(
			$order->id,
			__( 'Mercado Pago Payment ID', 'woocommerce-mercadopago-module' ),
			true
		);

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_cancel_order_meta_box_actions] - cancelling payments for ' . $payments
			);
		}

		if ( $this->mp != null && ! empty( $payments ) ) {
			$payment_ids = explode( ', ', $payments );
			foreach ( $payment_ids as $p_id ) {
				$response = $this->mp->cancel_payment( $p_id );
				$message = $response['response']['message'];
				$status = $response['status'];
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[process_cancel_order_meta_box_actions] - cancel payment of id ' . $p_id .
						' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
					);
				}
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_cancel_order_meta_box_actions] - no payments or credentials invalid'
				);
			}
		}

	}

	/*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES (CLIENT SIDE)
	 * ========================================================================
	 */

	public function payment_fields() {
		// basic checkout
		if ( $description = $this->get_description() ) {
			echo wpautop(wptexturize( $description ) );
		}
		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form();
		}
	}

	public function add_checkout_script() {

		$w = WC_WooMercadoPago_Module::woocommerce_instance();
		$logged_user_email = null;
		$payments = array();
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		foreach ( $gateways as $g ) {
			$payments[] = $g->id;
		}
		$payments = str_replace( '-', '_', implode( ', ', $payments ) );

		if ( wp_get_current_user()->ID != 0 ) {
			$logged_user_email = wp_get_current_user()->user_email;
		}

		?>
		<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
		<script type="text/javascript">
			var MA = ModuleAnalytics;
			MA.setToken( '<?php echo $this->get_option( 'client_id' ); ?>' );
			MA.setPlatform( 'WooCommerce' );
			MA.setPlatformVersion( '<?php echo $w->version; ?>' );
			MA.setModuleVersion( '<?php echo WC_WooMercadoPago_Module::VERSION; ?>' );
			MA.setPayerEmail( '<?php echo ( $logged_user_email != null ? $logged_user_email : "" ); ?>' );
			MA.setUserLogged( <?php echo ( empty( $logged_user_email ) ? 0 : 1 ); ?> );
			MA.setInstalledModules( '<?php echo $payments; ?>' );
			MA.post();
		</script>
		<?php

	}

	public function update_checkout_status( $order_id ) {

		if ( get_post_meta( $order_id, '_used_gateway', true ) != 'WC_WooMercadoPago_Gateway' )
			return;

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[update_checkout_status] - updating checkout statuses ' . $order_id
			);
		}

		echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
		<script type="text/javascript">
			var MA = ModuleAnalytics;
			MA.setToken( ' . $this->get_option( 'client_id' ) . ' );
			MA.setPaymentType("basic");
			MA.setCheckoutType("basic");
			MA.put();
		</script>';

	}

	/**
	 * Summary: Handle the payment and processing the order.
	 * Description: First step occurs when the customer selects Mercado Pago and proceed to checkout.
	 * This method verify which integration method was selected and makes the build for the checkout
	 * URL.
	 * @return an array containing the result of the processment and the URL to redirect.
	 */
	public function process_payment( $order_id ) {

		update_post_meta( $order_id, '_used_gateway', 'WC_WooMercadoPago_Gateway' );

		$order = new WC_Order( $order_id );

		if ( 'redirect' == $this->method ) {
			// The checkout is made by redirecting customer to Mercado Pago.
			if ( 'yes' == $this->debug) {
				$this->log->add(
					$this->id,
					'[process_payment] - customer being redirected to Mercado Pago.'
				);
			}
			return array(
				'result' => 'success',
				'redirect' => $this->create_url( $order )
			);
		} elseif ( 'modal' == $this->method || 'iframe' == $this->method ) {
			// The checkout is made by customizing the view, either by iframe or showing a modal.
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_payment] - preparing to render Mercado Pago checkout view.'
				);
			}
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' => add_query_arg(
						'order',
						$order->id,
						add_query_arg(
							'key',
							$order->order_key,
							get_permalink( woocommerce_get_page_id( 'pay' ) )
						)
					)
				);
			}
		}
	}

	/**
	 * Summary: Show the custom renderization for the checkout.
	 * Description: Order page and this generates the form that shows the pay button. This step
	 * generates the form to proceed to checkout.
	 * @return the html to be rendered.
	 */
	public function receipt_page( $order ) {
		echo $this->render_order_form( $order );
	}

	// --------------------------------------------------

	public function render_order_form( $order_id ) {

		$order = new WC_Order( $order_id );
		$url = $this->create_url( $order );

		if ( $url ) {
			$html =
				'<img width="468" height="60" src="' . $this->country_configs['checkout_banner'] . '">';
			if ( 'modal' == $this->method ) {
				// The checkout is made by displaying a modal to the customer.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[render_order_form] - rendering Mercado Pago lightbox (modal window).'
					);
				}
				$html .= '<p></p><p>' . wordwrap(
					__( 'Thank you for your order. Please, proceed with your payment clicking in the bellow button.', 'woocommerce-mercadopago-module' ),
					60, '<br>'
				) . '</p>';
				$html .=
					'<a id="submit-payment" href="' . $url .
					'" name="MP-Checkout" class="button alt" mp-mode="modal">' .
					__( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' ) .
					'</a> ';
				$html .=
					'<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' .
					__( 'Cancel order &amp; Clear cart', 'woocommerce-mercadopago-module' ) .
					'</a><style type="text/css">#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important;  height: 590px !important; }</style>';
				// Includes the javascript of lightbox.
				$html .=
					'<script type="text/javascript">(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true;s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"https://mp-tools.mlstatic.com/buttons/")+"render.js";var x = document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();}window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent("onload", $MPBR_load) : window.addEventListener("load", $MPBR_load, false) ) : null;})();</script>';
			} else {
				// The checkout is made by rendering Mercado Pago form within a iframe.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[render_order_form] - embedding Mercado Pago iframe.'
					);
				}
				$html .= '<p></p><p>' . wordwrap(
					__( 'Thank you for your order. Proceed with your payment completing the following information.', 'woocommerce-mercadopago-module' ),
					60, '<br>'
				) . '</p>';
				$html .= '<iframe src="' . $url . '" name="MP-Checkout" ' .
					'width="' . ( is_numeric( (int) $this->iframe_width ) ? $this->iframe_width : 640 ) .
					'" ' .
					'height="' . ( is_numeric( (int) $this->iframe_height ) ? $this->iframe_height : 800 ) .
					'" ' .
					'frameborder="0" scrolling="no" id="checkout_mercadopago"></iframe>';
			}
			return $html;
		} else {
			// Reaching at this point means that the URL could not be build by some reason.
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[render_order_form] - unable to build Mercado Pago checkout URL.'
				);
			}
			$html = '<p>' .
				__( 'An error occurred when proccessing your payment. Please try again or contact us for assistence.', 'woocommerce-mercadopago-module' ) .
				'</p>';
			$html .= '<a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
				__( 'Click to try again', 'woocommerce-mercadopago-module' ) .
				'</a>';
			return $html;
		}
	}

	/**
	 * Summary: Build Mercado Pago preference.
	 * Description: Create Mercado Pago preference and get init_point URL based in the order options
	 * from the cart.
	 * @return the preference object.
	 */
	public function build_payment_preference( $order ) {

		// A string to register items (workaround to deal with API problem that shows only first item)
		$list_of_items = array();

		//selected shipping
		$selected_shipping = $order->get_shipping_method();

		// Here we build the array that contains ordered items, from customer cart
		$items = array();
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item['product_id'] );
					$product_title = WC_WooMercadoPago_Module::utf8_ansi(
						$product->post->post_title
					);
					$product_content = WC_WooMercadoPago_Module::utf8_ansi(
						$product->post->post_content
					);
					// Remove decimals if MCO/MLC
					$unit_price = floor( ( (float) $item['line_total'] + (float) $item['line_tax'] ) *
						( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100;
					if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
						$unit_price = floor( $unit_price );
					}
					array_push( $list_of_items, $product_title . ' x ' . $item['qty'] );
					array_push( $items, array(
						'id' => $item['product_id'],
						'title' => ( $product_title . ' x ' . $item['qty'] ),
						'description' => sanitize_file_name(
							// This handles description width limit of Mercado Pago.
							( strlen( $product_content ) > 230 ?
								substr( $product_content, 0, 230 ) . '...' :
								$product_content )
						),
						'picture_url' => ( sizeof( $order->get_items() ) > 1 ?
							plugins_url( 'images/cart.png', plugin_dir_path( __FILE__ ) ) :
							wp_get_attachment_url( $product->get_image_id() )
						),
						'category_id' => $this->store_categories_id[$this->category_id],
						'quantity' => 1,
						'unit_price' => $unit_price,
						'currency_id' => $this->country_configs['currency']
					) );
				}
			}

			//check is not mercado envios
			if(strpos($selected_shipping, 'Mercado Envios') !== 0){
				// Shipment cost as an item (workaround to prevent API showing shipment setup again).
				$ship_cost = ( (float) $order->get_total_shipping() + (float) $order->get_shipping_tax() ) *
				( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
				// Remove decimals if MCO/MLC
				if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
					$ship_cost = floor( $ship_cost );
				}
				if ( $ship_cost > 0 ) {
					array_push(
						$list_of_items,
						__( 'Shipping service used by store', 'woocommerce-mercadopago-module' )
					);
					array_push( $items, array(
						'id' => 2147483647,
						'title' => implode( ', ', $list_of_items ),
						'description' => implode( ', ', $list_of_items ),
						'category_id' => $this->store_categories_id[$this->category_id],
						'quantity' => 1,
						'unit_price' => $ship_cost,
						'currency_id' => $this->country_configs['currency']
						) );
					}
				}

			// String of item names (workaround to deal with API problem that shows only first item).
			$items[0]['title'] = implode( ', ', $list_of_items );
		}

		// Find excluded methods. If 'n/d' is in array, we should disconsider the remaining values.
		$excluded_payment_methods = array();
		if ( is_array( $this->ex_payments ) || is_object( $this->ex_payments ) ) {
		  foreach ( $this->ex_payments as $excluded ) {
			// if 'n/d' is selected, we just not add any items to the array.
				if ( $excluded == 0 )
					break;
				array_push( $excluded_payment_methods, array(
					'id' => $this->payment_methods[$excluded]
				) );
			}
		}
		$payment_methods = array(
		  'installments' => ( is_numeric( (int) $this->installments) ? (int) $this->installments : 24 ),
		  'default_installments' => 1
		);
		// Set excluded payment methods.
		if ( count( $excluded_payment_methods ) > 0 ) {
			$payment_methods['excluded_payment_methods'] = $excluded_payment_methods;
		}

		// Create Mercado Pago preference.
		$preferences = array(
			'items' => $items,
			// Payer should be filled with billing info as orders can be made with non-logged users.
			'payer' => array(
				'name' => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email' => $order->billing_email,
				'phone'	=> array(
					'number' => $order->billing_phone
				),
				'address' => array(
					'street_name' => $order->billing_address_1 . ' / ' .
						$order->billing_city . ' ' .
						$order->billing_state . ' ' .
						$order->billing_country,
					'zip_code' => $order->billing_postcode
				)
			),
			'back_urls' => array(
				'success' => ( empty( $this->success_url ) ?
					WC_WooMercadoPago_Module::workaround_ampersand_bug(
						esc_url( $this->get_return_url( $order) )
					) : $this->success_url
				),
				'failure' => ( empty( $this->failure_url ) ?
					WC_WooMercadoPago_Module::workaround_ampersand_bug(
						str_replace( '&amp;', '&', $order->get_cancel_order_url() )
					) : $this->failure_url
				),
				'pending' =>  ( empty( $this->pending_url ) ?
					WC_WooMercadoPago_Module::workaround_ampersand_bug(
						esc_url( $this->get_return_url( $order) )
					) : $this->pending_url
				)
			),
			//'marketplace' =>
			//'marketplace_fee' =>
			'shipments' => array(
				//'cost' =>
				//'mode' =>
				'receiver_address' => array(
					'zip_code' => $order->shipping_postcode,
					//'street_number' =>
					'street_name' => $order->shipping_address_1 . ' ' .
						$order->shipping_city . ' ' .
						$order->shipping_state . ' ' .
						$order->shipping_country,
					//'floor' =>
					'apartment' => $order->shipping_address_2
				)
			),
			'payment_methods' => $payment_methods,
			//'notification_url' =>
			'external_reference' => $this->invoice_prefix . $order->id
			//'additional_info' =>
			//'expires' =>
			//'expiration_date_from' =>
			//'expiration_date_to' =>
		);


		// Set Mercado Envios
		if(strpos($selected_shipping, 'Mercado Envios') === 0){
			$preferences['shipments']['mode'] = "me2";

			foreach ( $order->get_shipping_methods() as $shipping ) {

				$preferences['shipments']['dimensions'] = $shipping['dimensions'];
				$preferences['shipments']['default_shipping_method'] = (int) $shipping['shipping_method_id'];
				$preferences['shipments']['free_methods'] = array();

				//get shipping method id
				$prepare_method_id = explode(":", $shipping['method_id']);

				//get instance_id
				$shipping_id = $prepare_method_id[count($prepare_method_id) - 1];

				// TODO: REFACTOR
				//get zone by instance_id
				$shipping_zone = WC_Shipping_Zones::get_zone_by("instance_id", $shipping_id);

				//get all shipping and filter by free_shipping (Mercado Envios)
				foreach ($shipping_zone->get_shipping_methods() as $key => $shipping_object) {

					//check is a free method
					if($shipping_object->get_option('free_shipping') == 'yes'){
						//get shipping method id (Mercado Envios)
						$shipping_method_id = $shipping_object->get_shipping_method_id($this->site_id);
						$preferences['shipments']['free_methods'][] = array("id" => (int) $shipping_method_id);
					}
				} //end foreach shipping
			} //end foreach order
		}//end if

		// Do not set IPN url if it is a localhost.
		if ( ! strrpos( $this->domain, 'localhost' ) ) {
			$preferences['notification_url'] = WC_WooMercadoPago_Module::workaround_ampersand_bug(
				WC()->api_request_url( 'WC_WooMercadoPago_Gateway' )
			);
		}

		// Set sponsor ID.
		if ( ! $this->is_test_user ) {
			$preferences['sponsor_id'] = $this->country_configs['sponsor_id'];
		}

		// Auto return options.
		if ( 'yes' == $this->auto_return ) {
			$preferences['auto_return'] = 'approved';
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[build_payment_preference] - preference created with following structure: ' .
				json_encode( $preferences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
		}

		$preferences = apply_filters(
			'woocommerce_mercadopago_module_preferences', $preferences, $order
		);

		return $preferences;
	}

	/**
	 * Summary: Build Mercado Pago preapproval.
	 * Description: Create Mercado Pago preapproval structure and get init_point URL based in the order options
	 * from the cart.
	 * @return the preapproval structure.
	 */
	public function build_preapproval( $order ) {

		// Here we build the array that contains ordered items, from customer cart
		$preapproval = null;

		if ( sizeof( $order->get_items() ) == 1 ) {
			$arr = $order->get_items();
			$item = $arr[0];
			if ( $item['qty'] ) {
				$product = new WC_product( $item['product_id'] );
				$product_title = WC_WooMercadoPago_Module::utf8_ansi(
					$product->post->post_title
				);
				// Remove decimals if MCO/MLC
				$unit_price = floor( ( (float) $item['line_total'] + (float) $item['line_tax'] ) *
					( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100;
				if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
					$unit_price = floor( $unit_price );
				}
				// Creates the pre-approval structure
				$preapproval = array(
					'payer_email' => $order->billing_email,
					'back_url' => ( empty( $this->success_url ) ?
						WC_WooMercadoPago_Module::workaround_ampersand_bug(
							esc_url( $this->get_return_url( $order) )
						) : $this->success_url
					),
					'reason' => $product_title . ' x ' . $item['qty'],
					'external_reference' => $this->invoice_prefix . $order->id,
					'auto_recurring' => array(
						'frequency' => $frequency,
						'frequency_type' => $frequency_type,
						'transaction_amount' => $unit_price,
						'currency_id' => $this->country_configs['currency'],
						'start_date' => $start_date,
						'end_date' => $end_date
					)
				);
				// Do not set IPN url if it is a localhost.
				if ( ! strrpos( $this->domain, 'localhost' ) ) {
					$preapproval['notification_url'] = WC_WooMercadoPago_Module::workaround_ampersand_bug(
						WC()->api_request_url( 'WC_WooMercadoPago_Gateway' )
					);
				}
				// Set sponsor ID.
				if ( ! $this->is_test_user ) {
					$preapproval['sponsor_id'] = $this->country_configs['sponsor_id'];
				}

				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[build_preapproval] - preapproval created with following structure: ' .
						json_encode( $preapproval, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
				}
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[build_preapproval] - product data are: ' .
						json_encode( $product, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
				}
			}
		}

		return $preapproval;
	}

	// --------------------------------------------------

	protected function create_url( $order ) {

		// TODO: check product type
		//$pre_approval = $this->build_preapproval( $order );
		//return false;

		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->build_payment_preference( $order );

		// Checks for sandbox mode.
		if ( 'yes' == $this->sandbox ) {
			$this->mp->sandbox_mode( true);
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[create_url] - sandbox mode is enabled'
				);
			}
		} else {
			$this->mp->sandbox_mode( false );
		}

		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_preference(json_encode( $preferences ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago trowed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - mercado pago gave error, payment creation failed with error: ' .
						$checkout_info['response']['status'] );
				}
				return false;
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - wordpress gave error, payment creation failed with error: ' .
						$checkout_info['response']['status'] );
				}
				return false;
			} else {
				// Obtain the URL.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - payment link generated with success from mercado pago, with structure as follow: ' .
						json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
				}
				if ( 'yes' == $this->sandbox) {
					return $checkout_info['response']['sandbox_init_point'];
				} else {
					return $checkout_info['response']['init_point'];
				}
			}
		} catch ( MercadoPagoException $e ) {
			// Something went wrong with the payment creation.
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[create_url] - payment creation failed with exception: ' .
					json_encode( array( 'status' => $e->getCode(), 'message' => $e->getMessage() ) )
				);
			}
			return false;
		}
	}

	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS (SERVER SIDE)
	 * ========================================================================
	 */

	/**
	 * Summary: Check if we have valid credentials.
	 * Description: Check if we have valid credentials.
	 * @return boolean true/false depending on the validation result.
	 */
	public function validate_credentials() {

		if ( empty( $this->client_id ) || empty( $this->client_secret ) )
			return false;

		try {

			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->client_id,
				$this->client_secret
			);
			$access_token = $this->mp->get_access_token();
			$get_request = $this->mp->get( '/users/me?access_token=' . $access_token );

			if ( isset( $get_request['response']['site_id'] ) ) {

				$this->is_test_user = in_array( 'test_user', $get_request['response']['tags'] );
				$this->site_id = $get_request['response']['site_id'];
				$this->collector_id = $get_request['response']['id'];
				$this->country_configs = WC_WooMercadoPago_Module::get_country_config( $this->site_id );
				$this->payment_split_mode = $this->mp->check_two_cards();

				$payments = $this->mp->get( '/v1/payment_methods/?access_token=' . $access_token );
				array_push( $this->payment_methods, 'n/d' );
				foreach ( $payments['response'] as $payment ) {
					array_push( $this->payment_methods, str_replace( '_', ' ', $payment['id'] ) );
				}

				// Check for auto converstion of currency (only if it is enabled).
				$this->currency_ratio = -1;
				if ( $this->currency_conversion == 'yes' ) {
					$this->currency_ratio = WC_WooMercadoPago_Module::get_conversion_rate(
						$this->country_configs['currency']
					);
				}

				return true;

			} else {
				$this->mp = null;
				return false;
			}

		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug) {
				$this->log->add(
					$this->id,
					'[validate_credentials] - while validating credentials, got exception: ' .
					json_encode( array( 'status' => $e->getCode(), 'message' => $e->getMessage() ) )
				);
			}
			$this->mp = null;
			return false;
		}

		return false;

	}

	// Build the string representing the path to the log file.
	protected function build_log_path_string() {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' .
			esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' .
			__( 'WooCommerce &gt; System Status &gt; Logs', 'woocommerce-mercadopago-module' ) . '</a>';
	}

	// Return boolean indicating if currency is supported.
	protected function is_supported_currency() {
		return get_woocommerce_currency() == $this->country_configs['currency'];
	}

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		$available = ( 'yes' == $this->settings['enabled'] ) &&
			! empty( $this->client_id ) &&
			! empty( $this->client_secret );
		return $available;
	}

	// Fix css for Mercado Pago in specific cases.
	public function css() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$page_id = wc_get_page_id( 'checkout' );
		} else {
			$page_id = woocommerce_get_page_id( 'checkout' );
		}
		if ( is_page( $page_id ) ) {
			echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' .
				PHP_EOL;
		}
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopago_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPago_Gateway'
		);
	}

	// Notify that Client_id and/or Client_secret are not valid.
	public function client_id_or_secret_missing_message() {
		echo '<div class="error"><p><strong>' .
			__( 'Basic Checkout is Inactive', 'woocommerce-mercadopago-module' ) .
			'</strong>: ' .
			__( 'Your Mercado Pago credentials Client_id/Client_secret appears to be misconfigured.', 'woocommerce-mercadopago-module' ) .
			'</p></div>';
	}

	/*
	 * ========================================================================
	 * IPN MECHANICS (SERVER SIDE)
	 * ========================================================================
	 */

	/**
	 * Summary: This call checks any incoming notifications from Mercado Pago server.
	 * Description: This call checks any incoming notifications from Mercado Pago server.
	 */
	public function check_ipn_response() {
		@ob_clean();
		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[check_ipn_response] - received _get content: ' .
				json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}
		$data = $this->check_ipn_request_is_valid( $_GET );
		if ( $data ) {
			header( 'HTTP/1.1 200 OK' );
			do_action( 'valid_mercadopago_ipn_request', $data );
		}
	}

	/**
	 * Summary: Get received data from IPN and checks if its a merchant_order or a payment.
	 * Description: If we have these information, we return data to be processed by
	 * successful_request function.
	 * @return boolean indicating if it was successfuly processed.
	 */
	public function check_ipn_request_is_valid( $data ) {

		if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_ipn_request_is_valid] - data_id or type not set: ' .
					json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
			// at least, check if its a v0 ipn.
			if ( ! isset( $data['data_id'] ) || ! isset( $data['type'] ) ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[check_ipn_request_is_valid] - mercado pago request failure: ' .
						json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
				}
				wp_die( __( 'Mercado Pago Request Failure', 'woocommerce-mercadopago-module' ) );
			} else {
				header( 'HTTP/1.1 200 OK' );
			}
			// No ID? No process!
			return false;
		}

		if ( 'yes' == $this->sandbox ) {
			$this->mp->sandbox_mode( true );
		} else {
			$this->mp->sandbox_mode( false );
		}

		try {
			// Get the merchant_order reported by the IPN.
			$params = array( 'access_token' => $this->mp->get_access_token() );
			if ( $data['topic'] == 'merchant_order' ) {
				$merchant_order_info = $this->mp->get( '/merchant_orders/' . $_GET['id'], $params, false );
				// If the payment's transaction amount is equal (or bigger) than the merchant order's
				// amount you can release your items.
				if ( ! is_wp_error( $merchant_order_info ) && ( $merchant_order_info['status'] == 200 ) ) {
					$payments = $merchant_order_info['response']['payments'];
				// check if we have more than one payment method.
					if ( sizeof( $payments ) >= 1 ) {
						// We have payments...
						return $merchant_order_info['response'];
				} else {
					// We have no payments?
						if ( 'yes' == $this->debug ) {
							$this->log->add(
								$this->id,
								'[check_ipn_request_is_valid] - order received but has no payment'
							);
						}
						return false;
					}
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_request_is_valid] - got status not equal 200 or some error'
						);
					}
					return false;
				}
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_ipn_request_is_valid] - while processing ipn, got exception: ' .
					json_encode( array( 'status' => $e->getCode(), 'message' => $e->getMessage() ) )
				);
			}
			return false;
		}
		return true;
	}

	/**
	* Summary: Properly handles each case of notification, based in payment status.
	* Description: Properly handles each case of notification, based in payment status.
	*/
	public function successful_request( $data ) {

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[successful_request] - starting to process ipn update...'
			);
		}

		$order_key = $data['external_reference'];
		if ( ! empty( $order_key ) ) {
			$order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );
			$order = new WC_Order( $order_id );
			// Checks whether the invoice number matches the order, if true processes the payment.
			if ( $order->id === $order_id ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[successful_request] - got order with ID ' . $order->id . ' and status ' .
						$data['payments'][0]['status']
					);
				}
				update_post_meta( $order->id, '_used_gateway', 'WC_WooMercadoPago_Gateway' );
				// Order details.
				if ( ! empty( $data['payer']['email'] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payer email', 'woocommerce-mercadopago-module' ),
						$data['payer']['email']
					);
				}
				if ( ! empty( $data['payment_type'] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payment type', 'woocommerce-mercadopago-module' ),
						$data['payment_type']
					);
				}
				if ( ! empty( $data['payments'] ) ) {
					$payment_ids = array();
					foreach ( $data['payments'] as $payment ) {
						$payment_ids[] = $payment['id'];
					}
					if ( sizeof( $payment_ids ) > 0 ) {
						update_post_meta(
							$order_id,
							__( 'Mercado Pago Payment ID', 'woocommerce-mercadopago-module' ),
							implode( ', ', $payment_ids )
						);
					}
				}
				// Here, we process the status...
				$status = 'pending';
				if ( sizeof( $data['payments'] ) == 1 ) {
					// if there's only one payment, then we get its status.
					$status = $data['payments'][0]['status'];
				} elseif ( sizeof( $data['payments'] ) > 1 ) {
					// otherwise, we check payment sum.
					$total_paid = 0.00;
					foreach ( $data['payments'] as $payment ) {
						if ( $payment['status'] === 'approved' ) {
							$total_paid = $total_paid + (float) $payment['total_paid_amount'];
						}
					}
					$total = $data['shipping_cost'] + $data['total_amount'];
					if ( $total_paid >= $total ) {
						// At this point, the sum of approved payments are above or equal than the total
						// order amount, so it is approved.
						$status = 'approved';
					}
				}
				// Switch the status and update in WooCommerce.
				switch ( $status ) {
					case 'approved':
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'Payment approved.', 'woocommerce-mercadopago-module' )
					);
					$order->payment_complete();
					break;
					case 'pending':
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'in_process':
					$order->update_status(
						'on-hold',
						'Mercado Pago: ' .
						__( 'Payment under review.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'rejected':
					$order->update_status(
						'failed',
						'Mercado Pago: ' .
						__( 'The payment was refused. The customer can try again.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'refunded':
					$order->update_status(
						'refunded',
						'Mercado Pago: ' .
						__( 'The payment was refunded to the customer.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'cancelled':
					$order->update_status(
						'cancelled',
						'Mercado Pago: ' .
						__( 'The payment was cancelled.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'in_mediation':
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module' )
					);
					break;
					case 'charged-back':
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module' )
					);
					break;
					default:
					break;
				}

				$this->check_mercado_envios($data);


			}
		}
	}

	function check_mercado_envios($merchant_order){

		$order_key = $merchant_order['external_reference'];

		if ( ! empty( $order_key ) ) {
			$order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );
			$order = new WC_Order( $order_id );

			if(count($merchant_order['shipments']) > 0){
				foreach ($merchant_order['shipments'] as $shipment) {

					$shipment_id = $shipment['id'];

					//get shipping data on merchant_order
					$shipment_name = $shipment['shipping_option']['name'];
					$shipment_cost = $shipment['shipping_option']['cost'];
					$shipping_method_id = $shipment['shipping_option']['shipping_method_id'];

					// get data shipping selected on checkout
					$shipping_meta = $order->get_items("shipping");
					$order_item_shipping_id = null;
					$method_id = null;
					foreach ($shipping_meta as $key => $shipping) {
						$order_item_shipping_id = $key;
						$method_id = $shipping['method_id'];
					}

					$free_shipping_text = "";
					$free_shipping_status = "no";
					if($shipment_cost == 0 ){
						$free_shipping_status = "yes";
						$free_shipping_text = " (" . __( 'Free Shipping', 'woocommerce' ) . ")";
					}

					// update shipping cost and method title
					$r = $order->update_shipping($order_item_shipping_id, array(
						"method_title" => "Mercado Envios - " . $shipment_name . $free_shipping_text,
						"method_id" => $method_id,
						"cost" => wc_format_decimal($shipment_cost)
					));

					// WTF?
					// https://docs.woocommerce.com/wc-apidocs/source-class-WC_Abstract_Order.html#541
					// FORCE UPDATE SHIPPING
					$order->set_total( wc_format_decimal($shipment_cost) , 'shipping' );

					// update total order
					$order->set_total(
						wc_format_decimal($order->get_subtotal())
						+ wc_format_decimal($order->get_total_shipping())
						+ wc_format_decimal($order->get_total_tax())
						- wc_format_decimal($order->get_total_discount())
					);

					//update additional info
					wc_update_order_item_meta($order_item_shipping_id, 'shipping_method_id', $shipping_method_id);
					wc_update_order_item_meta($order_item_shipping_id, 'free_shipping', $free_shipping_status);

					$access_token = $this->mp->get_access_token();
					$request = array(
						'uri' => "/shipments/" . $shipment_id,
						'params' => array(
							"access_token" => $access_token
						)
					);

					$shipments_data = MeliRestClient::get($request, "");

					switch ($shipments_data['response']['substatus']) {

						case 'ready_to_print':
							$substatus_description = __( 'Tag ready to print', 'woocommerce-mercadopago-module' );
							break;
						case 'printed':
							$substatus_description = __( 'Tag printed', 'woocommerce-mercadopago-module' );
							break;
						case 'stale':
							$substatus_description = __( 'Unsuccessful', 'woocommerce-mercadopago-module' );
							break;
						case 'delayed':
							$substatus_description = __( 'Sending the delayed path', 'woocommerce-mercadopago-module' );
							break;
						case 'receiver_absent':
							$substatus_description = __( 'Missing recipient for delivery', 'woocommerce-mercadopago-module' );
							break;
						case 'returning_to_sender':
							$substatus_description = __( 'In return to sender', 'woocommerce-mercadopago-module' );
							break;
						case 'claimed_me':
							$substatus_description = __( 'Buyer initiates complaint and requested a refund.', 'woocommerce-mercadopago-module' );
							break;
						default:
							$substatus_description = $shipments_data['response']['substatus'];
							break;
						}


						if($substatus_description == ""){
							$substatus_description = $shipments_data['response']['status'];
						}

						$order->add_order_note( 'Mercado Envios: ' . $substatus_description );

						$this->log->add(
							$this->id,
							'[check_mercado_envios] - Mercado Envios - Status : ' .
							$shipments_data['response']['status'] .
							' - substatus : ' .
							$substatus_description
						);

						// add tracking number in meta data to use in order page
						update_post_meta( $order_id, '_mercadoenvios_tracking_number', $shipments_data['response']['tracking_number']);

						// add shipiment_id in meta data to use in order page
						update_post_meta( $order_id, '_mercadoenvios_shipment_id', $shipment_id);

						// add status in meta data to use in order page
						update_post_meta( $order_id, '_mercadoenvios_status', $shipments_data['response']['status']);

						// add  substatus in meta data to use in order page
						update_post_meta( $order_id, '_mercadoenvios_substatus', $shipments_data['response']['substatus']);

					}
				}
			}

		}

}
