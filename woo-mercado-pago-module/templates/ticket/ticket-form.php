<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [http://www.mercadopago.com]
 * License - http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div width="100%" style="margin:1px; padding:36px 36px 16px 36px; background:white; ">
	<img class="logo" src="<?php echo ( $images_path . 'mplogo.png' ); ?>" width="156" height="40" />
	<?php if ( count( $payment_methods ) > 1 ) { ?>
		<img class="logo" src="<?php echo ( $images_path . 'boleto.png' ); ?>" width="90" height="40" style="float:right;"/>
	<?php } else { ?>
		<?php foreach ( $payment_methods as $payment ) { ?>
			<img class="logo" src="<?php echo $payment[ 'thumbnail' ]; ?>" width="90" height="40" style="float:right;"/> 
		<?php } ?>
	<?php } ?>
</div>
<fieldset id="mercadopago-form" style="background:white; ">
	<div style="padding:0px 36px 0px 36px;">

		<p>
			<?php echo $form_labels[ 'payment_instructions' ] ?>
			<br />
			<?php echo $form_labels[ 'ticket_note' ] ?>
		</p>
		<?php if ( count( $payment_methods ) > 1 ) { ?>
			<div class="mp-box-inputs mp-col-100">
				<!--<select id="paymentMethodId" name="mercadopago_ticket[paymentMethodId]">
					<option value="-1"> <?php /*echo $form_labels[ 'label_choose' ] . " ...";*/ ?> </option>-->
				<?php $atFirst = true; ?>
				<?php foreach ( $payment_methods as $payment ) { ?>
		  			<!--<option value="<?php /*echo $payment[ 'id' ];*/ ?>"> <?php /*echo $payment[ 'name' ];*/ ?></option>-->
	  				<div class="mp-box-inputs mp-line">
						<div id="paymentMethodId" class="mp-box-inputs mp-col-5">
	  						<input type="radio" class="input-radio" name="mercadopago_ticket[paymentMethodId]"
		  						style="height:16px; width:16px;" value="<?php echo $payment[ 'id' ]; ?>"
		  						<?php if ( $atFirst ) { ?> checked="checked" } <?php } ?> />
			  			</div>
			  			<div class="mp-box-inputs mp-col-45">
							<label>
			  					<img src="<?php echo $payment[ 'thumbnail' ]; ?>" alt="<?php echo $payment[ 'name' ]; ?>" /> 
			  					&nbsp;(<?php echo $payment[ 'name' ]; ?>)
			  				</label>
			  			</div>
	  				</div>
	  				<?php $atFirst = false; ?>
				<?php } ?>
				<!--</select>-->
			</div>
		<?php } else { ?>
			<div class="mp-box-inputs mp-col-100" style="display:none;">
				<select id="paymentMethodId" name="mercadopago_ticket[paymentMethodId]">
					<?php foreach ( $payment_methods as $payment ) { ?>
			  			<option value="<?php echo $payment[ 'id' ]; ?>"
			  				style="padding: 8px; background: url( 'http://img.mlstatic.com/org-img/MP3/API/logos/bapropagos.gif' ); ?> ) 98% 50% no-repeat;"> <?php echo $payment[ 'name' ]; ?></option>
					<?php } ?>
				</select>
			</div>
		<?php } ?>

		<div class="mp-box-inputs mp-line">
	    	<!-- <div class="mp-box-inputs mp-col-50">
	    		<input type="submit" value="Pay" id="submit"/>
	    	</div> -->
			<div class="mp-box-inputs mp-col-25">
	    		<div id="mp-box-loading">
	        	</div>
			</div>
		</div>

		<!-- utilities -->
		<div class="mp-box-inputs mp-col-100" id="mercadopago-utilities">
			<input type="hidden" id="public_key" value="<?php echo $public_key; ?>" name="mercadopago_ticket[amount]"/>
			<input type="hidden" id="site_id"  value="<?php echo $site_id; ?>" name="mercadopago_ticket[site_id]"/>
			<input type="hidden" id="amount" value="<?php echo $amount; ?>" name="mercadopago_ticket[amount]"/>
		</div>

	</div>
</fieldset>
