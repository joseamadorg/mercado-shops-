<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php _e( 'Payment', 'woocommerce-mercadopago-module' ); ?></h2>

<p class="order_details"><?php _e( 'Please use the link below to view your banking ticket, you can print and pay in your internet banking or in a lottery retailer:', 'woocommerce-mercadopago-module' ); ?><br /><a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank"><?php _e( 'Pay the banking ticket', 'woocommerce-mercadopago-module' ); ?></a><br /><?php _e( 'After we receive the banking ticket payment confirmation, your order will be processed.', 'woocommerce-mercadopago-module' ); ?></p>
