<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

_e( 'Payment', 'woocommerce-mercadopago-module' );

echo "\n\n";

_e( 'Please use the link below to view your banking ticket, you can print and pay in your internet banking or in a lottery retailer:', 'woocommerce-mercadopago-module' );

echo "\n";

echo esc_url( $url );

echo "\n";

_e( 'After we receive the banking ticket payment confirmation, your order will be processed.', 'woocommerce-mercadopago-module' );

echo "\n\n****************************************************\n\n";
