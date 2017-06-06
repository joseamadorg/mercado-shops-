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
class WC_WooMercadoPago_SubscriptionGateway extends WC_Payment_Gateway {

	public function __construct() {
		
		// WooCommerce fields.
		$this->mp = null;
		$this->id = 'woo-mercado-pago-subscription';
		//$this->supports = array( 'products', 'refunds' );
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) )
		);
		$this->method_title = __( 'Mercado Pago - Subscription', 'woo-mercado-pago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) ) .
		'"><br><br><strong>' .
			__( 'This service allows you to subscribe customers to subscription plans.', 'woo-mercado-pago-module' ) .
		'</strong>';

		// Mercao Pago instance.
		$this->mp = new MP(
			WC_Woo_Mercado_Pago_Module::get_module_version(),
			get_option( '_mp_client_id' ),
			get_option( '_mp_client_secret' )
		);

		// How checkout is shown.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method             = $this->get_option( 'method', 'iframe' );
		$this->iframe_width       = $this->get_option( 'iframe_width', '640' );
		$this->iframe_height      = $this->get_option( 'iframe_height', '800' );
		// How checkout redirections will behave.
		$this->success_url        = $this->get_option( 'success_url', '' );
		$this->failure_url        = $this->get_option( 'failure_url', '' );
		$this->pending_url        = $this->get_option( 'pending_url', '' );
		// How checkout payment behaves.
		$this->gateway_discount   = $this->get_option( 'gateway_discount', 0 );

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

		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);

	}

	/**
	 * Summary: Initialise Gateway Settings Form Fields.
	 * Description: Initialise Gateway settings form fields with a customized page.
	 */
	public function init_form_fields() {

		// TODO: implement and remove this
		$this->form_fields = array(
			'under_building_title' => array(
				'title' => sprintf(
					__( 'UNDER BUILDING...', 'woo-mercado-pago-module' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=mercado-pago-settings' ) ) . '">' .
					__( 'Mercado Pago Settings', 'woo-mercado-pago-module' ) .
					'</a>'
				),
				'type' => 'title'
			),
		);
		return;

		// Show message if credentials are not properly configured or country is not supported.
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
		} elseif ( get_option( '_site_id_v0', '' ) != 'MLA' && get_option( '_site_id_v0', '' ) != 'MLB' && get_option( '_site_id_v0', '' ) != 'MLM' ) {
			$this->form_fields = array(
				'unsupported_country_title' => array(
					'title' => sprintf(
						__( 'It appears that your country is not supported for this solution.<br/>Please, use another payment method or go to %s to use another credential.', 'woo-mercado-pago-module' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=mercado-pago-settings' ) ) . '">' .
						__( 'Mercado Pago Settings', 'woo-mercado-pago-module' ) .
						'</a>'
					),
					'type' => 'title'
				),
			);
			return;
		}

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

		$ipn_locale = sprintf(
			'<a href="https://www.mercadopago.com.ar/ipn-notifications" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com.br/ipn-notifications" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com.mx/ipn-notifications" target="_blank">%s</a>, ',
			__( 'Argentine', 'woocommerce-mercadopago-module' ),
			__( 'Brazil', 'woocommerce-mercadopago-module' ),
			__( 'or', 'woocommerce-mercadopago-module' ),
			__( 'Mexico', 'woocommerce-mercadopago-module' )
		);

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Subscription', 'woo-mercado-pago-module' ),
				'default' => 'no'
			),
			'checkout_options_title' => array(
				'title' => __( '--- Checkout Interface: How checkout is shown ---', 'woo-mercado-pago-module' ),
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
				'title' => __( '---  Checkout Navigation: How checkout redirections will behave ---', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'ipn_url' => array(
				'title' =>
					__( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'For this solution, you need to configure your IPN URL. You can access it in your account for your specific country in:', 'woocommerce-mercadopago-module' ) .
					'<br>' . ' %s.', $ipn_locale . '. ' . sprintf(
					__( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) .
					':<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPago_SubscriptionGateway' ) . '</code>' )
				)
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
				'title' => __( '--- Payment Options: How payment options behaves ---', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'gateway_discount' => array(
				'title' => __( 'Discount by Gateway', 'woo-mercado-pago-module' ),
				'type' => 'number',
				'description' => __( 'Give a percentual (0 to 100) discount for your customers if they use this payment gateway.', 'woo-mercado-pago-module' ),
				'default' => '0'
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
				if ( $key == 'iframe_width' ) {
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
			$infra_data['checkout_subscription'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$response = $mp->analytics_save_settings( $infra_data );
		}
		// Apply updates.
		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
		);
	}

	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS (SERVER SIDE)
	 * ========================================================================
	 */

	// Called automatically by WooCommerce, verify if Module is available to use.
	// TODO:: check
	public function is_available() {
		return false;
	}

}