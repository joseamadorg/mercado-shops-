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
 * Description: This class implements Mercado Pago ticket payment method.
 * @since 2.0.0
 */
class WC_WooMercadoPagoTicket_Gateway extends WC_Payment_Gateway {

	public function __construct( $is_instance = false ) {

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
		$this->supports = array( 'products', 'refunds' );
		$this->id = 'woocommerce-mercadopago-ticket-module';
		$this->domain = get_site_url() . '/index.php';
		$this->method_title = __( 'Mercado Pago - Ticket', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url(
				'images/mplogo.png',
				plugin_dir_path( __FILE__ )
			) . '"><br><br>' . '<strong>' .
			__( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ) .
			'</strong>';

		// Fields used in Mercado Pago Module configuration page.
		$this->access_token = $this->get_option( 'access_token' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->coupon_mode = $this->get_option( 'coupon_mode' );
		$this->category_id = $this->get_option( 'category_id' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->currency_conversion = $this->get_option( 'currency_conversion', false );
		$this->gateway_discount = $this->get_option( 'gateway_discount', 0 );
		$this->reduce_stock_on_order_gen = $this->get_option( 'reduce_stock_on_order_gen', false );
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
			'woocommerce_api_wc_woomercadopagoticket_gateway',
			array( $this, 'process_http_request' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopagoticket_ipn_request',
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
			array( $this, 'ticket_checkout_scripts' )
		);
		// Apply the discounts.
		add_action(
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_discount_ticket' ), 10
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_ticket' ), 10, 2
		);
		// Customizes thank you page.
		add_filter(
			'woocommerce_thankyou_order_received_text',
			array( $this, 'show_ticket_button' ), 10, 2
		);

		if ( ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] == 'yes' ) {
			if ( $is_instance ) {
				if ( empty( $this->access_token ) ) {
					// Verify if access token is empty.
					add_action( 'admin_notices', array( $this, 'credentials_missing_message' ) );
				} else {
					// Verify if SSL is supported.
					add_action( 'admin_notices', array( $this, 'check_ssl_absence' ) );
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
					'label' => __( 'Enable Ticket Payment Method', 'woocommerce-mercadopago-module' ),
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

		// Trigger API to get payment methods and site_id, also validates access_token.
		if ( $this->validate_credentials() ) {
			// checking the currency
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
				'label' => __( 'Enable Ticket Payment Method', 'woocommerce-mercadopago-module' ),
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
					'<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPagoTicket_Gateway' ) . '</code>.'
				)
			),
			'checkout_options_title' => array(
				'title' => __( 'Ticket Options', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => ''
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-mercadopago-module' ),
				'type' => 'text',
				'description' =>
					__( 'Title shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Mercado Pago - Ticket', 'woocommerce-mercadopago-module' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-mercadopago-module' ),
				'type' => 'textarea',
				'description' =>
					__( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
				'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' )
			),
			'coupon_mode' => array(
				'title' => __( 'Coupons', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable coupons of discounts', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' =>
					__( 'If there is a Mercado Pago campaign, allow your store to give discounts to customers.', 'woocommerce-mercadopago-module' )
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
			'reduce_stock_on_order_gen' => array(
				'title' => __( 'Stock Reduce', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' =>
					__( 'Reduce Stock in Order Generation', 'woocommerce-mercadopago-module' ),
				'default' => 'no',
				'description' => __( 'Enable this to reduce the stock on order creation. Disable this to reduce <strong>after</strong> the payment approval.', 'woocommerce-mercadopago-module' )
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

		if ( ! empty( $this->settings['access_token'] ) ) {
			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->settings['access_token']
			);
		} else {
			$this->mp = null;
		}

		// analytics
		if ( $this->mp != null && ! $this->is_test_user ) {
			$infra_data = WC_WooMercadoPago_Module::get_common_settings();
			$infra_data['checkout_custom_ticket'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$infra_data['checkout_custom_ticket_coupon'] = ( $this->settings['coupon_mode'] == 'yes' ? 'true' : 'false' );
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
			$used_gateway = $order->get_meta( '_used_gateway' );
			$payments = $order->get_meta( '_Mercado_Pago_Payment_IDs' );
		} else {
			$used_gateway = get_post_meta( $order->id, '_used_gateway', true );
			$payments = get_post_meta( $order->id, '_Mercado_Pago_Payment_IDs',	true );
		}

		if ( $used_gateway != 'WC_WooMercadoPago_Gateway' ) {
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

		$client_id = WC_WooMercadoPago_Module::get_client_id( $this->get_option( 'access_token' ) );

		if ( ! empty( $client_id ) && ! $this->is_test_user ) {

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
				MA.setToken( '<?php echo $client_id; ?>' );
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

		$access_token = WC_WooMercadoPago_Module::get_client_id( $this->get_option( 'access_token' ) );

		if ( ! empty( $access_token ) && ! $this->is_test_user ) {

			if ( get_post_meta( $order_id, '_used_gateway', true ) != 'WC_WooMercadoPagoTicket_Gateway' ) {
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
				MA.setToken( ' . $access_token . ' );
				MA.setPaymentType("ticket");
				MA.setCheckoutType("custom");
				MA.put();
			</script>';

		}

	}

	public function show_ticket_button( $thankyoutext, $order ) {
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_meta' ) ) {
			$used_gateway = $order->get_meta( '_used_gateway' );
			$transaction_details = $order->get_meta( '_transaction_details_ticket' );
		} else {
			$used_gateway = get_post_meta( $order->id, '_used_gateway', true );
			$transaction_details = get_post_meta( $order->id, '_transaction_details_ticket', true );
		}

		// Prevent showing ticket button for other payment methods.
		if ( empty( $transaction_details ) || $used_gateway != 'WC_WooMercadoPagoTicket_Gateway' ) {
			return;
		}

		$html = '<p>' .
			__( 'Thank you for your order. Please, pay the ticket to get your order approved.', 'woocommerce-mercadopago-module' ) .
		'</p>';
		$html .= '<a id="submit-payment" target="_blank" href="' .
			$transaction_details . '" class="button alt"' .
			' style="font-size:1.25rem; width:75%; height:48px; line-height:24px; text-align:center;">' .
			__( 'Print the Ticket', 'woocommerce-mercadopago-module' ) .
			'</a> ';
		$added_text = '<p>' . $html . '</p>';
		return $added_text;
	}

	public function ticket_checkout_scripts() {
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
			'payment_methods' => $this->payment_methods,
			'site_id' => $this->site_id,
			'images_path' => plugins_url( 'images/', plugin_dir_path( __FILE__ ) ),
			'amount' => $amount * ( ( float ) $this->currency_ratio > 0 ? ( float ) $this->currency_ratio : 1 ),
			'coupon_mode' => $this->coupon_mode,
			'is_currency_conversion' => $this->currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency' => $this->country_configs['currency'],
			'discount_action_url' => $this->domain .
				'/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPagoTicket_Gateway',
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
					'label_choose' => __( 'Choose', 'woocommerce-mercadopago-module' ),
					'issuer_selection' =>
						__( 'Please, select the ticket issuer of your preference.', 'woocommerce-mercadopago-module' ),
					'payment_instructions' =>
						__( 'Click "Place order" button. The ticket will be generated and you will be redirected to print it.', 'woocommerce-mercadopago-module' ),
					'ticket_note' =>
						__( 'Important: The order will be confirmed only after the payment approval.', 'woocommerce-mercadopago-module' )
	 			)
			)
		);

		// Find logged user.
		try {
			$logged_user_email = null;
			$parameters['payer_email'] = null;
			if ( wp_get_current_user()->ID != 0 ) {
				$logged_user_email = wp_get_current_user()->user_email;
			}
			if ( isset( $logged_user_email ) ) {
				if ( isset( $logged_user_email ) ) {
					$parameters['payer_email'] = $logged_user_email;
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

		wc_get_template(
			'ticket/ticket-form.php',
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

		if ( ! isset( $_POST['mercadopago_ticket'] ) ) {
			return;
		}

		update_post_meta( $order_id, '_used_gateway', 'WC_WooMercadoPagoTicket_Gateway' );

		$order = wc_get_order( $order_id );
		$mercadopago_ticket = $_POST['mercadopago_ticket'];

		// We have got parameters from checkout page, now its time to charge the card.
		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_payment] - Received [$_POST] from customer front-end page: ' .
				json_encode( $_POST, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
		}

		if ( isset( $mercadopago_ticket['amount'] ) && ! empty( $mercadopago_ticket['amount'] ) &&
			isset( $mercadopago_ticket['paymentMethodId'] ) && ! empty( $mercadopago_ticket['paymentMethodId'] ) ) {

			return self::create_url( $order, $mercadopago_ticket );

		} else {
			// process when fields are imcomplete.
			wc_add_notice(
				'<p>' .
					__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago-module' ) .
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
	private function build_payment_preference( $order, $ticket_checkout ) {

		// A string to register items (workaround to deal with API problem that shows only first item).
		$list_of_items = array();
		$order_total = 0;
		$discount_amount_of_items = 0;

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
								$discount_amount_of_items += $discount;
							}
						}
					}

					// Remove decimals if MCO/MLC
					if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
						$unit_price = floor( $unit_price );
						$discount_amount_of_items = floor( $discount_amount_of_items );
					}

					$order_total += $unit_price;

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
			$order_total += $ship_cost;
	 		$item = array(
	 			'id' => 2147483647,
				'title' => sanitize_file_name( $order->get_shipping_to_display() ),
				'description' => __( 'Shipping service used by store', 'woocommerce-mercadopago-module' ),
				'category_id' => $this->store_categories_id[$this->category_id],
				'quantity' => 1,
				'unit_price' => floor( $ship_cost * 100 ) / 100
			);
	 		$items[] = $item;
		}

		// Discounts features.
		if ( isset( $ticket_checkout['discount'] ) && $ticket_checkout['discount'] != '' &&
			$ticket_checkout['discount'] > 0 && isset( $ticket_checkout['coupon_code'] ) &&
			$ticket_checkout['coupon_code'] != '' &&
			WC()->session->chosen_payment_method == 'woocommerce-mercadopago-ticket-module' ) {

			// Remove decimals if MCO/MLC
			if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
				$ticket_checkout['discount'] = floor( $ticket_checkout['discount'] );
			}

		 	$item = array(
		 		'id' => 2147483646,
				'title' => __( 'Discount', 'woocommerce-mercadopago-module' ),
				'description' => __( 'Discount provided by store', 'woocommerce-mercadopago-module' ),
				'category_id' => $this->store_categories_id[$this->category_id],
				'quantity' => 1,
				'unit_price' => -( ( float ) $ticket_checkout['discount'] )
			);
	 		$items[] = $item;
		}

		if ( method_exists( $order, 'get_id' ) ) {
			// Build additional information from the customer data.
			$payer_additional_info = array(
				'first_name' => html_entity_decode( $order->get_billing_first_name() ),
				'last_name' => html_entity_decode( $order->get_billing_last_name() ),
				//'registration_date' =>
				'phone' => array(
					//'area_code' =>
					'number' => $order->get_billing_phone()
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
				'transaction_amount' => floor( ( ( float ) $order_total ) * 100 ) / 100 - $discount_amount_of_items,
				'description' => implode( ', ', $list_of_items ),
				'payment_method_id' => $ticket_checkout['paymentMethodId'],
				'payer' => array(
					'email' => $order->get_billing_email()
				),
				'external_reference' => $this->invoice_prefix . $order->get_id(),
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
				'transaction_amount' => floor( ( ( float ) $order_total ) * 100 ) / 100 - $discount_amount_of_items,
				'description' => implode( ', ', $list_of_items ),
				'payment_method_id' => $ticket_checkout['paymentMethodId'],
				'payer' => array(
					'email' => $order->billing_email
				),
				'external_reference' => $this->invoice_prefix . $order->id,
				'additional_info' => array(
					'items' => $items,
					'payer' => $payer_additional_info,
					'shipments' => $shipments
				)
			);
		}

		// Do not set IPN url if it is a localhost.
		if ( ! strrpos( $this->domain, 'localhost' ) ) {
		 	$preferences['notification_url'] = WC_WooMercadoPago_Module::workaround_ampersand_bug(
				WC()->api_request_url( 'WC_WooMercadoPagoTicket_Gateway' )
			);
		}

		// Discounts features.
		if ( isset( $ticket_checkout['discount'] ) && $ticket_checkout['discount'] != '' &&
	 		$ticket_checkout['discount'] > 0 && isset( $ticket_checkout['coupon_code'] ) &&
	 		$ticket_checkout['coupon_code'] != '' &&
	 		WC()->session->chosen_payment_method == 'woocommerce-mercadopago-ticket-module' ) {

			$preferences['campaign_id'] = (int) $ticket_checkout['campaign_id'];
		 	$preferences['coupon_amount'] = ( (float) $ticket_checkout['discount'] );
		 	$preferences['coupon_code'] = strtoupper( $ticket_checkout['coupon_code'] );
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
			'woocommerce_mercadopago_module_ticket_preferences',
	 		$preferences, $order
		);
		return $preferences;
	}

	// --------------------------------------------------

	protected function create_url( $order, $ticket_checkout ) {

		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->build_payment_preference( $order, $ticket_checkout );

		$this->mp->sandbox_mode( false );

		// Create order preferences with Mercado Pago API request.
		try {
			$ticket_info = $this->mp->create_payment( json_encode( $preferences ) );
			if ( $ticket_info['status'] < 200 || $ticket_info['status'] >= 300 ) {
				// Mercado Pago trowed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - mercado pago gave error, payment creation failed with error: ' .
						$ticket_info['response']['message'] );
				}
				return false;
			} elseif ( is_wp_error( $ticket_info ) ) {
				// WordPress throwed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - wordpress gave error, payment creation failed with error: ' .
						$ticket_info['response']['message'] );
				}
				return false;
			} else {
				// Obtain the URL.
				$response = $ticket_info['response'];
				if ( array_key_exists( 'status', $response ) ) {
					if ( $response['status'] == 'pending' ) {
						if ( $response['status_detail'] == 'pending_waiting_payment' ) {
							WC()->cart->empty_cart();
							if ( $this->reduce_stock_on_order_gen == 'yes' ) {
								$order->reduce_order_stock();
							}
							/*$html = '<p></p><p>' . wordwrap(
								__( 'Thank you for your order. Please, pay the ticket to get your order approved.', 'woocommerce-mercadopago-module' ),
								60, '<br>'
							) . '</p>';
							$html .= '<a id="submit-payment" target="_blank" href="' .
								$response['transaction_details']['external_resource_url'] .
								'" class="button alt">' .
								__( 'Print the Ticket', 'woocommerce-mercadopago-module' ) .
								'</a> ';
							wc_add_notice( '<p>' . $html . '</p>', 'notice' );*/
							
							// WooCommerce 3.0 or later.
							if ( method_exists( $order, 'update_meta_data' ) ) {
								$order->update_meta_data( '_transaction_details_ticket', $response['transaction_details']['external_resource_url'] );
								$order->save();
							} else {
								update_post_meta(
									$order->id,
									'_transaction_details_ticket',
									$response['transaction_details']['external_resource_url']
								);
							}

							$order->add_order_note(
								'Mercado Pago: ' .
								__( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago-module' )
							);
							$order->add_order_note(
								'Mercado Pago: ' .
								__( 'To reprint the ticket click ', 'woocommerce-mercadopago-module' ) .
								'<a target="_blank" href="' .
								$response['transaction_details']['external_resource_url'] . '">' .
								__( 'here', 'woocommerce-mercadopago-module' ) .
								'</a>', 1, false
							);

							/*return array(
								'result' => 'success',
								'redirect' => $order->get_checkout_payment_url( true )
							);*/
							return array(
								'result' => 'success',
								'redirect' => $order->get_checkout_order_received_url()
							);
						}
					}
				}
				return false;
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
		}
		return false;
	}

	/**
	* Summary: Receive post data and applies a discount based in the received values.
	* Description: Receive post data and applies a discount based in the received values.
	*/
	public function add_discount_ticket() {

		if ( ! isset( $_POST['mercadopago_ticket'] ) )
			return;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}

		$mercadopago_ticket = $_POST['mercadopago_ticket'];
		if ( isset( $mercadopago_ticket['discount'] ) && $mercadopago_ticket['discount'] != '' &&
			$mercadopago_ticket['discount'] > 0 && isset( $mercadopago_ticket['coupon_code'] ) &&
			$mercadopago_ticket['coupon_code'] != '' &&
			WC()->session->chosen_payment_method == 'woocommerce-mercadopago-ticket-module' ) {

			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[add_discount_ticket] - ticket trying to apply discount...'
				);
			}

			$value = ( $mercadopago_ticket['discount'] ) /
				( ( float ) $this->currency_ratio > 0 ? ( float ) $this->currency_ratio : 1 );
			global $woocommerce;
			if ( apply_filters(
				'wc_mercadopagoticket_module_apply_discount',
				0 < $value, $woocommerce->cart )
			) {
				$woocommerce->cart->add_fee( sprintf(
					__( 'Discount for %s coupon', 'woocommerce-mercadopago-module' ),
					esc_attr( $mercadopago_ticket['campaign']
					) ), ( $value * -1 ), true
				);
			}
		}

	}

	// Display the discount in payment method title.
	public function get_payment_method_title_ticket( $title, $id ) {

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

		if ( empty( $this->access_token ) )
			return false;

		try {

			$this->mp = new MP(
				WC_WooMercadoPago_Module::get_module_version(),
				$this->access_token
			);
			$get_request = $this->mp->get( '/users/me?access_token=' . $this->access_token );

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

				// Get ticket payments.
				$payments = $this->mp->get( '/v1/payment_methods/?access_token=' . $this->access_token );
				foreach ( $payments['response'] as $payment ) {
					if ( isset( $payment['payment_type_id'] ) ) {
						if ( $payment['payment_type_id'] != 'account_money' &&
							$payment['payment_type_id'] != 'credit_card' &&
							$payment['payment_type_id'] != 'debit_card' &&
							$payment['payment_type_id'] != 'prepaid_card' ) {

							array_push( $this->payment_methods, $payment );
						}
					}
				}

				// Check if there are available payments with ticket.
				if ( count( $this->payment_methods ) == 0 ) {
					return false;
				}

				// Check for auto converstion of currency.
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
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check if we have SSL.
		if ( empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ) {
			return false;
		}
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( WC_WooMercadoPago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}
		// Check if this gateway is enabled and well configured.
		$available = ( 'yes' == $this->settings['enabled'] ) && ! empty( $this->access_token );
		return $available;
	}

	public function check_ssl_absence() {
		if ( empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ) {
			if ( 'yes' == $this->settings['enabled'] ) {
				echo '<div class="error"><p><strong>' .
					__( 'Ticket is Inactive', 'woocommerce-mercadopago-module' ) .
					'</strong>: ' .
					sprintf(
						__( 'Your site appears to not have SSL certification. SSL is a pre-requisite because the payment process is made in your server.', 'woocommerce-mercadopago-module' )
					) . '</p></div>';
			}
		}
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopagoticket_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPagoTicket_Gateway'
		);
	}

	// Notify that access_token are not valid.
	public function credentials_missing_message() {
		echo '<div class="error"><p><strong>' .
			__( 'Ticket is Inactive', 'woocommerce-mercadopago-module' ) .
			'</strong>: ' .
			__( 'Your Mercado Pago credentials Access Token appears to be misconfigured.', 'woocommerce-mercadopago-module' ) .
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
			// process coupon evaluations.
			if ( isset( $_GET['payer'] ) && $_GET['payer'] != '' ) {
				$logged_user_email = $_GET['payer'];
				$coupon_id = $_GET['coupon_id'];
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
			// process IPN messages.
			$data = $this->check_ipn_request_is_valid( $_GET );
			if ( $data ) {
				header( 'HTTP/1.1 200 OK' );
				do_action( 'valid_mercadopagoticket_ipn_request', $data );
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
			// at least, check if its a v0 ipn.
			if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[check_ipn_response] - Mercado Pago Request Failure: ' .
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

		$this->mp->sandbox_mode( false );

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
					if ( 'yes' == $this->debug ) {
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

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data(  '_used_gateway', 'WC_WooMercadoPagoTicket_Gateway' );

			if ( ! empty( $data['payer']['email'] ) ) {
				$order->update_meta_data( __( 'Payer email', 'woocommerce-mercadopago-module' ), $data['payer']['email'] );
			}
			if ( ! empty( $data['payment_type_id'] ) ) {
				$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago-module' ), $data['payment_type_id'] );
			}
			$payment_id = $data['id'];
			$order->update_meta_data(
				'Mercado Pago - Payment ' . $payment_id,
				'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
				']/[Amount ' . $total .
				']/[Paid ' . $total_paid .
				']/[Refund ' . $total_refund . ']'
			);
			$order->update_meta_data( '_Mercado_Pago_Payment_IDs', $payment_id );

			$order->save();
		} else {
			update_post_meta( $order->id, '_used_gateway', 'WC_WooMercadoPagoTicket_Gateway' );

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

		// Switch the status and update in WooCommerce.
		switch ( $status ) {
			case 'approved':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago-module' )
				);
				if ( $this->reduce_stock_on_order_gen == 'no' ) {
					$order->payment_complete();
				} else {
					$order->update_status( 'processing' );
				}
				break;
			case 'pending':
				// decrease stock if not yet decreased and order not exists.
				$notes = $order->get_customer_order_notes();
				$has_note = false;
				if ( sizeof( $notes ) > 1 ) {
					$has_note = true;
					break;
				}
				if ( ! $has_note ) {
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'Waiting for the ticket payment.', 'woocommerce-mercadopago-module' )
					);
					$order->add_order_note(
						'Mercado Pago: ' .
						__( 'Waiting for the ticket payment.', 'woocommerce-mercadopago-module' ),
						1, false
					);
				}
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

new WC_WooMercadoPagoTicket_Gateway( true );
