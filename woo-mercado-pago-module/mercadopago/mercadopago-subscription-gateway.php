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
 * Description: This class implements Mercado Pago Subscription checkout.
 * @since 2.2.0
 */
class WC_WooMercadoPagoSubscription_Gateway extends WC_Payment_Gateway {

	public function __construct() {

		// Mercado Pago fields.
		$this->mp = null;
		$this->site_id = null;
		$this->currency_ratio = -1;
		$this->is_test_user = false;

		// Auxiliary fields.
		$this->currency_message = '';
		$this->payment_methods = array();
		$this->country_configs = array();

		// WooCommerce fields.
		//$this->supports = array( 'products', 'refunds' );
		$this->id = 'woocommerce-mercadopago-subscription-module';
		$this->domain = get_site_url() . '/index.php';
		$this->method_title = __( 'Mercado Pago - Subscription', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url(
				'images/mplogo.png',
				plugin_dir_path( __FILE__ )
			) . '"><br><br>' . '<strong>' .
			__( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ) .
			'</strong>';

		// Fields used in Mercado Pago Module configuration page.
		$this->client_id = $this->get_option( 'client_id' );
		$this->client_secret = $this->get_option( 'client_secret' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->method = $this->get_option( 'method', 'iframe' );
		$this->iframe_width = $this->get_option( 'iframe_width', 640 );
		$this->iframe_height = $this->get_option( 'iframe_height', 800 );
		$this->success_url = $this->get_option( 'success_url', '' );
		$this->currency_conversion = $this->get_option( 'currency_conversion', false );
		$this->gateway_discount = 0;
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
			'woocommerce_api_wc_woomercadopagosubscription_gateway',
			array( $this, 'check_ipn_response' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopagosubscription_ipn_request',
			array( $this, 'successful_request' )
		);
		// process the cancel order meta box order action
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// Used by WordPress to render the custom checkout page.
		add_action(
			'woocommerce_receipt_' . $this->id,
			array( $this, 'receipt_page' )
		);
		// Used to fix CSS in some older WordPress/WooCommerce versions.
		add_action(
			'wp_head',
			array( $this, 'css' )
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		// Scripts for order configuration.
		add_action(
			'woocommerce_after_checkout_form',
			array( $this, 'add_checkout_script' )
		);
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_subscription' ), 10, 2
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
					'label' => __( 'Enable Subscription', 'woocommerce-mercadopago-module' ),
					'default' => 'no'
				)
			);
			return;
		}

		$api_secret_locale = sprintf(
			'<a href="https://www.mercadopago.com/mla/account/credentials?type=basic" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com/mlb/account/credentials?type=basic" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com/mlm/account/credentials?type=basic" target="_blank">%s</a>, ',
			__( 'Argentine', 'woocommerce-mercadopago-module' ),
			__( 'Brazil', 'woocommerce-mercadopago-module' ),
			__( 'or', 'woocommerce-mercadopago-module' ),
			__( 'Mexico', 'woocommerce-mercadopago-module' )
		);

		$ipn_locale = sprintf(
			'<a href="https://www.mercadopago.com.ar/ipn-notifications" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com.br/ipn-notifications" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com.mx/ipn-notifications" target="_blank">%s</a>, ',
			__( 'Argentine', 'woocommerce-mercadopago-module' ),
			__( 'Brazil', 'woocommerce-mercadopago-module' ),
			__( 'or', 'woocommerce-mercadopago-module' ),
			__( 'Mexico', 'woocommerce-mercadopago-module' )
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
		} else {
			array_push( $this->payment_methods, 'n/d' );
			$this->credentials_message = WC_WooMercadoPago_Module::build_invalid_credentials_msg();
		}

		// Checks validity of iFrame width/height fields.
		if ( ! is_numeric( $this->iframe_width ) ) {
			$this->iframe_width_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
		} else {
			$this->iframe_width_desc =
				__( 'If your integration method is iFrame, please inform the payment iFrame width.', 'woocommerce-mercadopago-module' );
		}
		if ( ! is_numeric( $this->iframe_height ) ) {
			$this->iframe_height_desc = '<img width="12" height="12" src="' .
				plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
				__( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
		} else {
			$this->iframe_height_desc =
				__( 'If your integration method is iFrame, please inform the payment iFrame height.', 'woocommerce-mercadopago-module' );
		}

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Subscription', 'woocommerce-mercadopago-module' ),
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
					__( 'For this solution, you need to configure your IPN URL. You can access it in your account for your specific country in:', 'woocommerce-mercadopago-module' ) .
					'<br>' . ' %s.', $ipn_locale . '. ' . sprintf(
					__( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) .
					':<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPagoSubscription_Gateway' ) . '</code>' )
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
				'default' => __( 'Subscribe with Mercado Pago', 'woocommerce-mercadopago-module' )
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
			$infra_data['checkout_subscription'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$response = $this->mp->analytics_save_settings( $infra_data );
			if ( 'yes' == $this->debug) {
				$this->log->add(
					$this->id,
					'[custom_process_admin_options] - analytics response: ' .
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
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_meta' ) ) {
			$preapproval = $order->get_meta( 'Mercado Pago Pre-Approval' );
		} else {
			$preapproval = get_post_meta( $order->id, 'Mercado Pago Pre-Approval',	true );
		}

		if ( $used_gateway != 'WC_WooMercadoPagoSubscription_Gateway' ) {
			return;
		}

		$preapproval = explode( '/', $preapproval );
		$preapproval_id = explode( ' ', substr( $preapproval[0], 1, -1 ) )[1];

		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id,
				'[process_cancel_order_meta_box_actions] - cancelling preapproval for ' . $preapproval_id
			);
		}

		if ( $this->mp != null && ! empty( $preapproval_id ) ) {
			$response = $this->mp->cancel_preapproval_payment( $preapproval_id );
			$message = $response['response']['message'];
			$status = $response['status'];
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_cancel_order_meta_box_actions] - cancel preapproval of id ' . $preapproval_id .
					' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[process_cancel_order_meta_box_actions] - no preapproval or credentials invalid'
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
		// subscription checkout
		if ( $description = $this->get_description() ) {
			echo wpautop(wptexturize( $description ) );
		}
		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form();
		}
	}

	public function add_checkout_script() {

		$client_id = $this->get_option( 'client_id' );

		if ( ! empty( $client_id ) ) {

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

		$client_id = $this->get_option( 'client_id' );

		if ( ! empty( $client_id ) ) {

			$order = wc_get_order( $order_id );
			if ( 'woocommerce-mercadopago-subscription-module' !== $order->get_payment_method() ) {
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
				MA.setToken( ' . $client_id . ' );
				MA.setPaymentType("subscription");
				MA.setCheckoutType("subscription");
				MA.put();
			</script>';

		}

	}

	/**
	 * Summary: Handle the payment and processing the order.
	 * Description: First step occurs when the customer selects Mercado Pago and proceed to checkout.
	 * This method verify which integration method was selected and makes the build for the checkout
	 * URL.
	 * @return an array containing the result of the processment and the URL to redirect.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->save();
		}

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
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
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

		$order = wc_get_order( $order_id );
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
					'</a><style type="text/css">#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important; height: 590px !important; }</style>';
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
	 * Summary: Build Mercado Pago preapproval.
	 * Description: Create Mercado Pago preapproval structure and get init_point URL based in the order options
	 * from the cart.
	 * @return the preapproval structure.
	 */
	public function build_preapproval( $order ) {

		// Here we build the array that contains ordered items, from customer cart
		$preapproval = null;

		$arr = $order->get_items();
		foreach ( $order->get_items() as $item ) {
			if ( $item['qty'] ) {
				$product = new WC_product( $item['product_id'] );
				
				// WooCommerce 3.0 or later.
				if ( method_exists( $product, 'get_name' ) ) {
					$product_title = WC_WooMercadoPago_Module::utf8_ansi(
						$product->get_name()
					);
				} else {
					$product_title = WC_WooMercadoPago_Module::utf8_ansi(
						$product->post->post_title
					);
				}

				$unit_price = floor( ( (float) $item['line_total'] + (float) $item['line_tax'] ) *
					( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100;
				
				// Add shipment cost
				$unit_price += ( (float) $order->get_total_shipping() + (float) $order->get_shipping_tax() ) *
					( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
				
				// Remove decimals if MCO/MLC
				if ( $this->site_id == 'MCO' || $this->site_id == 'MLC' ) {
					$unit_price = floor( $unit_price );
				}
				
				// Get the custom fields
				$frequency = get_post_meta( $item['product_id'], '_mp_recurring_frequency', true );
				$frequency_type = get_post_meta( $item['product_id'], '_mp_recurring_frequency_type', true );
				$start_date = get_post_meta( $item['product_id'], '_mp_recurring_start_date', true );
				$end_date = get_post_meta( $item['product_id'], '_mp_recurring_end_date', true );
				
				// WooCommerce 3.0 or later.
				if ( method_exists( $order, 'get_id' ) ) {
					// Creates the pre-approval structure
					$preapproval = array(
						'payer_email' => $order->get_billing_email(),
						'back_url' => ( empty( $this->success_url ) ?
							WC_WooMercadoPago_Module::workaround_ampersand_bug(
								esc_url( $this->get_return_url( $order ) )
							) : $this->success_url
						),
						'reason' => $product_title,
						'external_reference' => $this->invoice_prefix . $order->get_id(),
						'auto_recurring' => array(
							'frequency' => $frequency,
							'frequency_type' => $frequency_type,
							'transaction_amount' => $unit_price,
							'currency_id' => $this->country_configs['currency']
						)
					);
				} else {
					// Creates the pre-approval structure
					$preapproval = array(
						'payer_email' => $order->billing_email,
						'back_url' => ( empty( $this->success_url ) ?
							WC_WooMercadoPago_Module::workaround_ampersand_bug(
								esc_url( $this->get_return_url( $order) )
							) : $this->success_url
						),
						'reason' => $product_title,
						'external_reference' => $this->invoice_prefix . $order->id,
						'auto_recurring' => array(
							'frequency' => $frequency,
							'frequency_type' => $frequency_type,
							'transaction_amount' => $unit_price,
							'currency_id' => $this->country_configs['currency']
						)
					);
				}

				if ( isset( $start_date ) && ! empty( $start_date ) )
					$preapproval['auto_recurring']['start_date'] = $start_date . 'T16:00:00.000-03:00';
				if ( isset( $end_date ) && ! empty( $end_date ) )
					$preapproval['auto_recurring']['end_date'] = $end_date . 'T16:00:00.000-03:00';
				// Do not set IPN url if it is a localhost.
				if ( ! strrpos( $this->domain, 'localhost' ) ) {
					$preapproval['notification_url'] = WC_WooMercadoPago_Module::workaround_ampersand_bug(
						WC()->api_request_url( 'WC_WooMercadoPagoSubscription_Gateway' )
					);
				}
				// Set sponsor ID.
				if ( ! $this->is_test_user ) {
					$preapproval['sponsor_id'] = $this->country_configs['sponsor_id'];
				}
				// Log debug message.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[build_preapproval] - preapproval created with following structure: ' .
						json_encode( $preapproval, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
				}
			}
		}

		return $preapproval;
	}

	// --------------------------------------------------

	protected function create_url( $order ) {

		$this->mp->sandbox_mode( false );

		// Creates the order parameters by checking the cart configuration.
		$preapproval_payment = $this->build_preapproval( $order );
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_preapproval_payment( json_encode( $preapproval_payment ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago trowed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - mercado pago gave error, payment creation failed with error: ' .
						$checkout_info['response']['message']
					);
				}
				return false;
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - wordpress gave error, payment creation failed with error: ' .
						$checkout_info['response']['message']
					);
				}
				return false;
			} else {
				// Obtain the URL.
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[create_url] - pre-approval link generated with success from mercado pago, with structure as follow: ' .
						json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
				}
				return $checkout_info['response']['init_point'];
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

	// Display the discount in payment method title.
	public function get_payment_method_title_subscription( $title, $id ) {

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

				$s_id = $get_request['response']['site_id'];
				if ( $s_id != 'MLA' && $s_id != 'MLB' && $s_id != 'MLM') {
					$this->mp = null;
					return false;
				}

				$this->is_test_user = in_array( 'test_user', $get_request['response']['tags'] );
				$this->site_id = $get_request['response']['site_id'];
				$this->country_configs = WC_WooMercadoPago_Module::get_country_config( $this->site_id );

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
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( ! WC_WooMercadoPago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}
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
				'admin.php?page=wc-settings&tab=checkout&section=WC_WooMercadoPagoSubscription_Gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPagoSubscription_Gateway'
		);
	}

	// Notify that Client_id and/or Client_secret are not valid.
	public function client_id_or_secret_missing_message() {
		echo '<div class="error"><p><strong>' .
			__( 'Subscription is Inactive', 'woocommerce-mercadopago-module' ) .
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

		// Setup sandbox mode.
		$this->mp->sandbox_mode( false );

		// Over here, $_GET should come with this JSON structure:
		// {
		// 	"topic": <string>,
		// 	"id": <string>
		// }
		// If not, the IPN is corrupted in some way.
		$data = $_GET;
		if ( isset( $data['action_mp_payment_id'] ) && isset( $data['action_mp_payment_amount'] ) ) {

			if ( $data['action_mp_payment_action'] === 'cancel' ) {

				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[check_ipn_response] - cancelling payment of ID ' . $data['action_mp_payment_id']
					);
				}

				if ( $this->mp != null && ! empty( $data['action_mp_payment_id'] ) ) {
					$response = $this->mp->cancel_payment( $data['action_mp_payment_id'] );
					$message = $response['response']['message'];
					$status = $response['status'];
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - cancel payment of id ' . $data['action_mp_payment_id'] .
							' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
						);
					}
					if ( $status >= 200 && $status < 300 ) {
						header( 'HTTP/1.1 200 OK' );
						echo json_encode( array(
							'status' => 200,
							'message' => __( 'Operation successfully completed.', 'woocommerce-mercadopago-module' )
						) );
					} else {
						header( 'HTTP/1.1 200 OK' );
						echo json_encode( array(
							'status' => $status,
							'message' => $message
						) );
					}
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - no payments or credentials invalid'
						);
					}
					header( 'HTTP/1.1 500 OK' );
				}

			} elseif ( $data['action_mp_payment_action'] === 'refund' ) {

				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id,
						'[check_ipn_response] - refunding payment of ID ' . $data['action_mp_payment_id']
					);
				}

				if ( $this->mp != null && ! empty( $data['action_mp_payment_id'] ) ) {
					$response = $this->mp->partial_refund_payment(
						$data['action_mp_payment_id'],
						(float) str_replace( ',', '.', $data['action_mp_payment_amount'] ),
						// TODO: here, we should improve by placing the actual reason and the external refarence
						__( 'Refund Payment', 'woocommerce-mercadopago-module' ) . ' ' . $data['action_mp_payment_id'],
						__( 'Refund Payment', 'woocommerce-mercadopago-module' ) . ' ' . $data['action_mp_payment_id']
					);
					$message = $response['response']['message'];
					$status = $response['status'];
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - refund payment of id ' . $data['action_mp_payment_id'] .
							' => ' . ( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
						);
					}
					if ( $status >= 200 && $status < 300 ) {
						header( 'HTTP/1.1 200 OK' );
						echo json_encode( array(
							'status' => 200,
							'message' => __( 'Operation successfully completed.', 'woocommerce-mercadopago-module' )
						) );
					} else {
						header( 'HTTP/1.1 200 OK' );
						echo json_encode( array(
							'status' => $status,
							'message' => $message
						) );
					}
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - no payments or credentials invalid'
						);
					}
					header( 'HTTP/1.1 500 OK' );
				}

			}

		} elseif ( isset( $data['id'] ) && isset( $data['topic'] ) ) {

			// We have received a normal IPN call for this gateway, start process by getting the access token...
			$access_token = array( 'access_token' => $this->mp->get_access_token() );

			// Now, we should handle the topic type that has come...
			if ( $data['topic'] == 'payment' ) {

				// Get the payment of a preapproval.
				$payment_info = $this->mp->get( '/v1/payments/' . $data['id'], $access_token, false );
				if ( ! is_wp_error( $payment_info ) && ( $payment_info['status'] == 200 || $payment_info['status'] == 201 ) ) {
					$payment_info['response']['ipn_type'] = 'payment';
					do_action( 'valid_mercadopagosubscription_ipn_request', $payment_info['response'] );
					header( 'HTTP/1.1 200 OK' );
				} else {
					if ( 'yes' == $this->debug) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - got status not equal 200: ' .
							json_encode( $payment_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
						);
					}
					return false;
				}

			} elseif ( $data['topic'] == 'preapproval' ) {

				// Get the preapproval reported by the IPN.
				$preapproval_info = $this->mp->get_preapproval_payment( $_GET['id'] );
				if ( ! is_wp_error( $preapproval_info ) && ( $preapproval_info['status'] == 200 || $preapproval_info['status'] == 201 ) ) {
					$preapproval_info['response']['ipn_type'] = 'preapproval';
					do_action( 'valid_mercadopagosubscription_ipn_request', $preapproval_info['response'] );
					header( 'HTTP/1.1 200 OK' );
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add(
							$this->id,
							'[check_ipn_response] - got status not equal 200: ' .
							json_encode( $preapproval_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
						);
					}
				}

			} else {

				// We have received an unhandled topic...
				$this->log->add(
					$this->id,
					'[check_ipn_response] - request failure, received an unhandled topic'
				);

			}

		} elseif ( isset( $data['data_id'] ) && isset( $data['type'] ) ) {

			// We have received a bad, however valid) IPN call for this gateway (data is set for API V1).
			// At least, we should respond 200 to notify server that we already received it.
			header( 'HTTP/1.1 200 OK' );

		} else {

			// Reaching here means that we received an IPN call but there are no data!
			// Just kills the processment. No IDs? No process!
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id,
					'[check_ipn_response] - request failure, received ipn call with no data'
				);
			}
			wp_die( __( 'Mercado Pago Request Failure', 'woocommerce-mercadopago-module' ) );

		}

		exit;

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

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {

			// Here, we process the status... this is the business rules!
			// Reference: https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/
			$status = isset( $data['status'] ) ? $data['status'] : 'pending';

			// Updates the order metadata.
			if ( $data['ipn_type'] == 'payment' ) {
				$total_paid = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
				$total_refund = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
				$total = $data['transaction_amount'];
				if ( ! empty( $data['payer']['email'] ) ) {
					$order->update_meta_data(
						__( 'Payer email', 'woocommerce-mercadopago-module' ),
						$data['payer']['email']
					);
				}
				if ( ! empty( $data['payment_type_id'] ) ) {
					$order->update_meta_data(
						__( 'Payment type', 'woocommerce-mercadopago-module' ),
						$data['payment_type_id']
					);
				}
				if ( ! empty( $data['id'] ) ) {
					$order->update_meta_data(
						'Mercado Pago - Payment ID ' . $data['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $total .
						']/[Paid ' . $total_paid .
						']/[Refund ' . $total_refund . ']'
					);
					$payment_ids_str = $order->get_meta( '_Mercado_Pago_Sub_Payment_IDs' );
					$payment_ids = array();
					if ( ! empty( $payment_ids_str ) ) {
						$payment_ids = explode( ', ', $payment_ids_str );
					}
					$payment_ids[] = $data['id'];
					$order->update_meta_data(
						'_Mercado_Pago_Sub_Payment_IDs',
						implode( ', ', $payment_ids )
					);
				}
				$order->save();
			} elseif ( $data['ipn_type'] == 'preapproval' ) {
				$status = $data['status'];
				if ( ! empty( $data['payer_email'] ) ) {
					$order->update_meta_data(
						__( 'Payer email', 'woocommerce-mercadopago-module' ),
						$data['payer_email']
					);
				}
				if ( ! empty( $data['id'] ) ) {
					$order->update_meta_data(
						'Mercado Pago Pre-Approval',
						'[ID ' . $data['id'] .
						']/[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $data['auto_recurring']['transaction_amount'] .
						']/[End ' . date( 'Y-m-d', strtotime( $data['auto_recurring']['end_date'] ) ) . ']'
					);
				}

				$order->save();
			}
		} else {

			// Here, we process the status... this is the business rules!
			// Reference: https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/
			$status = isset( $data['status'] ) ? $data['status'] : 'pending';

			// Updates the order metadata.
			if ( $data['ipn_type'] == 'payment' ) {
				$total_paid = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
				$total_refund = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
				$total = $data['transaction_amount'];
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
				if ( ! empty( $data['id'] ) ) {
					update_post_meta(
						$order_id,
						'Mercado Pago - Payment ID ' . $data['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $total .
						']/[Paid ' . $total_paid .
						']/[Refund ' . $total_refund . ']'
					);
					$payment_ids_str = get_post_meta(
						$order->id,
						'_Mercado_Pago_Sub_Payment_IDs',
						true
					);
					$payment_ids = array();
					if ( ! empty( $payment_ids_str ) ) {
						$payment_ids = explode( ', ', $payment_ids_str );
					}
					$payment_ids[] = $data['id'];
					update_post_meta(
						$order_id,
						'_Mercado_Pago_Sub_Payment_IDs',
						implode( ', ', $payment_ids )
					);
				}
			} elseif ( $data['ipn_type'] == 'preapproval' ) {
				$status = $data['status'];
				if ( ! empty( $data['payer_email'] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payer email', 'woocommerce-mercadopago-module' ),
						$data['payer_email']
					);
				}
				if ( ! empty( $data['id'] ) ) {
					update_post_meta(
						$order_id,
						'Mercado Pago Pre-Approval',
						'[ID ' . $data['id'] .
						']/[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $data['auto_recurring']['transaction_amount'] .
						']/[End ' . date( 'Y-m-d', strtotime( $data['auto_recurring']['end_date'] ) ) . ']'
					);
				}
			}
		}

		// Switch the status and update in WooCommerce.
		switch ( $status ) {
			case 'authorized':
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

	}

}
