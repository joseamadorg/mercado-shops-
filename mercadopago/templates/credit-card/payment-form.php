<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<fieldset id="mercadopago-credit-cart-form">
	<p class="form-row form-row-first">
		<label for="mercadopago-card-holder-name"><?php _e( 'Card Holder Name', 'woocommerce-mercadopago-module' ); ?><span class="required">*</span></label>
		<input id="mercadopago-card-holder-name" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="mercadopago-card-number"><?php _e( 'Card Number', 'woocommerce-mercadopago-module' ); ?> <span class="required">*</span></label>
		<input id="mercadopago-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<p class="form-row form-row-first">
		<label for="mercadopago-card-expiry"><?php _e( 'Expiry (MM/YY)', 'woocommerce-mercadopago-module' ); ?> <span class="required">*</span></label>
		<input id="mercadopago-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="<?php _e( 'MM / YY', 'woocommerce-mercadopago-module' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="mercadopago-card-cvc"><?php _e( 'Card Code', 'woocommerce-mercadopago-module' ); ?> <span class="required">*</span></label>
		<input id="mercadopago-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="<?php _e( 'CVC', 'woocommerce-mercadopago-module' ); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<?php if ( 1 < $max_installment ) : ?>
		<p class="form-row form-row-wide">
			<label for="mercadopago-card-installments"><?php _e( 'Installments', 'woocommerce-mercadopago-module' ); ?> <span class="required">*</span></label>
			<select name="mercadopago_installments" id="mercadopago-installments" style="font-size: 1.5em; padding: 8px; width: 100%;">
				<?php
					foreach ( $installments as $number => $installment ) :
						if ( $smallest_installment > $installment['installment_amount'] ) {
							break;
						}

						$interest           = ( ( $cart_total * 100 ) < $installment['amount'] ) ? sprintf( __( '(total of %s)', 'woocommerce-mercadopago-module' ), strip_tags( wc_price( $installment['amount'] / 100 ) ) ) : __( '(interest-free)', 'woocommerce-mercadopago-module' );
						$installment_amount = strip_tags( wc_price( $installment['installment_amount'] / 100 ) );
						$installment_number = absint( $installment['installment'] );
					?>
					<option value="<?php echo $installment_number; ?>"><?php echo sprintf( __( '%dx of %s %s', 'woocommerce-mercadopago-module' ), $installment_number, $installment_amount, $interest ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php endif; ?>
</fieldset>
