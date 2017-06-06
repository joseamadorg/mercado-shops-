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
class WC_WooMercadoPago_TicketGateway extends WC_Payment_Gateway {

	public function __construct() {
		
		// WooCommerce fields.
		$this->mp = null;
		$this->id = 'woo-mercado-pago-ticket';
		$this->supports = array( 'products', 'refunds' );
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) )
		);
		$this->method_title = __( 'Mercado Pago - Ticket', 'woo-mercado-pago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) ) .
		'"><br><br><strong>' .
			__( 'We give you the possibility to adapt the payment experience you want to offer 100% in your website, mobile app or anywhere you want. You can build the design that best fits your business model, aiming to maximize conversion.', 'woo-mercado-pago-module' ) .
		'</strong>';

		// Mercao Pago instance.
		$this->mp = new MP(
			WC_Woo_Mercado_Pago_Module::get_module_version(),
			get_option( '_mp_access_token' )
		);

		// How checkout is shown.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		// How checkout payment behaves.
		$this->coupon_mode        = $this->get_option( 'coupon_mode', 'no' );
		$this->stock_reduce_mode  = $this->get_option( 'stock_reduce_mode', 'no' );
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

		// Show message if credentials are not properly configured.
		$_site_id_v1 = get_option( '_site_id_v1', '' );
		if ( empty( $_site_id_v1 ) ) {
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

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Ticket Payment Method', 'woo-mercado-pago-module' ),
				'default' => 'no'
			),
			'checkout_options_title' => array(
				'title' => __( 'Ticket Interface: How checkout is shown', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'title' => array(
				'title' => __( 'Title', 'woo-mercado-pago-module' ),
				'type' => 'text',
				'description' =>
					__( 'Title shown to the client in the ticket.', 'woo-mercado-pago-module' ),
				'default' => __( 'Mercado Pago', 'woo-mercado-pago-module' )
			),
			'description' => array(
				'title' => __( 'Description', 'woo-mercado-pago-module' ),
				'type' => 'textarea',
				'description' =>
					__( 'Description shown to the client in the ticket.', 'woo-mercado-pago-module' ),
				'default' => __( 'Pay with Mercado Pago', 'woo-mercado-pago-module' )
			),
			'payment_title' => array(
				'title' => __( 'Payment Options: How payment options behaves', 'woo-mercado-pago-module' ),
				'type' => 'title'
			),
			'coupon_mode' => array(
				'title' => __( 'Coupons', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' => __( 'Enable coupons of discounts', 'woo-mercado-pago-module' ),
				'default' => 'no',
				'description' =>
					__( 'If there is a Mercado Pago campaign, allow your store to give discounts to customers.', 'woo-mercado-pago-module' )
			),
			'stock_reduce_mode' => array(
				'title' => __( 'Stock Reduce', 'woo-mercado-pago-module' ),
				'type' => 'checkbox',
				'label' =>
					__( 'Reduce Stock in Order Generation', 'woo-mercado-pago-module' ),
				'default' => 'no',
				'description' => __( 'Enable this to reduce the stock on order creation. Disable this to reduce <strong>after</strong> the payment approval.', 'woo-mercado-pago-module' )
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
				if ( $key == 'gateway_discount') {
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
		$_site_id_v1 = get_option( '_site_id_v1', '' );
		if ( ! empty( $_site_id_v1 ) ) {
			// Create MP instance.
			$mp = new MP(
				WC_Woo_Mercado_Pago_Module::get_module_version(),
				get_option( '_mp_access_token' )
			);
			// Analytics.
			$infra_data = WC_Woo_Mercado_Pago_Module::get_common_settings();
			$infra_data['checkout_custom_ticket'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$infra_data['checkout_custom_ticket_coupon'] = ( $this->settings['coupon_mode'] == 'yes' ? 'true' : 'false' );
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