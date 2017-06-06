<div class="wrap">

	<h1><?php echo esc_html( __( 'Mercado Pago Settings', 'woo-mercado-pago-module' ) ); ?></h1>
	
	<table class="form-table">
		<tr>
			<td>
				<?php echo $v0_credentials_message; ?>
				<br>
				<?php echo $v1_credentials_message; ?>
				<br>
				<?php echo $has_woocommerce_message; ?>
				<br>
				<?php echo $min_php_message; ?>
				<br>
				<?php echo $curl_message; ?>
				<br>
				<?php echo $is_ssl_message; ?>
			</td>
			<th scope="row">
				<?php echo $mp_logo; ?>
			</th>
		</tr>
	</table>
	
	<strong>
		<?php echo __( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woo-mercado-pago-module' ); ?>
	</strong>

	<table class="form-table">
		<tr>
			<th scope="row"><?php echo __( 'Payment Gateways', 'woo-mercado-pago-module' ); ?></th>
			<td><?php echo $gateway_buttons; ?></td>
		</tr>
	</table>

	<form method="post" action="" novalidate="novalidate" method="post">

		<?php settings_fields( 'mercadopago' ); ?>

		<table class="form-table" border="0.5" frame="above" rules="void">
			<tr>
				<th scope="row"><label><h3>
					<?php echo esc_html( __( 'Basic Checkout, Subscriptions', 'woo-mercado-pago-module' ) ); ?>
				</h3></label></th>
				<td><label class="description" id="tagline-description">
					<?php echo $v0_credential_locales; ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label>Client ID</label></th>
				<td>
					<input name="client_id" type="text" id="client_id" value="<?php form_option('_mp_client_id'); ?>" class="regular-text" />
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'Insert your Mercado Pago Client_id.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>Client Secret</label></th>
				<td>
					<input name="client_secret" type="password" id="client_secret" aria-describedby="tagline-description" value="<?php form_option('_mp_client_secret'); ?>" class="regular-text" />
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'Insert your Mercado Pago Client_secret.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<?php if ( ! empty ( $site_id_v0 ) ) { ?>
			<tr>
				<th scope="row"><label><?php echo __( 'Currency Conversion', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<label>
						<input class="" type="checkbox" name="currency_conversion_v0" id="currency_conversion_v0" <?php echo $is_currency_conversion_v0; ?>>
						<?php echo __( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio. This service may slow down your server as each conversion is made in the checkout moment.', 'woo-mercado-pago-module' ); ?>
					</label>
					<p class="description" id="tagline-description">
						<?php echo $currency_conversion_v0_message; ?>
					</p>
				</td>
			</tr>
			<?php } ?>
		</table>
		
		<table class="form-table" border="0.5" frame="above" rules="void">
			<tr>
				<th scope="row"><label><h3>
					<?php echo esc_html( __( 'Custom Checkout, Tickets', 'woo-mercado-pago-module' ) ); ?>
				</h3></label></th>
				<td><label class="description" id="tagline-description">
					<?php echo $v1_credential_locales; ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label>Public Key</label></th>
				<td>
					<input name="public_key" type="text" id="public_key" aria-describedby="tagline-description" value="<?php form_option('_mp_public_key'); ?>" class="regular-text" />
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'Insert your Mercado Pago Public key.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>Access Token</label></th>
				<td>
					<input name="access_token" type="password" id="access_token" aria-describedby="tagline-description" value="<?php form_option('_mp_access_token'); ?>" class="regular-text" />
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'Insert your Mercado Pago Access token.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<?php if ( ! empty ( $site_id_v1 ) ) { ?>
			<tr>
				<th scope="row"><label><?php echo __( 'Currency Conversion', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<label>
						<input class="" type="checkbox" name="currency_conversion_v1" id="currency_conversion_v1" <?php echo $is_currency_conversion_v1; ?>>
						<?php echo __( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio. This service may slow down your server as each conversion is made in the checkout moment.', 'woo-mercado-pago-module' ); ?>
					</label>
					<p class="description" id="tagline-description">
						<?php echo $currency_conversion_v1_message; ?>
					</p>
				</td>
			</tr>
			<?php } ?>
		</table>

		<table class="form-table" border="0.5" frame="above" rules="void">
			<tr>
				<th scope="row"><label><h3>
					<?php echo esc_html( __( 'Status Mapping of Payment x Order', 'woo-mercado-pago-module' ) ); ?>
				</h3></label></th>
				<td><label class="description" id="tagline-description">
					<?php echo __( 'Here you can configure details about Mercado Pago payments and WooCommerce order statuses.', 'woo-mercado-pago-module' ); ?>
					<br>
					<?php echo sprintf(
						__( 'For status mappings between payment/order you can use the defaults, or check references of %s and %s', 'woo-mercado-pago-module' ),
						'<a target="_blank" href="https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/">Mercado Pago</a>',
						'<a target="_blank" href="https://docs.woocommerce.com/document/managing-orders/">WooCommerce</a>.'
					); ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for PENDING', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_pending_map" id="order_status_pending_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_pending_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'when Mercado Pago updates a payment status to PENDING.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for APPROVED', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_approved_map" id="order_status_approved_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_approved_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to APPROVED.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for IN_PROCESS', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_inprocess_map" id="order_status_inprocess_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_inprocess_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to IN_PROCESS.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for IN_MEDIATION', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_inmediation_map" id="order_status_inmediation_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_inmediation_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to IN_MEDIATION.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for REJECTED', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_rejected_map" id="order_status_rejected_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_rejected_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to REJECTED.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for CANCELLED', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_cancelled_map" id="order_status_cancelled_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_cancelled_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to CANCELLED.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for REFUNDED', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_refunded_map" id="order_status_refunded_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_refunded_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to REFUNDED.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>
					<?php echo __( 'Status for CHARGED_BACK', 'woo-mercado-pago-module' ); ?>
				</label></th>
				<td>
					<select name="order_status_chargedback_map" id="order_status_chargedback_map">
						<?php echo WC_Woo_Mercado_Pago_Module::get_map( 'order_status_chargedback_map' ); ?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo esc_html( __( 'When Mercado Pago updates a payment status to CHARGED_BACK.', 'woo-mercado-pago-module' ) ); ?>
					</p>
				</td>
			</tr>

		</table>

		<table class="form-table" border="0.5" frame="above" rules="void">
			<tr>
				<th scope="row"><label><h3>
					<?php echo esc_html( __( 'Store Settings', 'woo-mercado-pago-module' ) ); ?>
				</h3></label></th>
				<td><label class="description" id="tagline-description">
					<?php echo __( 'Here you can place details about your store.', 'woo-mercado-pago-module' ); ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label><?php echo __( 'Statement Descriptor', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<input name="statement_descriptor" type="text" id="statement_descriptor" aria-describedby="tagline-description" value="<?php echo $statement_descriptor; ?>" class="regular-text"/>
					<p class="description" id="tagline-description">
						<?php echo esc_html(
							__( 'The description that will be shown in your customer\'s invoice.', 'woo-mercado-pago-module' )
						); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php echo __( 'Store Category', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<select name="category_id" id="category_id">
						<?php
						foreach ( $store_categories_id as $key=>$value) {
							if ( $category_id == $key ) {
								echo '<option value="' . $key . '" selected="selected">' . $value . '</option>';
							} else {
								echo '<option value="' . $key . '">' . $value . '</option>';
							}
						}
						?>
					</select>
					<p class="description" id="tagline-description">
						<?php echo $store_category_message; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php echo __( 'Store Identificator', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<input name="store_identificator" type="text" id="store_identificator" aria-describedby="tagline-description" value="<?php echo $store_identificator; ?>" class="regular-text"/>
					<p class="description" id="tagline-description">
						<?php echo esc_html(
							__( 'Please, inform a prefix to your store.', 'woo-mercado-pago-module' ) . ' ' .
							__( 'If you use your Mercado Pago account on multiple stores you should make sure that this prefix is unique as Mercado Pago will not allow orders with same identificators.', 'woo-mercado-pago-module' )
						); ?>
					</p>
				</td>
			</tr>
		</table>

		<table class="form-table" border="0.5" frame="hsides" rules="void">
			<tr>
				<th scope="row"><label><h3>
					<?php echo esc_html( __( 'Test and Debug Options', 'woo-mercado-pago-module' ) ); ?>
				</h3></label></th>
				<td><label class="description" id="tagline-description">
					<?php echo __( 'Tools for debug and testing your integration.', 'woo-mercado-pago-module' ); ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label><?php echo __( 'Debug and Log', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<label>
						<input class="" type="checkbox" name="debug_mode" id="debug_mode" <?php echo $is_debug_mode; ?>>
						<?php echo __( 'Enable log (Keep this disabled if you’re in production).', 'woo-mercado-pago-module' ); ?>
					</label>
					<p class="description" id="tagline-description">
						<?php echo sprintf(
							__( 'Register event logs of Mercado Pago, such as API requests, for', 'woo-mercado-pago-module' ) . ' %s, %s, %s, or %s.',
							WC_Woo_Mercado_Pago_Module::build_log_path_string(
								'woo-mercado-pago-basic',
								__( 'Basic Checkout', 'woo-mercado-pago-module' )
							),
							WC_Woo_Mercado_Pago_Module::build_log_path_string(
								'woo-mercado-pago-custom',
								__( 'Custom Checkout', 'woo-mercado-pago-module' )
							),
							WC_Woo_Mercado_Pago_Module::build_log_path_string(
								'woo-mercado-pago-ticket',
								__( 'Tickets', 'woo-mercado-pago-module' )
							),
							WC_Woo_Mercado_Pago_Module::build_log_path_string(
								'woo-mercado-pago-subscription',
								__( 'Subscriptions', 'woo-mercado-pago-module' )
							) . '.<br>' .
							__( 'You can access your logs in ', 'woo-mercado-pago-module' ) . '<strong>' .
							__( 'WooCommerce &gt; System Status &gt; Logs', 'woo-mercado-pago-module' ) . '</strong>. ' .
							__( 'Files are located in: ', 'woo-mercado-pago-module' ) . '<code>wordpress/wp-content/uploads/wc-logs/</code>' )
						?>
					</p>
				</td>
			</tr>
			<!--<tr>
				<th scope="row"><label><?php echo __( 'Mercado Pago Sandbox', 'woo-mercado-pago-module' ); ?></label></th>
				<td>
					<label>
						<input class="" type="checkbox" name="sandbox_mode" id="sandbox_mode" <?php echo $is_sandbox_mode; ?>>
						<?php echo __( 'Enable Mercado Pago Sandbox.', 'woo-mercado-pago-module' ); ?>
					</label>
					<p class="description" id="tagline-description">
						<?php echo esc_html(
							__( 'This option allows you to test payments inside a sandbox environment.', 'woo-mercado-pago-module' )
						); ?>
					</p>
				</td>
			</tr>-->
		</table>

		<?php do_settings_sections( 'mercadopago' ); ?>

		<?php submit_button(); ?>

	</form>

</div>