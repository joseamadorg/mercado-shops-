<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

_e( 'Payment', 'woocommerce-mercadopago-module' );

echo "\n\n";

echo sprintf( __( 'Payment successfully made using %s credit card in %s.', 'woocommerce-mercadopago-module' ), $card_brand, $installments . 'x' );

echo "\n\n****************************************************\n\n";
