<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="woocommerce-message">
	<span><?php echo sprintf( __( 'Payment successfully made using %s credit card in %s.', 'woocommerce-mercadopago-module' ), '<strong>' . $card_brand . '</strong>', '<strong>' . $installments . 'x</strong>' ); ?></span>
</div>
