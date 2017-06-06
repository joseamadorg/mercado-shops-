<?php
/**
 * Admin orders actions.
 *
 * @package WooCommerce_MercadoEnvios/Admin/Orders
 * @version 2.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


include_once dirname( __FILE__ ) . '/../mercadopago/sdk/lib/mercadopago.php';

/**
 * MercadoEnvios orders.
 */
class WC_MercadoEnvios_Admin_Orders {

	/**
	 * Initialize the order actions.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
	}

	/**
	 * Register tracking code metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'wc_mercadoenvios',
			'Mercado Envios',
			array( $this, 'metabox_content' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Tracking code metabox content.
	 *
	 * @param WC_Post $post Post data.
	 */
	public function metabox_content( $post ) {

		$order = wc_get_order( $post->ID );
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'get_meta' ) ) {
			$shipment_id     = $order->get_meta( '_mercadoenvios_shipment_id' );
			$status          = $order->get_meta( '_mercadoenvios_status' );
			$tracking_number = $order->get_meta( '_mercadoenvios_tracking_number' );
		} else {
			$shipment_id     = get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true );
			$status          = get_post_meta( $post->ID, '_mercadoenvios_status', true );
			$tracking_number = get_post_meta( $post->ID, '_mercadoenvios_tracking_number', true );
		}

		if ( isset( $status ) && $status != '' && $status != 'pending' ) {
			echo '<label for="mercadoenvios_tracking_code">' . esc_html__( 'Tracking code:', 'woocommerce-mercadopago-module' ) . '</label><br />';
			echo '<input type="text" id="mercadoenvios_tracking_code" name="mercadoenvios_tracking_code" value="' . esc_attr( $tracking_number ) . '" style="width: 100%;" />';

			// Check exist shipment_id
			if ( isset( $shipment_id ) && $shipment_id != '' ) {
				$checkout_standard = new WC_WooMercadoPago_Gateway();
				$client_id = $checkout_standard->get_option( 'client_id' );
				$client_secret = $checkout_standard->get_option( 'client_secret' );

				$this->mp = new MP(
					WC_WooMercadoPago_Module::get_module_version(),
					$client_id,
					$client_secret
				);

				echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Tag:', 'woocommerce-mercadopago-module' ) . '</label><br />';
				echo '<a href="https://api.mercadolibre.com/shipment_labels?shipment_ids=' . esc_attr( $shipment_id ) . '&savePdf=Y&access_token=' . $this->mp->get_access_token() . '" class="button-primary" target="_blank">' . esc_html__( 'Print', 'woocommerce-mercadopago-module' ) . '</a>';
			}
		} else {
			echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Shipping is pending', 'woocommerce-mercadopago-module' ) . '</label><br />';
		}
	}
}

new WC_MercadoEnvios_Admin_Orders();
