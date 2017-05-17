<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// This include Mercado Pago library SDK
require_once dirname( __FILE__ ) . '/sdk/lib/mercadopago.php';

/**
 * Summary: Extending from WooCommerce Payment Gateway class.
 * Description: This class implements Mercado Pago custom checkout.
 * @since 2.0.0
 */
class WC_WooMercadoPagoCustom_Gateway extends WC_Payment_Gateway {

	public function __construct( $is_instance = false ) {

		// Mercado Pago fields.
		$this->mp = null;
		$this->site_id = null;
		$this->collector_id = null;
		$this->currency_ratio = -1;
		$this->is_test_user = false;

		// Auxiliary fields.
		$this->currency_message = '';
		$this->country_configs = array();
		$this->store_categories_id = array();
		$this->store_categories_description = array();

		// WooCommerce fields.
		$this->supports = array( 'products', 'refunds' );
		$this->id = 'woocommerce-mercadopago-custom-module';
		$this->domain = get_site_url() . '/index.php';
		$this->method_title = __( 'Mercado Pago - Credit Card', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url(
				'images/mplogo.png',
				plugin_dir_path( __FILE__ )
			) . '"><br><br>' . '<strong>' .
			__( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ) .
			'</strong>';

		// Fields used in Mercado Pago Module configuration page.
		$this->public_key = $this->get_option( 'public_key' );
		$this->access_token = $this->get_option( 'access_token' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->statement_descriptor = $this->get_option( 'statement_descriptor' );
		$this->coupon_mode = $this->get_option( 'coupon_mode' );
		$this->binary_mode = $this->get_option( 'binary_mode' );
		$this->category_id = $this->get_option( 'category_id' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->currency_conversion = $this->get_option( 'currency_conversion', false );
		$this->gateway_discount = $this->get_option( 'gateway_discount', 0 );
		$this->sandbox = $this->get_option( 'sandbox', false );
		$this->debug = $this->get_option( 'debug', false );

		// Logging and debug.
		if ( 'yes' == $this->debug) {
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
			'woocommerce_api_wc_woomercadopagocustom_gateway',
			array( $this, 'process_http_request' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopagocustom_ipn_request',
			array( $this, 'successful_request' )
		);
		// process the cancel order meta box order action
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		// Scripts for custom checkout.
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'custom_checkout_scripts' )
		);
		// Apply the discounts.
		add_action(
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_discount_custom' ), 10
		);
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_custom' ), 10, 2
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);

		if ( ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] == 'yes' ) {
			if ( $is_instance ) {
				if ( empty( $this->public_key) || empty( $this->access_token ) ) {
					// Verify if public_key or access_token is empty.
					add_action( 'admin_notices', array( $this, 'credentials_missing_message' ) );
				} else {
					if ( empty( $this->sandbox) && $this->sandbox == 'no' ) {
						// Verify if SSL is supported.
						add_action( 'admin_notices', array( $this, 'check_ssl_absence' ) );
					}
				}
			} else {
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
					'label' => __( 'Enable Custom Checkout', 'woocommerce-mercadopago-module' ),
					'default' => 'no'
				)
			);
			return;
		}

		$api_secret_locale = sprintf(
			'<a href="https://www.mercadopago.com/mla/account/credentials?type=custom" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlb/account/credentials?type=custom" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlc/account/credentials?type=custom" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mco/account/credentials?type=custom" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlm/account/credentials?type=custom" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mpe/account/credentials?type=custom" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com/mlv/account/credentials?type=custom" target="_blank">%s</a>',
			__( 'Argentine', 'woocommerce-mercadopago-module' ),
			__( 'Brazil', 'woocommerce-mercadopago-module' ),
			__( 'Chile', 'woocommerce-mercadopago-module' ),
			__( 'Colombia', 'woocommerce-mercadopago-module' ),
			__( 'Mexico', 'woocommerce-mercadopago-module' ),
			__( 'Peru', 'woocommerce-mercadopago-module' ),
			__( 'or', 'woocommerce-mercadopago-module' ),
			__( 'Venezuela', 'woocommerce-mercadopago-module' )
		);

		// Trigger API to get payment methods and site_id, also validates public_key/access_token.
		if ( $this->validate_credentials() ) {
			// checking the currency.
			$this->currency_message = '';
			if ( ! $this->is_supported_currency() && 'yes' == $this->settings['enabled'] ) {
				if ( $this->currency_conversion == 'no' ) {
					$this->currency_ratio = -1;
					$this->currency_message .= WC_WooMercadoPago_Module::build_currency_not_converted_msg(
						$this->country_configs['currency'],
						$this->country_configs['country_name']
					);
				} elseif ( $this->currency_conversion == 'yes' && $this->currency_ratio != -1 ) {
					$this->currency_message .= WC_WooMercadoPago_Module::build_currency_converted_msg(
						$this->country_configs['currency'],
						$this->currency_ratio
					);
				} else {
					$this->currency_ratio = -1;
					$this->currency_message .=
						WC_WooMercadoPago_Module::build_currency_conversion_err_msg(
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
		} else {
			$this->credentials_message = WC_WooMercadoPago_Module::build_invalid_credentials_msg();
		}

		// fill categories (can be handled without credentials).
		$categories = WC_WooMercadoPago_Module::get_categories();
		$this->store_categories_id = $categories['store_categories_id'];
		$this->store_categories_description = $categories['store_categories_description'];

		// Validate discount field.
		if ( ! is_numeric( $this->gateway_discount ) ) {
			$this->gateway_discount_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer greater or equal 0 and smaller than 100.', 'woocommerce-mercadopago-module' );
		} elseif ( $this->gateway_discount < 0 || $this->gateway_discount >= 100 ) {
			$this->gateway_discount_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer greater or equal 0 and smaller than 100.', 'woocommerce-mercadopago-module' );
		} else {
			$this->gateway_discount_desc =
				__( 'Give a percentual discount for your customers if they use this payment gateway.', 'woocommerce-mercadopago-module' );
		}

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Custom Checkout', 'woocommerce-mercadopago-module' ),
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
			'public_key' => array(
				'title' => 'Public key',
				'type' => 'text',
				'description' =>
					__( 'Insert your Mercado Pago Public key.', 'woocommerce-mercadopago-module' ),
				'default' => '',
				'required' => true
			),
			'access_token' => array(
				'title' => 'Access token',
				'type' => 'text',
				'description' =>
					__( 'Insert your Mercado Pago Access token.', 'woocommerce-mercadopago-module' ),
				'default' => '',
				'required' => true
			),
			'ipn_url' => array(
				'title' =>
					__( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) .
					'<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPagoCustom_Gateway' ) . '</code>.'
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
				'default' => __( 'Mercado Pago - Credit Card', 'woocommerce-mercadopago-module' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-mercadopago-module' ),
				'type' => 'textarea',
				'description' =>
					__( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' )
			),
			'statement_descriptor' => array(
				'title' => __( 'Statement Descriptor', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' => __( 'The description that will be shown in your customer\'s invoice.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Mercado Pago', 'woocommerce-mercadopago-module' )
			),
			'coupon_mode' => array(
				'title' => __( 'Coupons', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable coupons of discounts', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' =>
					__( 'If there is a Mercado Pago campaign, allow your store to give discounts to customers.', 'woocommerce-mercadopago-module' )
			),
			'binary_mode' => array(
				'title' => __( 'Binary Mode', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable binary mode for checkout status', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' =>
					__( 'When charging a credit card, only [approved] or [reject] status will be taken.', 'woocommerce-mercadopago-module' )
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
			'currency_conversion' => array(
				'title' => __( 'Currency Conversion', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' =>
					__( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio.', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' => sprintf( '%s', $this->currency_message )
			),
			'gateway_discount' => array(
				'title' => __( 'Discount by Gateway', 'woocommerce-mercadopago-module' ),
				'type' => 'number',
				'description' => $this->gateway_discount_desc,
				'default' => '0'
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
					sanitize_file_name( wp_hash( $this->id ) ) . '.log</code>' )
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
					$this->settings[$key] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
		 	}
		}

		if ( ! empty( $this->settings['public_key'] ) && ! empty( $this->settings['access_token'] ) ) {
			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->settings['access_token']
			);
		} else {
			$this->mp = null;
		}

		// analytics
		$infra_data = WC_WooMercadoPago_Module::get_common_settings();
		$infra_data['checkout_custom_credit_card'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
		$infra_data['checkout_custom_credit_card_coupon'] = ( $this->settings['coupon_mode'] == 'yes' ? 'true' : 'false' );
		if ( $this->mp != null ) {
			$response = $this->mp->analytics_save_settings( $infra_data );
			if ( 'yes' == $this->debug) {
				$this->log->add(
					$this->id,
					'[custom_process_admin_options] - analytics info response: ' .
					json_encode( $response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
		}

		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
	 	);
	}

	/**
	 * Handles the manual order refunding in server-side.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$payments = get_post_meta(
			$order_id,
			'_Mercado_Pago_Payment_IDs',
			true
		);

		// Validate.
		if ( $this->mp == null || empty( $payments ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_refund] - no payments or credentials invalid'
				);
			}
			return false;
		}

		$total_available = 0;
		$payment_structs = array();
		$payment_ids = explode( ', ', $payments );
		foreach ( $payment_ids as $p_id ) {
			$p = get_post_meta(
				$order_id,
				'Mercado Pago - Payment ' . $p_id,
				true
			);
			$p = explode( '/', $p );
			$paid_arr = explode( ' ', substr( $p[2], 1, -1 ) );
			$paid = ( (float) $paid_arr[1] );
			$refund_arr = explode( ' ', substr( $p[3], 1, -1 ) );
			$refund = ( (float) $refund_arr[1] );
			$p_struct = array(
				'id' => $p_id,
				'available_to_refund' => $paid - $refund
			);
			$total_available += $paid - $refund;
			$payment_structs[] = $p_struct;
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_refund] - refunding ' . $amount . ' because of ' . $reason . ' and payments ' .
				json_encode( $payment_structs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		// Do not allow refund more than available or invalid amounts.
		if ( $amount > $total_available || $amount <= 0 ) {
			return false;
		}

		$remaining_to_refund = $amount;
		foreach ( $payment_structs as $to_refund ) {
			if ( $remaining_to_refund <= $to_refund['available_to_refund'] ) {
				// We want to refund an amount that is less than the available for this payment, so we
				// can just refund and return.
				$response = $this->mp->partial_refund_payment(
					$to_refund['id'],
					$remaining_to_refund,
					$reason,
					$this->invoice_prefix . $order_id
				);
				$message = $response['response']['message'];
				$status = $response['status'];
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[process_refund] - refund payment of id ' . $p_id .
						' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
					);
				}
				if ( $status >= 200 && $status < 300 ) {
					return true;
				} else {
					return false;
				}
			} elseif ( $to_refund['available_to_refund'] > 0 ) {
				// We want to refund an amount that exceeds the available for this payment, so we
				// totally refund this payment, and try to complete refund in other/next payments.
				$response = $this->mp->partial_refund_payment(
					$to_refund['id'],
					$to_refund['available_to_refund'],
					$reason,
					$this->invoice_prefix . $order_id
				);
				$message = $response['response']['message'];
				$status = $response['status'];
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[process_refund] - refund payment of id ' . $p_id .
						' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
					);
				}
				if ( $status < 200 || $status >= 300 ) {
					return false;
				}
				$remaining_to_refund -= $to_refund['available_to_refund'];
			}
			if ( $remaining_to_refund == 0 )
				return true;
		}

		// Reaching here means that there we run out of payments, and there is an amount
		// remaining to be refund, which is impossible as it implies refunding more than
		// available on paid amounts.
		return false;

	}

	/**
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_meta' ) ) {
			$payments = $order->get_meta( '_Mercado_Pago_Payment_IDs' );
		} else {
			$payments = get_post_meta( $order->id, '_Mercado_Pago_Payment_IDs',	true );
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

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

	public function add_checkout_script() {

		$public_key = $this->get_option( 'public_key' );

		if ( ! empty( $public_key ) ) {

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
				MA.setPublicKey( '<?php echo $public_key; ?>' );
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

	}

	public function update_checkout_status( $order_id ) {

		$public_key = $this->get_option( 'public_key' );

		if ( ! empty( $public_key ) ) {

			$order = wc_get_order( $order_id );
			if ( $this->id !== $order->get_payment_method() ) {
				return;
			}

			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[update_checkout_status] - updating order of ID ' . $order_id
				);
			}

			echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				var MA = ModuleAnalytics;
				MA.setPublicKey( "' . $public_key . '" );
				MA.setPaymentType("credit_card");
				MA.setCheckoutType("custom");
				MA.put();
			</script>';

		}

	}

	public function custom_checkout_scripts() {
		if ( is_checkout() && $this->is_available() ) {
			if ( ! get_query_var( 'order-received' ) ) {
				wp_enqueue_style(
					'woocommerce-mercadopago-style', plugins_url(
						'assets/css/custom_checkout_mercadopago.css',
						plugin_dir_path( __FILE__ ) ) );
				wp_enqueue_script(
					'woocommerce-mercadopago-v1',
					'https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js' );
			}
		}
	}

	public function payment_fields() {
		$amount = $this->get_order_total();

		$parameters = array(
			'public_key' => $this->public_key,
			'site_id' => $this->site_id,
			'images_path' => plugins_url( 'images/', plugin_dir_path( __FILE__ ) ),
			'banner_path' => $this->country_configs['checkout_banner_custom'],
			'amount' => $amount *
				( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ),
			'coupon_mode' => $this->coupon_mode,
			'is_currency_conversion' => $this->currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency' => $this->country_configs['currency'],
			'discount_action_url' => $this->domain .
				'/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPagoCustom_Gateway',
			'form_labels' => array(
				'form' => array(
					'payment_converted' =>
						__( 'Payment converted from', 'woocommerce-mercadopago-module' ),
					'to' => __( 'to', 'woocommerce-mercadopago-module' ),
					'coupon_empty' =>
						__( 'Please, inform your coupon code', 'woocommerce-mercadopago-module' ),
					'apply' => __( 'Apply', 'woocommerce-mercadopago-module' ),
					'remove' => __( 'Remove', 'woocommerce-mercadopago-module' ),
					'discount_info1' => __( 'You will save', 'woocommerce-mercadopago-module' ),
					'discount_info2' => __( 'with discount from', 'woocommerce-mercadopago-module' ),
					'discount_info3' => __( 'Total of your purchase:', 'woocommerce-mercadopago-module' ),
					'discount_info4' =>
						__( 'Total of your purchase with discount:', 'woocommerce-mercadopago-module' ),
					'discount_info5' => __( '*Uppon payment approval', 'woocommerce-mercadopago-module' ),
					'discount_info6' =>
						__( 'Terms and Conditions of Use', 'woocommerce-mercadopago-module' ),
					'coupon_of_discounts' => __( 'Discount Coupon', 'woocommerce-mercadopago-module' ),
					'label_other_bank' => __( 'Other Bank', 'woocommerce-mercadopago-module' ),
					'label_choose' => __( 'Choose', 'woocommerce-mercadopago-module' ),
					'your_card' => __( 'Your Card', 'woocommerce-mercadopago-module' ),
					'other_cards' => __( 'Other Cards', 'woocommerce-mercadopago-module' ),
					'other_card' => __( 'Other Card', 'woocommerce-mercadopago-module' ),
					'ended_in' => __( 'ended in', 'woocommerce-mercadopago-module' ),
					'card_holder_placeholder' =>
						__( ' as it appears in your card ...', 'woocommerce-mercadopago-module' ),
					'payment_method' => __( 'Payment Method', 'woocommerce-mercadopago-module' ),
					'credit_card_number' => __( 'Credit card number', 'woocommerce-mercadopago-module' ),
					'expiration_month' => __( 'Expiration month', 'woocommerce-mercadopago-module' ),
					'expiration_year' => __( 'Expiration year', 'woocommerce-mercadopago-module' ),
					'year' => __( 'Year', 'woocommerce-mercadopago-module' ),
					'month' => __( 'Month', 'woocommerce-mercadopago-module' ),
					'card_holder_name' => __( 'Card holder name', 'woocommerce-mercadopago-module' ),
					'security_code' => __( 'Security code', 'woocommerce-mercadopago-module' ),
					'document_type' => __( 'Document Type', 'woocommerce-mercadopago-module' ),
					'document_number' => __( 'Document number', 'woocommerce-mercadopago-module' ),
					'issuer' => __( 'Issuer', 'woocommerce-mercadopago-module' ),
					'installments' => __( 'Installments', 'woocommerce-mercadopago-module' )
			),
			'error' => array(
					// Card number.
					'205' =>
						__( 'Parameter cardNumber can not be null/empty', 'woocommerce-mercadopago-module' ),
					'E301' => __( 'Invalid Card Number', 'woocommerce-mercadopago-module' ),
					// Expiration date.
					'208' => __( 'Invalid Expiration Date', 'woocommerce-mercadopago-module' ),
					'209' => __( 'Invalid Expiration Date', 'woocommerce-mercadopago-module' ),
					'325' => __( 'Invalid Expiration Date', 'woocommerce-mercadopago-module' ),
					'326' => __( 'Invalid Expiration Date', 'woocommerce-mercadopago-module' ),
					// Card holder name.
					'221' =>
						__( 'Parameter cardholderName can not be null/empty', 'woocommerce-mercadopago-module' ),
					'316' => __( 'Invalid Card Holder Name', 'woocommerce-mercadopago-module' ),
					// Security code.
					'224' =>
						__( 'Parameter securityCode can not be null/empty', 'woocommerce-mercadopago-module' ),
					'E302' => __( 'Invalid Security Code', 'woocommerce-mercadopago-module' ),
					// Doc type.
					'212' =>
						__( 'Parameter docType can not be null/empty', 'woocommerce-mercadopago-module' ),
					'322' => __( 'Invalid Document Type', 'woocommerce-mercadopago-module' ),
					// Doc number.
					'214' =>
						__( 'Parameter docNumber can not be null/empty', 'woocommerce-mercadopago-module' ),
					'324' => __( 'Invalid Document Number', 'woocommerce-mercadopago-module' ),
					// Doc sub type.
					'213' => __( 'The parameter cardholder.document.subtype can not be null or empty', 'woocommerce-mercadopago-module' ),
					'323' => __( 'Invalid Document Sub Type', 'woocommerce-mercadopago-module' ),
					// Issuer.
					'220' =>
						__( 'Parameter cardIssuerId can not be null/empty', 'woocommerce-mercadopago-module' )
				)
			)
		);

		// Find logged user.
		$customer_cards = array();
		try {
			$logged_user_email = null;
			$parameters['customerId'] = null;
			$parameters['payer_email'] = null;
			if ( wp_get_current_user()->ID != 0 ) {
				$logged_user_email = wp_get_current_user()->user_email;
			}
			if ( isset( $logged_user_email ) ) {
				$customer = $this->mp->get_or_create_customer( $logged_user_email);
				if ( isset( $logged_user_email ) ) {
					$parameters['payer_email'] = $logged_user_email;
				}
				if ( isset( $customer['id'] ) ) {
					$parameters['customerId'] = $customer['id'];
				}
				if ( isset( $customer['cards'] ) ) {
					$customer_cards = $customer['cards'];
				}
			} else {
				$parameters['coupon_mode'] = 'no';
			}
		} catch ( Exception $e ) {
			$parameters['coupon_mode'] = 'no';
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_fields] - there is a problem when retrieving information for cards: ' .
					json_encode( array( 'status' => $e->getCode(), 'message' => $e->getMessage() ) )
				);
			}
		}
		$parameters['customer_cards'] = $customer_cards;

		wc_get_template(
			'credit-card/payment-form.php',
			$parameters,
			'woocommerce/mercadopago/',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Summary: Handle the payment and processing the order.
	 * Description: This function is called after we click on [place_order] button, and each field is
	 * passed to this function through $_POST variable.
	 * @return an array containing the result of the processment and the URL to redirect.
	 */
	public function process_payment( $order_id ) {

		if ( ! isset( $_POST['mercadopago_custom'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$custom_checkout = $_POST['mercadopago_custom'];

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->save();
		}

		// We have got parameters from checkout page, now its time to charge the card.
		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_payment] - Received [$_POST] from customer front-end page: ' .
				json_encode( $_POST, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		// Mexico country case.
		if ( $custom_checkout['paymentMethodId'] == '' || empty( $custom_checkout['paymentMethodId'] ) ) {
			$custom_checkout['paymentMethodId'] = $custom_checkout['paymentMethodSelector'];
		}

		if ( isset( $custom_checkout['amount'] ) && ! empty( $custom_checkout['amount'] ) &&
			isset( $custom_checkout['token'] ) && ! empty( $custom_checkout['token'] ) &&
			isset( $custom_checkout['paymentMethodId'] ) && ! empty( $custom_checkout['paymentMethodId'] ) &&
			isset( $custom_checkout['installments'] ) && ! empty( $custom_checkout['installments'] ) &&
			$custom_checkout['installments'] != -1 ) {

			$response = self::create_url( $order, $custom_checkout );

			if (array_key_exists( 'status', $response ) ) {
				switch ( $response['status'] ) {
					case 'approved':
						WC()->cart->empty_cart();
						wc_add_notice(
							'<p>' .
								__( $this->get_order_status( 'accredited' ), 'woocommerce-mercadopago-module' ) .
							'</p>',
							'notice'
						);
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'Payment approved.', 'woocommerce-mercadopago-module' )
						);
						return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_order_received_url()
						);
						break;
				case 'pending':
					// Order approved/pending, we just redirect to the thankyou page.
					return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_order_received_url()
						);
						break;
				case 'in_process':
					// For pending, we don't know if the purchase will be made, so we must inform this status.
					WC()->cart->empty_cart();
						wc_add_notice(
							'<p>' .
								__( $this->get_order_status( $response['status_detail'] ), 'woocommerce-mercadopago-module' ) .
							'</p>' .
							'<p><a class="button" href="' .
								esc_url( $order->get_checkout_order_received_url() ) .
							'">' .
								__( 'Check your order resume', 'woocommerce-mercadopago-module' ) .
							'</a></p>',
							'notice'
						);
						return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_payment_url( true )
						);
						break;
				case 'rejected':
						// If rejected is received, the order will not proceed until another payment try,
						// so we must inform this status.
						wc_add_notice(
							'<p>' .
								__( 'Your payment was refused. You can try again.', 'woocommerce-mercadopago-module' ) .
							'<br>' .
								__( $this->get_order_status( $response['status_detail'] ), 'woocommerce-mercadopago-module' ) .
							'</p>' .
							'<p><a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
								__( 'Click to try again', 'woocommerce-mercadopago-module' ) .
							'</a></p>',
							'error'
						);
						return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_payment_url( true )
						);
						break;
					case 'cancelled':
					case 'in_mediation':
					case 'charged-back':
						break;
					default:
						break;
				}
			}
		} else {
			// Process when fields are imcomplete.
			wc_add_notice(
				'<p>' .
					__( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago-module' ) .
				'</p>',
				'error'
			);
			return array(
				'result' => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Summary: Build Mercado Pago preference.
	 * Description: Create Mercado Pago preference and get init_point URL based in the order options
	 * from the cart.
	 * @return the preference object.
	 */
	private function build_payment_preference( $order, $custom_checkout ) {

		// A string to register items (workaround to deal with API problem that shows only first item).
		$list_of_items = array();
		$amount_of_items = 0;

		// Here we build the array that contains ordered items, from customer cart.
		$items = array();
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item['product_id'] );

					// WooCommerce 3.0 or later.
					if ( method_exists( $product, 'get_description' ) ) {
						$product_title = WC_WooMercadoPago_Module::utf8_ansi(
							$product->get_name()
						);
						$product_content = WC_WooMercadoPago_Module::utf8_ansi(
							$product->get_description()
						);
					} else {
						$product_title = WC_WooMercadoPago_Module::utf8_ansi(
							$product->post->post_title
						);
						$product_content = WC_WooMercadoPago_Module::utf8_ansi(
							$product->post->post_content
						);
					}
					
					// Calculate discount for payment method.
					$unit_price = floor( ( (float) $item['line_total'] + (float) $item['line_tax'] ) *
						( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100;
					if ( is_numeric( $this->gateway_discount ) ) {
						if ( $this->gateway_discount >= 0 && $this->gateway_discount < 100 ) {
							$price_percent = $this->gateway_discount / 100;
							$discount = $unit_price * $price_percent;
							if ( $discount > 0 ) {
								$amount_of_items += $discount;
							}
						}
					}

					// Remove decimals if MCO/MLC
					if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
						$unit_price = floor( $unit_price );
						$amount_of_items = floor( $amount_of_items );
					}

					array_push( $list_of_items, $product_title . ' x ' . $item['qty'] );
					array_push( $items, array(
						'id' => $item['product_id'],
						'title' => ( html_entity_decode( $product_title ) . ' x ' . $item['qty'] ),
						'description' => sanitize_file_name( html_entity_decode( 
							// This handles description width limit of Mercado Pago.
							( strlen( $product_content ) > 230 ?
								substr( $product_content, 0, 230 ) . '...' :
								$product_content )
						) ),
						'picture_url' => wp_get_attachment_url( $product->get_image_id() ),
						'category_id' => $this->store_categories_id[$this->category_id],
						'quantity' => 1,
						'unit_price' => $unit_price
					) );
				}
			}
		}

		// Creates the shipment cost structure.
		$ship_cost = ( (float) $order->get_total_shipping() + (float) $order->get_shipping_tax() ) *
			( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
		// Remove decimals if MCO/MLC
		if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
			$ship_cost = floor( $ship_cost );
		}
		if ( $ship_cost > 0 ) {
			$item = array(
				'title' => sanitize_file_name( $order->get_shipping_to_display() ),
				'description' => __( 'Shipping service used by store', 'woocommerce-mercadopago-module' ),
				'quantity' => 1,
				'category_id' => $this->store_categories_id[$this->category_id],
				'unit_price' => floor( $ship_cost * 100 ) / 100
			);
			$items[] = $item;
		}

		// Discounts features.
		if ( isset( $custom_checkout['discount'] ) && $custom_checkout['discount'] != '' &&
			$custom_checkout['discount'] > 0 && isset( $custom_checkout['coupon_code'] ) &&
			$custom_checkout['coupon_code'] != '' &&
			WC()->session->chosen_payment_method == 'woocommerce-mercadopago-custom-module' ) {

			// Remove decimals if MCO/MLC
			if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
				$custom_checkout['discount'] = floor( $custom_checkout['discount'] );
			}

			$item = array(
				'title' => __( 'Discount', 'woocommerce-mercadopago-module' ),
				'description' => __( 'Discount provided by store', 'woocommerce-mercadopago-module' ),
				'quantity' => 1,
				'category_id' => $this->store_categories_id[$this->category_id],
				'unit_price' => -( (float) $custom_checkout['discount'] )
			);
			$items[] = $item;
		}

		// Build additional information from the customer data.
		if ( method_exists( $order, 'get_id' ) ) {
			// Build additional information from the customer data.
			$payer_additional_info = array(
				'first_name' => html_entity_decode( $order->get_billing_first_name() ),
				'last_name' => html_entity_decode( $order->get_billing_last_name() ),
				//'registration_date' =>
				'phone' => array(
					//'area_code' =>
					'number' => $order->get_billing_phone(),
				),
				'address' => array(
					'zip_code' => $order->get_billing_postcode(),
					//'street_number' =>
					'street_name' => html_entity_decode(
						$order->get_billing_address_1() . ' / ' .
						$order->get_billing_city() . ' ' .
						$order->get_billing_state() . ' ' .
						$order->get_billing_country()
					)
				)
			);
			// Create the shipment address information set.
			$shipments = array(
				'receiver_address' => array(
					'zip_code' => $order->get_shipping_postcode(),
					//'street_number' =>
					'street_name' => html_entity_decode(
						$order->get_shipping_address_1() . ' ' .
						$order->get_shipping_address_2() . ' ' .
						$order->get_shipping_city() . ' ' .
						$order->get_shipping_state() . ' ' .
						$order->get_shipping_country()
					),
					//'floor' =>
					'apartment' => $order->get_shipping_address_2()
				)
			);
			// The payment preference.
			$preferences = array(
				'transaction_amount' => floor( ( (float) $custom_checkout['amount'] ) * 100 ) / 100 - $amount_of_items,
				'token' => $custom_checkout['token'],
				'description' => implode( ', ', $list_of_items ),
				'installments' => (int) $custom_checkout['installments'],
				'payment_method_id' => $custom_checkout['paymentMethodId'],
				'payer' => array(
					'email' => $order->get_billing_email()
				),
				'external_reference' => $this->invoice_prefix . $order->get_id(),
				'statement_descriptor' => $this->statement_descriptor,
				'binary_mode' => ( $this->binary_mode == 'yes' ),
				'additional_info' => array(
					'items' => $items,
					'payer' => $payer_additional_info,
					'shipments' => $shipments
				)
			);
		} else {
			// Build additional information from the customer data.
			$payer_additional_info = array(
				'first_name' => html_entity_decode( $order->billing_first_name ),
				'last_name' => html_entity_decode( $order->billing_last_name ),
				//'registration_date' =>
				'phone' => array(
					//'area_code' =>
					'number' => $order->billing_phone
				),
				'address' => array(
					'zip_code' => $order->billing_postcode,
					//'street_number' =>
					'street_name' => html_entity_decode(
						$order->billing_address_1 . ' / ' .
						$order->billing_city . ' ' .
						$order->billing_state . ' ' .
						$order->billing_country
					)
				)
			);
			// Create the shipment address information set.
			$shipments = array(
				'receiver_address' => array(
					'zip_code' => $order->shipping_postcode,
					//'street_number' =>
					'street_name' => html_entity_decode(
						$order->shipping_address_1 . ' ' .
						$order->shipping_address_2 . ' ' .
						$order->shipping_city . ' ' .
						$order->shipping_state . ' ' .
						$order->shipping_country
					),
					//'floor' =>
					'apartment' => $order->shipping_address_2
				)
			);
			// The payment preference.
			$preferences = array(
				'transaction_amount' => floor( ( (float) $custom_checkout['amount'] ) * 100 ) / 100 - $amount_of_items,
				'token' => $custom_checkout['token'],
				'description' => implode( ', ', $list_of_items ),
				'installments' => (int) $custom_checkout['installments'],
				'payment_method_id' => $custom_checkout['paymentMethodId'],
				'payer' => array(
					'email' => $order->billing_email
				),
				'external_reference' => $this->invoice_prefix . $order->id,
				'statement_descriptor' => $this->statement_descriptor,
				'binary_mode' => ( $this->binary_mode == 'yes' ),
				'additional_info' => array(
					'items' => $items,
					'payer' => $payer_additional_info,
					'shipments' => $shipments
				)
			);
		}

		// Customer's Card Feature, add only if it has issuer id.
		if ( array_key_exists( 'token', $custom_checkout ) ) {
			$preferences['metadata']['token'] = $custom_checkout['token'];
			if ( array_key_exists( 'issuer', $custom_checkout ) ) {
				if ( ! empty( $custom_checkout['issuer'] ) ) {
					$preferences['issuer_id'] = (integer) $custom_checkout['issuer'];
				}
			}
			if ( ! empty( $custom_checkout['CustomerId'] ) ) {
				$preferences['payer']['id'] = $custom_checkout['CustomerId'];
			}
		}

		// Do not set IPN url if it is a localhost.
		if ( ! strrpos( $this->domain, 'localhost' ) ) {
			$preferences['notification_url'] = WC_WooMercadoPago_Module::workaround_ampersand_bug(
				WC()->api_request_url( 'WC_WooMercadoPagoCustom_Gateway' )
			);
		}

		// Discounts features.
		if ( isset( $custom_checkout['discount'] ) && $custom_checkout['discount'] != '' &&
			$custom_checkout['discount'] > 0 && isset( $custom_checkout['coupon_code'] ) &&
			$custom_checkout['coupon_code'] != '' &&
			WC()->session->chosen_payment_method == 'woocommerce-mercadopago-custom-module' ) {

			$preferences['campaign_id'] = (int) $custom_checkout['campaign_id'];
			$preferences['coupon_amount'] = ( (float) $custom_checkout['discount'] );
			$preferences['coupon_code'] = strtoupper( $custom_checkout['coupon_code'] );
		}

		// Set sponsor ID.
		if ( ! $this->is_test_user ) {
			$preferences['sponsor_id'] = $this->country_configs['sponsor_id'];
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[build_payment_preference] - returning just created [$preferences] structure: ' .
				json_encode( $preferences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		$preferences = apply_filters(
			'woocommerce_mercadopago_module_custom_preferences',
			$preferences, $order
		);
		return $preferences;
	}

	// --------------------------------------------------

	protected function create_url( $order, $custom_checkout ) {

		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->build_payment_preference( $order, $custom_checkout );

		// Checks for sandbox mode.
		if ( 'yes' == $this->sandbox ) {
			$this->mp->sandbox_mode( true );
			if ( 'yes' == $this->debug) {
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
			$checkout_info = $this->mp->post( '/v1/payments', json_encode( $preferences) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago trowed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - mercado pago gave error, payment creation failed with error: ' .
						$checkout_info['response']['message'] );
				}
				return false;
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - wordpress gave error, payment creation failed with error: ' .
						$checkout_info['response']['message'] );
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
				return $checkout_info['response'];
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

	/**
	 * Summary: Check if we have existing customer card, if not we create and save it.
	 * Description: Check if we have existing customer card, if not we create and save it.
	 * @return boolean true/false depending on the validation result.
	 */
	public function check_and_save_customer_card( $checkout_info ) {

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				': @[check_and_save_customer_card] - checking info to create card: ' .
				json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		$custId = null;
		$token = null;
		$issuer_id = null;
		$payment_method_id = null;

		if ( isset( $checkout_info['payer']['id'] ) && ! empty( $checkout_info['payer']['id'] ) ) {
			$custId = $checkout_info['payer']['id'];
		} else {
			return;
		}

		if ( isset( $checkout_info['metadata']['token'] ) && ! empty( $checkout_info['metadata']['token'] ) ) {
			$token = $checkout_info['metadata']['token'];
		} else {
			return;
		}

		if ( isset( $checkout_info['issuer_id'] ) && ! empty( $checkout_info['issuer_id'] ) ) {
			$issuer_id = (integer) ( $checkout_info['issuer_id'] );
		}
		if ( isset( $checkout_info['payment_method_id'] ) && ! empty( $checkout_info['payment_method_id'] ) ) {
			$payment_method_id = $checkout_info['payment_method_id'];
		}

		try {
			$this->mp->create_card_in_customer( $custId, $token, $payment_method_id, $issuer_id );
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_and_save_customer_card] - card creation failed: ' .
					json_encode( array( 'status' => $e->getCode(), 'message' => $e->getMessage() ) )
				);
			}
		}

	}

	/**
	 * Summary: Receive post data and applies a discount based in the received values.
	 * Description: Receive post data and applies a discount based in the received values.
	 */
	public function add_discount_custom() {

		if ( ! isset( $_POST['mercadopago_custom'] ) )
			return;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}

		$mercadopago_custom = $_POST['mercadopago_custom'];
		if ( isset( $mercadopago_custom['discount'] ) && $mercadopago_custom['discount'] != '' &&
			$mercadopago_custom['discount'] > 0 && isset( $mercadopago_custom['coupon_code'] ) &&
			$mercadopago_custom['coupon_code'] != '' &&
			WC()->session->chosen_payment_method == 'woocommerce-mercadopago-custom-module' ) {

			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[add_discount_custom] - custom checkout trying to apply discount...'
				);
			}

			$value = ( $mercadopago_custom['discount'] ) /
				( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
			global $woocommerce;
			if ( apply_filters(
				'wc_mercadopagocustom_module_apply_discount',
				0 < $value, $woocommerce->cart )
			) {
				$woocommerce->cart->add_fee( sprintf(
					__( 'Discount for %s coupon', 'woocommerce-mercadopago-module' ),
					esc_attr( $mercadopago_custom['campaign']
					) ), ( $value * -1 ), true
				);
			}
		}

	}

	// Display the discount in payment method title.
	public function get_payment_method_title_custom( $title, $id ) {

		if ( ! is_checkout() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $title;
		}

		if ( $title != $this->title || $this->gateway_discount == 0 ) {
			return $title;
		}

		$total = (float) WC()->cart->subtotal;
		if ( is_numeric( $this->gateway_discount ) ) {
			if ( $this->gateway_discount >= 0 && $this->gateway_discount < 100 ) {
				$price_percent = $this->gateway_discount / 100;
				if ( $price_percent > 0 ) {
					$title .= ' (' . __( 'Discount Of ', 'woocommerce-mercadopago-module' ) .
						strip_tags( wc_price( $total * $price_percent ) ) . ' )';
				}
			}
		}

		return $title;
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

		if ( empty( $this->public_key ) || empty( $this->access_token ) )
			return false;

		try {

			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->access_token
			);
			$get_request = $this->mp->get(
				'/users/me?access_token=' . $this->access_token
			);

			if ( isset( $get_request['response']['site_id'] ) ) {

				// TODO: revalidate MLU
				if ( $get_request['response']['site_id'] == 'MLU' ) {
					$this->mp = null;
					return false;
				}

				$this->is_test_user = in_array( 'test_user', $get_request['response']['tags'] );
				$this->site_id = $get_request['response']['site_id'];
				$this->collector_id = $get_request['response']['id'];
				$this->country_configs = WC_WooMercadoPago_Module::get_country_config( $this->site_id );

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
			if ( 'yes' == $this->debug ) {
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
		return '<a href="' . esc_url(admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' .
			esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' .
			__( 'WooCommerce &gt; System Status &gt; Logs', 'woocommerce-mercadopago-module' ) . '</a>';
	}

	// Return boolean indicating if currency is supported.
	protected function is_supported_currency() {
		return get_woocommerce_currency() == $this->country_configs['currency'];
	}

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check if we have SSL.
		if ( empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ) {
			if ( empty( $this->sandbox ) && $this->sandbox == 'no' ) {
				return false;
			}
		}
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( WC_WooMercadoPago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}
		// Check if this gateway is enabled and well configured.
		$available = ( 'yes' == $this->settings['enabled'] ) &&
			! empty( $this->public_key ) &&
			! empty( $this->access_token) ;
		return $available;
	}

	public function check_ssl_absence() {
		if ( empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ) {
			if ( 'yes' == $this->settings['enabled'] ) {
				echo '<div class="error"><p><strong>' .
					__( 'Custom Checkout is Inactive', 'woocommerce-mercadopago-module' ) .
					'</strong>: ' .
					sprintf(
						__( 'Your site appears to not have SSL certification. SSL is a pre-requisite because the payment process is made in your server.', 'woocommerce-mercadopago-module' )
					) . '</p></div>';
			}
		}
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if (defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopagocustom_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPagoCustom_Gateway'
		);
	}

	// Notify that public_key and/or access_token are not valid.
	public function credentials_missing_message() {
		echo '<div class="error"><p><strong>' .
			__( 'Custom Checkout is Inactive', 'woocommerce-mercadopago-module' ) .
			'</strong>: ' .
			__( 'Your Mercado Pago credentials Public Key/Access Token appears to be misconfigured.', 'woocommerce-mercadopago-module' ) .
			'</p></div>';
	}

	public function get_order_status( $status_detail ) {
		switch ( $status_detail ) {
			case 'accredited':
				return __( 'Done, your payment was accredited!', 'woocommerce-mercadopago-module' );
			case 'pending_contingency':
				return __( 'We are processing the payment. In less than an hour we will e-mail you the results.', 'woocommerce-mercadopago-module' );
			case 'pending_review_manual':
				return __( 'We are processing the payment. In less than 2 business days we will tell you by e-mail whether it has accredited or we need more information.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_bad_filled_card_number':
				return __( 'Check the card number.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_bad_filled_date':
				return __( 'Check the expiration date.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_bad_filled_other':
				return __( 'Check the information.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_bad_filled_security_code':
				return __( 'Check the security code.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_blacklist':
				return __( 'We could not process your payment.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_call_for_authorize':
				return __( 'You must authorize the payment of your orders.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_card_disabled':
				return __( 'Call your card issuer to activate your card. The phone is on the back of your card.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_card_error':
				return __( 'We could not process your payment.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_duplicated_payment':
				return __( 'You already made a payment for that amount. If you need to repay, use another card or other payment method.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_high_risk':
				return __( 'Your payment was rejected. Choose another payment method. We recommend cash.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_insufficient_amount':
				return __( 'Your payment do not have sufficient funds.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_invalid_installments':
				return __( 'Your payment does not process payments with selected installments.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_max_attempts':
				return __( 'You have reached the limit of allowed attempts. Choose another card or another payment method.', 'woocommerce-mercadopago-module' );
			case 'cc_rejected_other_reason':
				return __( 'This payment method did not process the payment.', 'woocommerce-mercadopago-module' );
			default:
				return __( 'This payment method did not process the payment.', 'woocommerce-mercadopago-module' );
		}
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
	public function process_http_request() {
		@ob_clean();
		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_http_request] - Received _get content: ' .
				json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}
		if ( isset( $_GET['coupon_id'] ) && $_GET['coupon_id'] != '' ) {
			// Process coupon evaluations.
			if ( isset( $_GET['payer'] ) && $_GET['payer'] != '' ) {
				$logged_user_email = $_GET['payer'];
				$coupon_id = $_GET['coupon_id'];
			if ( 'yes' == $this->sandbox )
				$this->mp->sandbox_mode( true );
			else
				$this->mp->sandbox_mode( false );
				$response = $this->mp->check_discount_campaigns(
			 	$_GET['amount'],
					$logged_user_email,
					$coupon_id
				);
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: application/json' );
				echo json_encode( $response );
			} else {
				$obj = new stdClass();
				$obj->status = 404;
				$obj->response = array(
					'message' =>
						__( 'Please, inform your email in billing address to use this feature', 'woocommerce-mercadopago-module' ),
					'error' => 'payer_not_found',
					'status' => 404,
					'cause' => array()
				);
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: application/json' );
				echo json_encode( $obj );
			}
			exit( 0 );
		} else {
			// Process IPN messages.
			$data = $this->check_ipn_request_is_valid( $_GET );
			if ( $data ) {
				header( 'HTTP/1.1 200 OK' );
				do_action( 'valid_mercadopagocustom_ipn_request', $data );
			}
		}
	}

	/**
	 * Summary: Get received data from IPN and checks if its a merchant_order or a payment.
	 * Description: If we have these information, we return data to be processed by
	 * successful_request function.
	 * @return boolean indicating if it was successfuly processed.
	 */
	public function check_ipn_request_is_valid( $data ) {

		if ( ! isset( $data['data_id'] ) || ! isset( $data['type'] ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_ipn_request_is_valid] - data_id or type not set: ' .
					json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
			// At least, check if its a v0 ipn.
			if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[check_ipn_request_is_valid] - Mercado Pago Request failure: ' .
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
			// Get the payment reported by the IPN.
			if ( $data['type'] == 'payment' ) {
				$access_token = array( 'access_token' => $this->mp->get_access_token() );
				$payment_info = $this->mp->get(
					'/v1/payments/' . $data['data_id'], $access_token, false
				);
				if ( ! is_wp_error( $payment_info ) &&
					( $payment_info['status'] == 200 || $payment_info['status'] == 201 ) ) {
					return $payment_info['response'];
				} else {
					if ( 'yes' == $this->debug) {
						$this->log->add(
							$this->id,
							'[check_ipn_request_is_valid] - error when processing received data: ' .
							json_encode( $payment_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
						);
					}
					return false;
				}
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_ipn_request_is_valid] - MercadoPagoException: ' .
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

		// Get the order and check its presence.
		$order_key = $data['external_reference'];
		if ( empty( $order_key ) ) {
			return;
		}
		$id = (int) str_replace( $this->invoice_prefix, '', $order_key );
		$order = wc_get_order( $id );

		// Check if order exists.
		if ( ! $order ) {
			return;
		}

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_id' ) ) {
			$order_id = $order->get_id();
		} else {
			$order_id = $order->id;
		}

		// Check if we have the correct order.
		if ( $order_id !== $id ) {
			return;
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[successful_request] - updating metadata and status with data: ' .
				json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		// Here, we process the status... this is the business rules!
		// Reference: https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/
		$status = isset( $data['status'] ) ? $data['status'] : 'pending';
		$total_paid = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
		$total_refund = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
		$total = $data['transaction_amount'];

		if ( method_exists( $order, 'update_meta_data' ) ) {

			if ( ! empty( $data['payer']['email'] ) ) {
				$order->update_meta_data( __( 'Payer email', 'woocommerce-mercadopago-module' ), $data['payer']['email'] );
			}

			if ( ! empty( $data['payment_type_id'] ) ) {
				$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago-module' ), $data['payment_type_id'] );
			}

			$payment_id = $data['id'];

			$order->update_meta_data( 'Mercado Pago - Payment ' . $payment_id,
				'Mercado Pago - Payment ' . $payment_id,
				'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
				']/[Amount ' . $total .
				']/[Paid ' . $total_paid .
				']/[Refund ' . $total_refund . ']'
			);

			$order->update_meta_data( '_Mercado_Pago_Payment_IDs', $payment_id );
			$order->save();

		} else {

			if ( ! empty( $data['payer']['email'] ) ) {
				update_post_meta(
					$order_id,
					__( 'Payer email', 'woocommerce-mercadopago-module' ),
					$data['payer']['email']
				);
			}
			if ( ! empty( $data['payment_type_id'] ) ) {
				update_post_meta(
					$order_id,
					__( 'Payment type', 'woocommerce-mercadopago-module' ),
					$data['payment_type_id']
				);
			}
			$payment_id = $data['id'];
			update_post_meta(
				$order_id,
				'Mercado Pago - Payment ' . $payment_id,
				'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
				']/[Amount ' . $total .
				']/[Paid ' . $total_paid .
				']/[Refund ' . $total_refund . ']'
			);
			update_post_meta(
				$order_id,
				'_Mercado_Pago_Payment_IDs',
				$payment_id
			);
		}

		// Switch the status and update in WooCommerce
		switch ( $status ) {
			case 'approved':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago-module' )
				);
				$this->check_and_save_customer_card( $data );
				$order->payment_complete();
				break;
			case 'pending':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago-module' )
				);
				break;
			case 'in_process':
				$order->update_status(
					'on-hold',
					'Mercado Pago: ' . __( 'Payment under review.', 'woocommerce-mercadopago-module' )
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
	}

}

new WC_WooMercadoPagoCustom_Gateway( true );
