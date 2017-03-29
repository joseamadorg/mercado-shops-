<?php
/**
 * Admin orders actions.
 *
 * @package WooCommerce_MercadoEnvios/Admin/Orders
 * @version 2.1.9
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

		$shipment_id = get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true );
		$status = get_post_meta( $post->ID, '_mercadoenvios_status', true );

		if(isset($status) && $status != "" && $status != "pending"){
<<<<<<< HEAD
			echo '<label for="mercadoenvios_tracking_code">' . esc_html__( 'Tracking code:', 'woocommerce-mercadopago-module' ) . '</label><br />';
=======
			echo '<label for="mercadoenvios_tracking_code">' . esc_html__( 'Tracking code:', 'woocommerce-mercadoenvios' ) . '</label><br />';
>>>>>>> cf81420b26d84d5d38c8769a5b34b8c3760a78f8
			echo '<input type="text" id="mercadoenvios_tracking_code" name="mercadoenvios_tracking_code" value="' . esc_attr( get_post_meta( $post->ID, '_mercadoenvios_tracking_number', true ) ) . '" style="width: 100%;" />';

			//check exist shipment_id
			if(isset($shipment_id) && $shipment_id != ""){
				$checkout_standard = new WC_WooMercadoPago_Gateway();
				$client_id = $checkout_standard->get_option( 'client_id' );
				$client_secret = $checkout_standard->get_option( 'client_secret' );

				$this->mp = new MP(
					WC_WooMercadoPago_Module::get_module_version(),
					$client_id,
					$client_secret
				);

<<<<<<< HEAD
				echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Ticket:', 'woocommerce-mercadopago-module' ) . '</label><br />';
				echo '<a href="https://api.mercadolibre.com/shipment_labels?shipment_ids=' . esc_attr( get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true ) ) . '&savePdf=Y&access_token=' . $this->mp->get_access_token() . '" class="button-primary" target="_blank">' . esc_html__( 'Print', 'woocommerce-mercadopago-module' ) . '</a>';
			}
		}else{
			echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Shipping is pending', 'woocommerce-mercadopago-module' ) . '</label><br />';
=======
				echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Ticket:', 'woocommerce-mercadoenvios' ) . '</label><br />';
				echo '<a href="https://api.mercadolibre.com/shipment_labels?shipment_ids=' . esc_attr( get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true ) ) . '&savePdf=Y&access_token=' . $this->mp->get_access_token() . '" class="button-primary" target="_blank">' . esc_html__( 'Print', 'woocommerce-mercadoenvios' ) . '</a>';
			}
		}else{
			echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Shipping is pending', 'woocommerce-mercadoenvios' ) . '</label><br />';
>>>>>>> cf81420b26d84d5d38c8769a5b34b8c3760a78f8
		}
	}
}

new WC_MercadoEnvios_Admin_Orders();
