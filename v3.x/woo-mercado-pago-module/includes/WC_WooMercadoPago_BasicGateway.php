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
 * Description: This class implements Mercado Pago Basic checkout.
 * @since 3.0.0
 */
class WC_WooMercadoPago_BasicGateway extends WC_Payment_Gateway {

	public function __construct() {
		
		// WooCommerce fields.
		$this->id = 'woo-mercado-pago-basic';
		$this->supports = array( 'products', 'refunds' );
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'assets/images/mercadopago.png', plugin_dir_path( __FILE__ ) )
		);

		$this->method_title = __( 'Mercado Pago - Basic Checkout', 'woo-mercado-pago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) ) .
		'"><br><br><strong>' .
			__( 'Receive payments in a matter of minutes. We make it easy for you: just tell us what you want to collect and we’ll take care of the rest.', 'woo-mercado-pago-module' ) .
		'</strong>';

		// Mercao Pago instance.
		$this->site_data = WC_Woo_Mercado_Pago_Module::get_site_data( false );
		$this->mp = new MP(
			WC_Woo_Mercado_Pago_Module::get_module_version(),
			get_option( '_mp_client_id' ),
			get_option( '_mp_client_secret' )
		);
		// TODO: Verify sandbox availability.
		$this->mp->sandbox_mode( false );

		// How checkout is shown.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method             = $this->get_option( 'method', 'iframe' );
		$this->iframe_width       = $this->get_option( 'iframe_width', '640' );
		$this->iframe_height      = $this->get_option( 'iframe_height', '800' );
		// How checkout redirections will behave.
		$this->auto_return        = $this->get_option( 'auto_return', 'yes' );
		$this->success_url        = $this->get_option( 'success_url', '' );
		$this->failure_url        = $this->get_option( 'failure_url', '' );
		$this->pending_url        = $this->get_option( 'pending_url', '' );
		// How checkout payment behaves.
		$this->installments       = $this->get_option( 'installments', '24' );
		$this->ex_payments        = $this->get_option( 'ex_payments', 'n/d' );
		$this->gateway_discount   = $this->get_option( 'gateway_discount', 0 );
		$this->two_cards_mode     = 'inactive';

		// Logging and debug.
		$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
		if ( ! empty ( $_mp_debug_mode ) ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_Woo_Mercado_Pago_Module::woocommerce_instance()->logger();
			}
		}

		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();

		// Used by IPN to receive IPN incomings.
		add_action(
			'woocommerce_api_wc_woomercadopago_basicgateway',
			array( $this, 'check_ipn_response' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopago_ipn_request',
			array( $this, 'successful_request' )
		);
		// Process the cancel order meta box order action.
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// Used by WordPress to render the custom checkout page.
		add_action(
			'woocommerce_receipt_' . $this->id,
			function( $order ) {
				echo $this->render_order_form( $order );
			}
		);
		// Used to fix CSS in some older WordPress/WooCommerce versions.
		add_action(
			'wp_head',
			function () {
				if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
					$page_id = wc_get_page_id( 'checkout' );
				} else {
					$page_id = woocommerce_get_page_id( 'checkout' );
				}
				if ( is_page( $page_id ) ) {
					echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
				}
			}
		);
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
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_basic' ), 10, 2
		);
		// Checkout updates.
		add_action(
			'woocommerce_thankyou',
			array( $this, 'update_checkout_status' )
		);

	}

	/**
	 * Summary: Initialise Gateway Settings Form Fields.
	 * Description: Initialise Gateway settings form fields with a customized page.
	 */
	public function init_form_fields() {

		// Show message if credentials are not properly configured.
		$_site_id_v0 = get_option( '_site_id_v0', '' );
		if ( empty( $_site_id_v0 ) ) {
			$this->form_fields = array(
				'no_credentials_title' => array(
					'title' => sprintf(
						__( 'It appears that your credentials are not properly configured.<br/>Please, go to %s and configure it.', 'woo-mercado-pago-module' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=mercado-pago-settings' ) ) . '">' .
						__( 'Mercado Pago Settings', 'woo-mercado-pago-module' ) .
						'</a>'
					),
					'type' => 'title'
				),
			);
			return;
		}

		$this->two_cards_mode = $this->mp->check_two_cards();

		// Validate back URL.
		if ( ! empty( $this->success_url ) && filter_var( $this->success_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$success_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woo-mercado-pago-module' ) . ' ';
		} else {
			$success_back_url_message = __( 'Where customers should be redirected after a successful purchase. Let blank to redirect to the default store order resume page.', 'woo-mercado-pago-module' );
		}
		if ( ! empty( $this->failure_url ) && filter_var( $this->failure_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$fail_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woo-mercado-pago-module' ) . ' ';
		} else {
			$fail_back_url_message = __( 'Where customers should be redirected after a failed purchase. Let blank to redirect to the default store order resume page.', 'woo-mercado-pago-module' );
		}
		if ( ! empty( $this->pending_url ) && filter_var( $this->pending_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$pending_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woo-mercado-pago-module' ) . ' ';
		} else {
			$pending_back_url_message = __( 'Where customers should be redirected after a pending purchase. Let blank to redirect to the default store order resume page.', 'woo-mercado-pago-module' );
		}

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Basic Checkout', 'woo-mercado-pago-module' ),
				'default' => 'no'
			),
			'checkout_options_title' => array(
				'title' => __( 'Checkout Interface: How checkout is shown', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'title' => array(
				'title' => __( 'Title', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' =>
					__( 'Title shown to the client in the checkout.', 'woo-mercado-pago-module' ),
				'default' => __( 'Mercado Pago', 'woo-mercado-pago-module' )
			),
			'description' => array(
				'title' => __( 'Description', 'woo-mercado-pago-module' ),
				'type' => 'textarea',
				'description' =>
					__( 'Description shown to the client in the checkout.', 'woo-mercado-pago-module' ),
				'default' => __( 'Pay with Mercado Pago', 'woo-mercado-pago-module' )
			),
			'method' => array(
				'title' => __( 'Integration Method', 'woo-mercado-pago-module' ),
				'type' => 'select',
				'description' => __( 'Select how your clients should interact with Mercado Pago. Modal Window (inside your store), Redirect (Client is redirected to Mercado Pago), or iFrame (an internal window is embedded to the page layout).', 'woo-mercado-pago-module' ),
				'default' => 'iframe',
				'options' => array(
					'iframe' => __( 'iFrame', 'woo-mercado-pago-module' ),
					'modal' => __( 'Modal Window', 'woo-mercado-pago-module' ),
					'redirect' => __( 'Redirect', 'woo-mercado-pago-module' )
				)
			),
			'iframe_width' => array(
				'title' => __( 'iFrame Width', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' => __( 'If your integration method is iFrame, please inform the payment iFrame width.', 'woo-mercado-pago-module' ),
				'default' => '640'
			),
			'iframe_height' => array(
				'title' => __( 'iFrame Height', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' => __( 'If your integration method is iFrame, please inform the payment iFrame height.', 'woo-mercado-pago-module' ),
				'default' => '800'
			),
			'checkout_navigation_title' => array(
				'title' => __( 'Checkout Navigation: How checkout redirections will behave', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'auto_return' => array(
				'title' => __( 'Auto Return', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Automatic Return After Payment', 'woo-mercado-pago-module' ),
				'default' => 'yes',
				'description' =>
					__( 'After the payment, client is automatically redirected.', 'woo-mercado-pago-module' ),
			),
			'success_url' => array(
				'title' => __( 'Sucess URL', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' => $success_back_url_message,
				'default' => ''
			),
			'failure_url' => array(
				'title' => __( 'Failure URL', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' => $fail_back_url_message,
				'default' => ''
			),
			'pending_url' => array(
				'title' => __( 'Pending URL', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' => $pending_back_url_message,
				'default' => ''
			),
			'payment_title' => array(
				'title' => __( 'Payment Options: How payment options behaves', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'installments' => array(
				'title' => __( 'Max installments', 'woo-mercado-pago-module' ),
				'type' => 'select',
				'description' => __( 'Select the max number of installments for your customers.', 'woo-mercado-pago-module' ),
				'default' => '24',
				'options' => array(
					'1' => __( '1x installment', 'woo-mercado-pago-module' ),
					'2' => __( '2x installmens', 'woo-mercado-pago-module' ),
					'3' => __( '3x installmens', 'woo-mercado-pago-module' ),
					'4' => __( '4x installmens', 'woo-mercado-pago-module' ),
					'5' => __( '5x installmens', 'woo-mercado-pago-module' ),
					'6' => __( '6x installmens', 'woo-mercado-pago-module' ),
					'10' => __( '10x installmens', 'woo-mercado-pago-module' ),
					'12' => __( '12x installmens', 'woo-mercado-pago-module' ),
					'15' => __( '15x installmens', 'woo-mercado-pago-module' ),
					'18' => __( '18x installmens', 'woo-mercado-pago-module' ),
					'24' => __( '24x installmens', 'woo-mercado-pago-module' )
				)
			),
			'ex_payments' => array(
				'title' => __( 'Exclude Payment Methods', 'woo-mercado-pago-module' ),
				'description' => __( 'Select the payment methods that you <strong>don\'t</strong> want to receive with Mercado Pago.', 'woo-mercado-pago-module' ),
				'type' => 'multiselect',
				'options' => explode( ',', get_option( '_all_payment_methods_v0', '' ) ),
				'default' => ''
			),
			'gateway_discount' => array(
				'title' => __( 'Discount by Gateway', 'woo-mercado-pago-module' ),
				'type' => 'number',
				'description' => __( 'Give a percentual (0 to 100) discount for your customers if they use this payment gateway.', 'woo-mercado-pago-module' ),
				'default' => '0'
			),
			'two_cards_mode' => array(
				'title' => __( 'Two Cards Mode', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Payments with Two Cards', 'woo-mercado-pago-module' ),
				'default' => ( $this->two_cards_mode == 'active' ? 'yes' : 'no' ),
				'description' =>
					__( 'Your customer will be able to use two different cards to pay the order.', 'woo-mercado-pago-module' )
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
				if ( $key == 'two_cards_mode' ) {
					// We dont save two card mode as it should come from api.
					$value = $this->get_field_value( $key, $field, $post_data );
					$this->two_cards_mode = ( $value == 'yes' ? 'active' : 'inactive' );
				} elseif ( $key == 'iframe_width' ) {
					$value = $this->get_field_value( $key, $field, $post_data );
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = '480';
					} else {
						$this->settings[$key] = $value;
					}
				} elseif ( $key == 'iframe_height' ) {
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = '800';
					} else {
						$this->settings[$key] = $value;
					}
				} elseif ( $key == 'gateway_discount') {
					$value = $this->get_field_value( $key, $field, $post_data );
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = 0;
					} else {
						if ( $value < 0 || $value >= 100 || empty ( $value ) ) {
							$this->settings[$key] = 0;
						} else {
							$this->settings[$key] = $value;
						}
					}
				} else {
					$this->settings[$key] = $this->get_field_value( $key, $field, $post_data );
				}
			}
		}
		$_site_id_v0 = get_option( '_site_id_v0', '' );
		if ( ! empty( $_site_id_v0 ) ) {
			// Create MP instance.
			$mp = new MP(
				WC_Woo_Mercado_Pago_Module::get_module_version(),
				get_option( '_mp_client_id' ),
				get_option( '_mp_client_secret' )
			);
			// Analytics.
			$infra_data = WC_Woo_Mercado_Pago_Module::get_common_settings();
			$infra_data['checkout_basic'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$infra_data['two_cards'] = ( $this->two_cards_mode == 'active' ? 'true' : 'false' );
			$response = $mp->analytics_save_settings( $infra_data );
			// Two cards mode.
			$response = $mp->set_two_cards_mode( $this->two_cards_mode );
		}
		// Apply updates.
		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
		);
	}

	private function write_log( $function, $message ) {
		$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
		if ( ! empty ( $_mp_debug_mode ) ) {
			$this->log->add(
				$this->id,
				'[' . $function . ']: ' . $message
			);
		}
	}

	/*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES (CLIENT SIDE)
	 * ========================================================================
	 */

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		// Reaching here means that there we run out of payments, and there is an amount
		// remaining to be refund, which is impossible as it implies refunding more than
		// available on paid amounts.
		return false;
	}

	/**
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {
	}

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
		$client_id = get_option( '_mp_client_id' );
		if ( ! empty( $client_id ) ) {
			$w = WC_Woo_Mercado_Pago_Module::woocommerce_instance();
			$available_payments = array();
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $g ) {
				$available_payments[] = $g->id;
			}
			$available_payments = str_replace( '-', '_', implode( ', ', $available_payments ) );
			if ( wp_get_current_user()->ID != 0 ) {
				$logged_user_email = wp_get_current_user()->user_email;
			} else {
				$logged_user_email = null;
			}
			?>
			<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				var MA = ModuleAnalytics;
				MA.setToken( '<?php echo $client_id; ?>' );
				MA.setPlatform( 'WooCommerce' );
				MA.setPlatformVersion( '<?php echo $w->version; ?>' );
				MA.setModuleVersion( '<?php echo WC_Woo_Mercado_Pago_Module::VERSION; ?>' );
				MA.setPayerEmail( '<?php echo ( $logged_user_email != null ? $logged_user_email : "" ); ?>' );
				MA.setUserLogged( <?php echo ( empty( $logged_user_email ) ? 0 : 1 ); ?> );
				MA.setInstalledModules( '<?php echo $available_payments; ?>' );
				MA.post();
			</script>
			<?php
		}
	}

	public function update_checkout_status( $order_id ) {
		$client_id = get_option( '_mp_client_id' );
		$_test_user_v0 = get_option( '_test_user_v0', false );
		if ( ! empty( $client_id ) && ! $_test_user_v0 ) {
			if ( get_post_meta( $order_id, '_used_gateway', true ) != 'woo-mercado-pago-basic' ) {
				return;
			}
			$this->write_log( __FUNCTION__, 'updating order of ID ' . $order_id );
			echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				var MA = ModuleAnalytics;
				MA.setToken( ' . $client_id . ' );
				MA.setPaymentType("basic");
				MA.setCheckoutType("basic");
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
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_used_gateway', 'woo-mercado-pago-basic' );
			$order->save();
		} else {
 			update_post_meta( $order_id, '_used_gateway', 'woo-mercado-pago-basic' );
 		}

		if ( 'redirect' == $this->method ) {
			$this->write_log( __FUNCTION__, 'customer being redirected to Mercado Pago.' );
			return array(
				'result' => 'success',
				'redirect' => $this->create_url( $order )
			);
		} elseif ( 'modal' == $this->method || 'iframe' == $this->method ) {
			$this->write_log( __FUNCTION__, 'preparing to render Mercado Pago checkout view.' );
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
	public function render_order_form( $order_id ) {

		$order = wc_get_order( $order_id );
		$url = $this->create_url( $order );

		if ( 'modal' == $this->method && $url ) {
			
			$this->write_log( __FUNCTION__, 'rendering Mercado Pago lightbox (modal window).' );

			// ===== The checkout is made by displaying a modal to the customer =====
			$html = '<style type="text/css">
						#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important; height: 590px !important; }
					</style>';
			$html = '<script type="text/javascript" src="//secure.mlstatic.com/mptools/render.js"></script>
					<script type="text/javascript">
						(function() { $MPC.openCheckout({ url: "' . esc_url( $url ) . '", mode: "modal" }); })();
					</script>';
			$html = '<img width="468" height="60" src="' . $this->site_data['checkout_banner'] . '">';
			$html = '<p></p><p>' . wordwrap(
						__( 'Thank you for your order. Please, proceed with your payment clicking in the bellow button.', 'woo-mercado-pago-module' ),
						60, '<br>'
					) . '</p>
					<a id="submit-payment" href="' . esc_url( $url ) . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
						__( 'Pay with Mercado Pago', 'woo-mercado-pago-module' ) .
					'</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' .
						__( 'Cancel order &amp; Clear cart', 'woo-mercado-pago-module' ) .
					'</a>';
			return $html;
			// ===== The checkout is made by displaying a modal to the customer =====

		} elseif ( 'modal' != $this->method && $url ) {

			$this->write_log( __FUNCTION__, 'embedding Mercado Pago iframe.' );

			// ===== The checkout is made by rendering Mercado Pago form within a iframe =====
			$html = '<img width="468" height="60" src="' . $this->site_data['checkout_banner'] . '">';
			$html = '<p></p><p>' . wordwrap(
						__( 'Thank you for your order. Proceed with your payment completing the following information.', 'woo-mercado-pago-module' ),
						60, '<br>'
					) . '</p>
					<iframe src="' . esc_url( $url ) . '" name="MP-Checkout" ' .
					'width="' . $this->iframe_width . '" ' . 'height="' . $this->iframe_height . '" ' .
					'frameborder="0" scrolling="no" id="checkout_mercadopago"></iframe>';
			return $html;
			// ===== The checkout is made by rendering Mercado Pago form within a iframe =====

		} else {

			$this->write_log( __FUNCTION__, 'unable to build Mercado Pago checkout URL.' );

			// ===== Reaching at this point means that the URL could not be build by some reason =====
			$html = '<p>' .
						__( 'An error occurred when proccessing your payment. Please try again or contact us for assistence.', 'woo-mercado-pago-module' ) .
					'</p>' .
					'<a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
						__( 'Click to try again', 'woo-mercado-pago-module' ) .
					'</a>
			';
			return $html;
			// ===== Reaching at this point means that the URL could not be build by some reason =====

		}

	}

	/**
	 * Summary: Build Mercado Pago preference.
	 * Description: Create Mercado Pago preference and get init_point URL based in the order options
	 * from the cart.
	 * @return the preference object.
	 */
	public function build_payment_preference( $order ) {

		$selected_shipping = $order->get_shipping_method();
		$order_content = array();
		$items = array();

		// Here we build the array that contains ordered items, from customer cart.
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item['product_id'] );
					$product_title = method_exists( $product, 'get_description' ) ?
						$product->get_name() :
						$product->post->post_title;
					$product_content = method_exists( $product, 'get_description' ) ?
						$product->get_description() :
						$product->post->post_content;
					$line_amount = $item['line_total'] + $item['line_tax'];
					$method_discount = $line_amount * ( $this->gateway_discount / 100 );

					$currency_ratio = 1;
					$_mp_currency_conversion_v0 = get_option( '_mp_currency_conversion_v0', '' );
					if ( ! empty( $_mp_currency_conversion_v0 ) ) {
						$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate( $this->site_data['currency'] );
						$currency_ratio = $currency_ratio > 0 ? $currency_ratio : 1;
					}

					array_push( $order_content, $product_title . ' x ' . $item['qty'] );
					array_push( $items, array(
						'id' => $item['product_id'],
						'title' => html_entity_decode( $product_title ) . ' x ' . $item['qty'],
						'description' => sanitize_file_name( html_entity_decode(
							strlen( $product_content ) > 230 ?
							substr( $product_content, 0, 230 ) . '...' :
							$product_content
						) ),
						'picture_url' => sizeof( $order->get_items() > 1 ) ?
							plugins_url( 'assets/images/cart.png', plugin_dir_path( __FILE__ ) ) :
							wp_get_attachment_url( $product->get_image_id()
						),
						'category_id' => get_option( '_mp_category_name', 'others' ),
						'quantity' => 1,
						'unit_price' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
							floor( ( $line_amount - $method_discount ) * $currency_ratio ) :
							floor( ( $line_amount - $method_discount ) * $currency_ratio * 100 ) / 100,
						'currency_id' => $this->site_data['currency']
					) );
				}
			}

			// If we're not using Mercado Envios, shipping cost is added as an item in the order, preventing
			// Mercado Pago Javascript to show shipment setup twice.
			if ( strpos( $selected_shipping, 'Mercado Envios' ) !== 0 && $order->get_total_shipping() + $order->get_shipping_tax() > 0 ) {
				$ship_amount = $order->get_total_shipping() + $order->get_shipping_tax();
				array_push( $order_content, __( 'Shipping service used by store', 'woo-mercado-pago-module' ) );
				array_push( $items, array(
					'title' => __( 'Shipping service used by store', 'woo-mercado-pago-module' ),
					'description' => __( 'Shipping service used by store', 'woo-mercado-pago-module' ),
					'category_id' => get_option( '_mp_category_name', 'others' ),
					'quantity' => 1,
					'unit_price' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
						floor( $ship_amount * $currency_ratio ) :
						floor( $ship_amount * $currency_ratio * 100 ) / 100,
					'currency_id' => $this->site_data['currency']
				) );
			}

			$items[0]['title'] = implode( ', ', $order_content );
		}

		// Create and setup payment options.
		$excluded_payment_methods = array();
		$payment_methods = explode( ',', get_option( '_all_payment_methods_v0', '' ) );
		if ( is_array( $this->ex_payments ) || is_object( $this->ex_payments ) ) {
			foreach ( $this->ex_payments as $excluded ) {
				if ( $excluded == 0 ) {
					break;
				}
				array_push( $excluded_payment_methods, array(
					'id' => $payment_methods[$excluded]
				) );
			}
		}
		$payment_methods = array(
			'installments' => (int) $this->installments,
			'default_installments' => 1,
			'excluded_payment_methods' => $excluded_payment_methods
		);

		// Create Mercado Pago preference.
		$preferences = array(
			'items' => $items,
			'payer' => ( method_exists( $order, 'get_id' ) ?
				array( // Support to WooCommerce 3.0.
					'name' => html_entity_decode( $order->get_billing_first_name() ),
					'surname' => html_entity_decode( $order->get_billing_last_name() ),
					'email' => $order->get_billing_email(),
					'phone' => array(
						'number' => $order->get_billing_phone()
					),
					'address' => array(
						'street_name' => html_entity_decode(
							$order->get_billing_address_1() . ' / ' .
							$order->get_billing_city() . ' ' .
							$order->get_billing_state() . ' ' .
							$order->get_billing_country()
						),
						'zip_code' => $order->get_billing_postcode()
					)
				) :
				array( // In case that we're not with WooCommerce 3.0.
					'name' => html_entity_decode( $order->billing_first_name ),
					'surname' => html_entity_decode( $order->billing_last_name ),
					'email' => $order->billing_email,
					'phone'	=> array(
						'number' => $order->billing_phone
					),
					'address' => array(
						'street_name' => html_entity_decode(
							$order->billing_address_1 . ' / ' .
							$order->billing_city . ' ' .
							$order->billing_state . ' ' .
							$order->billing_country
						),
						'zip_code' => $order->billing_postcode
					)
				)
			),
			'back_urls' => array(
				'success' => empty( $this->success_url ) ?
					WC_Woo_Mercado_Pago_Module::workaround_ampersand_bug(
						esc_url( $this->get_return_url( $order ) )
					) :
					$this->success_url,
				'failure' => empty( $this->failure_url ) ?
					WC_Woo_Mercado_Pago_Module::workaround_ampersand_bug(
						esc_url( $order->get_cancel_order_url() )
					) :
					$this->failure_url,
				'pending' => empty( $this->pending_url ) ?
					WC_Woo_Mercado_Pago_Module::workaround_ampersand_bug(
						esc_url( $this->get_return_url( $order) )
					) : $this->pending_url
			),
			//'marketplace' =>
			//'marketplace_fee' =>
			'shipments' => array(
				//'cost' =>
				//'mode' =>
				'receiver_address' => ( method_exists( $order, 'get_id' ) ?
					array(
						'zip_code' => $order->get_shipping_postcode(),
						//'street_number' =>
						'street_name' => html_entity_decode(
							$order->get_shipping_address_1() . ' ' .
							$order->get_shipping_city() . ' ' .
							$order->get_shipping_state() . ' ' .
							$order->get_shipping_country()
						),
						//'floor' =>
						'apartment' => $order->get_shipping_address_2()
					) :
					array(
						'zip_code' => $order->shipping_postcode,
						//'street_number' =>
						'street_name' => html_entity_decode(
							$order->shipping_address_1 . ' ' .
							$order->shipping_city . ' ' .
							$order->shipping_state . ' ' .
							$order->shipping_country
						),
						//'floor' =>
						'apartment' => $order->shipping_address_2
					)
				),
			),
			'payment_methods' => $payment_methods,
			//'notification_url' =>
			'external_reference' => get_option( '_mp_store_identificator', 'WC-' ) .
				( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id )
			//'additional_info' =>
			//'expires' =>
			//'expiration_date_from' =>
			//'expiration_date_to' =>
		);

		// If we're  using Mercado Envios, shipping cost should be setup in preferences.
		if ( strpos( $selected_shipping, 'Mercado Envios' ) === 0 && $order->get_total_shipping() + $order->get_shipping_tax() > 0 ) {
			$preferences['shipments']['mode'] = 'me2';
			foreach ( $order->get_shipping_methods() as $shipping ) {
				$preferences['shipments']['dimensions'] = $shipping['dimensions'];
				$preferences['shipments']['default_shipping_method'] = (int) $shipping['shipping_method_id'];
				$preferences['shipments']['free_methods'] = array();
				// Get shipping method id.
				$prepare_method_id = explode( ':', $shipping['method_id'] );
				// Get instance_id.
				$shipping_id = $prepare_method_id[count( $prepare_method_id ) - 1];
				// TODO: Refactor to Get zone by instance_id.
				$shipping_zone = WC_Shipping_Zones::get_zone_by( 'instance_id', $shipping_id );
				// Get all shipping and filter by free_shipping (Mercado Envios).
				foreach ( $shipping_zone->get_shipping_methods() as $key => $shipping_object ) {
					// Check is a free method.
					if ( $shipping_object->get_option( 'free_shipping' ) == 'yes' ) {
						// Get shipping method id (Mercado Envios).
						$shipping_method_id = $shipping_object->get_shipping_method_id( $this->site_data['site_id'] );
						$preferences['shipments']['free_methods'][] = array( 'id' => (int) $shipping_method_id );
					}
				}
			}
		}

		// Do not set IPN url if it is a localhost.
		if ( ! strrpos( get_site_url(), 'localhost' ) ) {
			$preferences['notification_url'] = WC_Woo_Mercado_Pago_Module::workaround_ampersand_bug(
				esc_url( WC()->api_request_url( 'WC_WooMercadoPago_BasicGateway' ) )
			);
		}

		// Set sponsor ID.
		$_test_user_v0 = get_option( '_test_user_v0', false );
		if ( ! $_test_user_v0 ) {
			$preferences['sponsor_id'] = $this->site_data['sponsor_id'];
		}

		// Auto return options.
		if ( 'yes' == $this->auto_return ) {
			$preferences['auto_return'] = 'approved';
		}

		// Debug/log this preference.
		$this->write_log(
			__FUNCTION__,
			'preference created with following structure: ' .
			json_encode( $preferences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);

		return $preferences;
	}

	protected function create_url( $order ) {
		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->build_payment_preference( $order );
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_preference( json_encode( $preferences ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago trowed an error.
				$this->write_log(
					__FUNCTION__,
					'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return false;
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				$this->write_log(
					__FUNCTION__,
					'wordpress gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return false;
			} else {
				// Obtain the URL.
				$this->write_log(
					__FUNCTION__,
					'payment link generated with success from mercado pago, with structure as follow: ' .
					json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
				// TODO: Verify sandbox availability.
				//if ( 'yes' == $this->sandbox ) {
				//	return $checkout_info['response']['sandbox_init_point'];
				//} else {
				return $checkout_info['response']['init_point'];
				//}
			}
		} catch ( MercadoPagoException $ex ) {
			// Something went wrong with the payment creation.
			$this->write_log(
				__FUNCTION__,
				'payment creation failed with exception: ' .
				json_encode( $ex, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
			return false;
		}
	}

	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS (SERVER SIDE)
	 * ========================================================================
	 */

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		/*global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( WC_Woo_Mercado_Pago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}*/
		// Check if this gateway is enabled and well configured.
		$_mp_client_id = get_option( '_mp_client_id' );
		$_mp_client_secret = get_option( '_mp_client_secret' );
		$_site_id_v0 = get_option( '_site_id_v0' );
		$available = ( 'yes' == $this->settings['enabled'] ) &&
			! empty( $_mp_client_id ) &&
			! empty( $_mp_client_secret ) &&
			! empty( $_site_id_v0 );
		return $available;
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopago_basicgateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPago_BasicGateway'
		);
	}

	// Display the discount in payment method title.
	public function get_payment_method_title_basic( $title, $id ) {
		if ( ! is_checkout() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $title;
		}
		if ( $title != $this->title || $this->gateway_discount == 0 ) {
			return $title;
		}
		//if ( WC()->session->chosen_payment_method == 'woocommerce-mercadopago-subscription-module' ) {
		//	return $title;
		//}
		$total = (float) WC()->cart->subtotal;
		$price_percent = $this->gateway_discount / 100;
		if ( $price_percent > 0 ) {
			$title .= ' (' . __( 'Discount of ', 'woo-mercado-pago-module' ) .
				strip_tags( wc_price( $total * $price_percent ) ) . ' )';
		}
		return $title;
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
		$this->write_log(
			__FUNCTION__,
			'received _get content: ' .
			json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);
		// Setup sandbox mode.
		$this->mp->sandbox_mode( 'yes' == $this->sandbox );
		// Over here, $_GET should come with this JSON structure:
		// {
		// 	"topic": <string>,
		// 	"id": <string>
		// }
		// If not, the IPN is corrupted in some way.
		$data = $_GET;
		if ( isset( $data['id'] ) && isset( $data['topic'] ) ) {
			// We have received a normal IPN call for this gateway, start process by getting the access token...
			$access_token = array( 'access_token' => $this->mp->get_access_token() );
			// Now, we should handle the topic type that has come...
			if ( $data['topic'] == 'merchant_order' ) {
				// Get the merchant_order reported by the IPN.
				$merchant_order_info = $this->mp->get( '/merchant_orders/' . $data['id'], $access_token, false );
				if ( ! is_wp_error( $merchant_order_info ) && ( $merchant_order_info['status'] == 200 || $merchant_order_info['status'] == 201 ) ) {
					$payments = $merchant_order_info['response']['payments'];
					// If the payment's transaction amount is equal (or bigger) than the merchant order's amount we can release the items.
					if ( sizeof( $payments ) >= 1 ) {
						// We have payments...
						$merchant_order_info['response']['ipn_type'] = 'merchant_order';
						do_action( 'valid_mercadopago_ipn_request', $merchant_order_info['response'] );
					} else {
						// We have no payments?
						$this->write_log( __FUNCTION__, 'order received but has no payment.' );
					}
					header( 'HTTP/1.1 200 OK' );
				} else {
					$this->write_log(
						__FUNCTION__,
						'got status not equal 200: ' .
						json_encode( $preapproval_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
				}
			} elseif ( $data['topic'] == 'payment' ) {
				$payment_info = $this->mp->get( '/v1/payments/' . $data['id'], $access_token, false );
				if ( ! is_wp_error( $payment_info ) && ( $payment_info['status'] == 200 || $payment_info['status'] == 201 ) ) {
					$payments = $payment_info['response']['payments'];
					// If the payment's transaction amount is equal (or bigger) than the merchant order's amount we can release the items.
					if ( sizeof( $payments ) >= 1 ) {
						// We have payments...
						$payment_info['response']['ipn_type'] = 'payment';
						do_action( 'valid_mercadopago_ipn_request', $payment_info['response'] );
					} else {
						// We have no payments?
						$this->write_log( __FUNCTION__, 'order received but has no payment.' );
					}
					header( 'HTTP/1.1 200 OK' );
				} else {
					$this->write_log(
						__FUNCTION__,
						'error when processing received data: ' .
						json_encode( $payment_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
				}
			} else {
				// We have received an unhandled topic...
				$this->write_log(
					__FUNCTION__,
					'request failure, received an unhandled topic.'
				);
			}
		} elseif ( isset( $data['data_id'] ) && isset( $data['type'] ) ) {
			// We have received a bad, however valid) IPN call for this gateway (data is set for API V1).
			// At least, we should respond 200 to notify server that we already received it.
			header( 'HTTP/1.1 200 OK' );
		} else {
			// Reaching here means that we received an IPN call but there are no data!
			// Just kills the processment. No IDs? No process!
			$this->write_log(
				__FUNCTION__,
				'request failure, received ipn call with no data.'
			);
			wp_die( __( 'Mercado Pago Request Failure', 'woo-mercado-pago-module' ) );
		}
	}
	/**
	 * Summary: Properly handles each case of notification, based in payment status.
	 * Description: Properly handles each case of notification, based in payment status.
	 */
	public function successful_request( $data ) {
		$this->write_log( __FUNCTION__, 'starting to process ipn update...' );
		// Get the order and check its presence.
		$order_key = $data['external_reference'];
		if ( empty( $order_key ) ) {
			return;
		}
		$invoice_prefix = get_option( '_mp_store_identificator', 'WC-' );
		$id = (int) str_replace( $invoice_prefix, '', $order_key );
		$order = wc_get_order( $id );
		// Check if order exists.
		if ( ! $order ) {
			return;
		}
		// WooCommerce 3.0 or later.
		$order_id = ( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id );
		// Check if we have the correct order.
		if ( $order_id !== $id ) {
			return;
		}
		$this->write_log(
			__FUNCTION__,
			'updating metadata and status with data: ' .
			json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);
		// Here, we process the status... this is the business rules!
		// Reference: https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/
		$status = 'pending';
		$payments = $data['payments'];
		if ( sizeof( $payments ) == 1 ) {
			// If we have only one payment, just set status as its status
			$status = $payments[0]['status'];
		} elseif ( sizeof( $payments ) > 1 ) {
			// However, if we have multiple payments, the overall payment have some rules...
			$total_paid = 0.00;
			$total_refund = 0.00;
			$total = $data['shipping_cost'] + $data['total_amount'];
			// Grab some information...
			foreach ( $data['payments'] as $payment ) {
				if ( $payment['status'] === 'approved' ) {
					// Get the total paid amount, considering only approved incomings.
					$total_paid += (float) $payment['total_paid_amount'];
				} elseif ( $payment['status'] === 'refunded' ) {
					// Get the total refounded amount.
					$total_refund += (float) $payment['amount_refunded'];
				}
			}
			if ( $total_paid >= $total ) {
				$status = 'approved';
			} elseif ( $total_refund >= $total ) {
				$status = 'refunded';
			} else {
				$status = 'pending';
			}
		}
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			// Updates the type of gateway.
			$order->update_meta_data( '_used_gateway', 'woo-mercado-pago-basic' );
			if ( ! empty( $data['payer']['email'] ) ) {
				$order->update_meta_data( __( 'Payer email', 'woo-mercado-pago-module' ), $data['payer']['email'] );
			}
			if ( ! empty( $data['payment_type'] ) ) {
				$order->update_meta_data( __( 'Payment type', 'woo-mercado-pago-module' ), $data['payment_type'] );
			}
			if ( ! empty( $data['payments'] ) ) {
				$payment_ids = array();
				foreach ( $data['payments'] as $payment ) {
					$payment_ids[] = $payment['id'];
					$order->update_meta_data( 'Mercado Pago - Payment ' . $payment['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $payment['date_created'] ) ) .
						']/[Amount ' . $payment['transaction_amount'] .
						']/[Paid ' . $payment['total_paid_amount'] .
						']/[Refund ' . $payment['amount_refunded'] . ']'
					);
				}
				if ( sizeof( $payment_ids ) > 0 ) {
					$order->update_meta_data( '_Mercado_Pago_Payment_IDs', implode( ', ', $payment_ids ) );
				}
			}
			$order->save();
		} else {
			// Updates the type of gateway.
 			update_post_meta( $order->id, '_used_gateway', 'woo-mercado-pago-basic' );
			if ( ! empty( $data['payer']['email'] ) ) {
				update_post_meta( $order_id, __( 'Payer email', 'woo-mercado-pago-module' ), $data['payer']['email'] );
			}
			if ( ! empty( $data['payment_type'] ) ) {
				update_post_meta( $order_id, __( 'Payment type', 'woo-mercado-pago-module' ), $data['payment_type'] );
			}
			if ( ! empty( $data['payments'] ) ) {
				$payment_ids = array();
				foreach ( $data['payments'] as $payment ) {
					$payment_ids[] = $payment['id'];
					update_post_meta(
						$order_id,
						'Mercado Pago - Payment ' . $payment['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $payment['date_created'] ) ) .
						']/[Amount ' . $payment['transaction_amount'] .
						']/[Paid ' . $payment['total_paid_amount'] .
						']/[Refund ' . $payment['amount_refunded'] . ']'
					);
				}
				if ( sizeof( $payment_ids ) > 0 ) {
					update_post_meta( $order_id, '_Mercado_Pago_Payment_IDs', implode( ', ', $payment_ids ) );
				}
			}
		}
		// Switch the status and update in WooCommerce.
		$this->write_log(
			__FUNCTION__,
			'Changing order status to: ' .
			WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( str_replace( '_', '', $status ) )
		);
		switch ( $status ) {
			case 'approved':
				$order->add_order_note(
					'Mercado Pago: ' .
					__( 'Payment approved.', 'woo-mercado-pago-module' )
				);
				$order->payment_complete();
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'approved' )
				);
				break;
			case 'pending':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'pending' )
				);
				$order->add_order_note(
					'Mercado Pago: ' .
					__( 'Customer haven\'t paid yet.', 'woo-mercado-pago-module' )
				);
				break;
			case 'in_process':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'on-hold' ),
					'Mercado Pago: ' .
					__( 'Payment under review.', 'woo-mercado-pago-module' )
				);
				break;
			case 'rejected':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'failed' ),
					'Mercado Pago: ' .
					__( 'The payment was refused. The customer can try again.', 'woo-mercado-pago-module' )
				);
				break;
			case 'refunded':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'refunded' ),
					'Mercado Pago: ' .
					__( 'The payment was refunded to the customer.', 'woo-mercado-pago-module' )
				);
				break;
			case 'cancelled':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'cancelled' ),
					'Mercado Pago: ' .
					__( 'The payment was cancelled.', 'woo-mercado-pago-module' )
				);
				break;
			case 'in_mediation':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'inmediation' )
				);
				$order->add_order_note(
					'Mercado Pago: ' .
					__( 'The payment is under mediation or it was charged-back.', 'woo-mercado-pago-module' )
				);
				break;
			case 'charged-back':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'chargedback' )
				);
				$order->add_order_note(
					'Mercado Pago: ' .
					__( 'The payment is under mediation or it was charged-back.', 'woo-mercado-pago-module' )
				);
				break;
			default:
				break;
		}
		//$this->check_mercado_envios( $data );
	}
	/**
	 * Summary: Check IPN data and updates Mercado Envios tag and informaitons.
	 * Description: Check IPN data and updates Mercado Envios tag and informaitons.
	 */
	/*public function check_mercado_envios( $merchant_order ) {
		$order_key = $merchant_order['external_reference'];
		if ( ! empty( $order_key ) ) {
			$invoice_prefix = get_option( '_mp_store_identificator', 'WC-' );
			$order_id = (int) str_replace( $invoice_prefix, '', $order_key );
			$order = wc_get_order( $order_id );
			if ( count( $merchant_order['shipments'] ) > 0 ) {
				foreach ( $merchant_order['shipments'] as $shipment ) {
					$shipment_id = $shipment['id'];
					// Get shipping data on merchant_order.
					$shipment_name = $shipment['shipping_option']['name'];
					$shipment_cost = $shipment['shipping_option']['cost'];
					$shipping_method_id = $shipment['shipping_option']['shipping_method_id'];
					// Get data shipping selected on checkout.
					$shipping_meta = $order->get_items( 'shipping' );
					$order_item_shipping_id = null;
					$method_id = null;
					foreach ( $shipping_meta as $key => $shipping ) {
						$order_item_shipping_id = $key;
						$method_id = $shipping['method_id'];
					}
					$free_shipping_text = '';
					$free_shipping_status = 'no';
					if ( $shipment_cost == 0 ) {
						$free_shipping_status = 'yes';
						$free_shipping_text = ' (' . __( 'Free Shipping', 'woocommerce' ) . ')';
					}
					// WooCommerce 3.0 or later.
					if ( method_exists( $order, 'get_id' ) ) {
						$shipping_item = $order->get_item( $order_item_shipping_id );
						$item->set_order_id( $order->get_id() );
						// Update shipping cost and method title.
						$item->set_props( array(
							'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
							'method_id' => $method_id,
							'total' => wc_format_decimal( $shipment_cost ),
						) );
						$item->save();
						$this->calculate_shipping();
					} else {
						// Update shipping cost and method title.
						$r = $order->update_shipping( $order_item_shipping_id, array(
							'method_title' => 'Mercado Envios - ' . $shipment_name . $free_shipping_text,
							'method_id' => $method_id,
							'cost' => wc_format_decimal( $shipment_cost )
						) );
					}
					// WTF?
					// https://docs.woocommerce.com/wc-apidocs/source-class-WC_Abstract_Order.html#541
					// FORCE UPDATE SHIPPING
					$order->set_total( wc_format_decimal( $shipment_cost ) , 'shipping' );
					// Update total order.
					$order->set_total(
						wc_format_decimal( $order->get_subtotal() )
						+ wc_format_decimal( $order->get_total_shipping() )
						+ wc_format_decimal( $order->get_total_tax() )
						- wc_format_decimal( $order->get_total_discount() )
					);
					// Update additional info.
					wc_update_order_item_meta( $order_item_shipping_id, 'shipping_method_id', $shipping_method_id );
					wc_update_order_item_meta( $order_item_shipping_id, 'free_shipping', $free_shipping_status );
					$access_token = $this->mp->get_access_token();
					$request = array(
						'uri' => '/shipments/' . $shipment_id,
						'params' => array(
							'access_token' => $access_token
						)
					);
					$shipments_data = MeliRestClient::get( $request, '' );
					switch ( $shipments_data['response']['substatus'] ) {
						case 'ready_to_print':
							$substatus_description = __( 'Tag ready to print', 'woo-mercado-pago-module' );
							break;
						case 'printed':
							$substatus_description = __( 'Tag printed', 'woo-mercado-pago-module' );
							break;
						case 'stale':
							$substatus_description = __( 'Unsuccessful', 'woo-mercado-pago-module' );
							break;
						case 'delayed':
							$substatus_description = __( 'Delayed shipping', 'woo-mercado-pago-module' );
							break;
						case 'receiver_absent':
							$substatus_description = __( 'Missing recipient for delivery', 'woo-mercado-pago-module' );
							break;
						case 'returning_to_sender':
							$substatus_description = __( 'In return to sender', 'woo-mercado-pago-module' );
							break;
						case 'claimed_me':
							$substatus_description = __( 'Buyer initiates complaint and requested a refund.', 'woo-mercado-pago-module' );
							break;
						default:
							$substatus_description = $shipments_data['response']['substatus'];
							break;
					}
					if ( $substatus_description == '' ) {
						$substatus_description = $shipments_data['response']['status'];
					}
					$order->add_order_note( 'Mercado Envios: ' . $substatus_description );
					$this->log->add(
						$this->id,
						'[check_mercado_envios] - Mercado Envios - shipments_data : ' .
						json_encode( $shipments_data, JSON_PRETTY_PRINT )
					);
					// Add tracking number in meta data to use in order page.
					update_post_meta( $order_id, '_mercadoenvios_tracking_number', $shipments_data['response']['tracking_number'] );
					// Add shipiment_id in meta data to use in order page.
					update_post_meta( $order_id, '_mercadoenvios_shipment_id', $shipment_id );
					// Add status in meta data to use in order page.
					update_post_meta( $order_id, '_mercadoenvios_status', $shipments_data['response']['status'] );
					// Add substatus in meta data to use in order page.
					update_post_meta( $order_id, '_mercadoenvios_substatus', $shipments_data['response']['substatus'] );
					// Send email to customer.
					$tracking_id = $shipments_data['response']['tracking_number'];
					if ( isset( $order->billing_email ) && isset( $tracking_id ) ) {
						$list_of_items = array();
						$items = $order->get_items();
						foreach ( $items as $item ) {
							$product = new WC_product( $item['product_id'] );
							if ( method_exists( $product, 'get_description' ) ) {
								$product_title = WC_Woo_Mercado_Pago_Module::utf8_ansi(
									$product->get_name()
								);
							} else {
								$product_title = WC_Woo_Mercado_Pago_Module::utf8_ansi(
									$product->post->post_title
								);
							}
							array_push( $list_of_items, $product_title . ' x ' . $item['qty'] );
						}
						wp_mail(
							$order->billing_email,
							__( 'Order', 'woo-mercado-pago-module' ) . ' ' . $order_id . ' - ' . __( 'Mercado Envios Tracking ID', 'woo-mercado-pago-module' ),
							__( 'Hello,', 'woo-mercado-pago-module' ) . "\r\n\r\n" .
							__( 'Your order', 'woo-mercado-pago-module' ) . ' ' . ' [ ' . implode( ', ', $list_of_items ) . ' ] ' .
							__( 'made in', 'woo-mercado-pago-module' ) . ' ' . get_site_url() . ' ' .
							__( 'used Mercado Envios as its shipment method.', 'woo-mercado-pago-module' ) . "\r\n" .
							__( 'You can track it with the following Tracking ID:', 'woo-mercado-pago-module' ) . ' ' . $tracking_id . ".\r\n\r\n" .
							__( 'Best regards.', 'woo-mercado-pago-module' )
						);
					}
				}
			}
		}
	}*/

}