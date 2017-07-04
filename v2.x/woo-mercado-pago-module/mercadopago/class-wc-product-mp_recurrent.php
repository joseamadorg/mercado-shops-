<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'add_meta_boxes', 'add_meta_boxes' );
function add_meta_boxes() {
	add_meta_box(
		'woocommerce-mp-order-action-refund',
		__( 'Mercado Pago Subscription', 'woocommerce-mercadopago-module' ),
		'mp_subscription_order_refund_cancel_box',
		'shop_order',
		'side',
		'default'
	);
}

function mp_subscription_order_refund_cancel_box() {

	global $post;
	$order = wc_get_order( $post->ID );
	$order_id = trim( str_replace( '#', '', $order->get_order_number() ) );

	$payments = get_post_meta(
		$order_id,
		'_Mercado_Pago_Sub_Payment_IDs',
		true
	);

	$options = '';
	if ( ! empty( $payments ) ) {
		$payment_structs = array();
		$payment_ids = explode( ', ', $payments );
		foreach ( $payment_ids as $p_id ) {
			$options .= '<option value="' . $p_id . '">' . $p_id . '</option>';
		}
	}

	if ( $options == '' ) {
		return;
	}

	$domain = get_site_url() . '/index.php' . '/woocommerce-mercadopago-module/';
	$domain .= '?wc-api=WC_WooMercadoPagoSubscription_Gateway';
	$subscription_js = '<script type="text/javascript">
		( function() {
			var MPSubscription = {}
			MPSubscription.callSubscriptionCancel = function () {
				var url = "' . $domain . '";
				url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
				url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
				url += "&action_mp_payment_action=cancel";
				document.getElementById("sub_pay_cancel_btn").disabled = true;
				MPSubscription.AJAX({
					url: url,
					method : "GET",
					timeout : 5000,
					error: function() {
						document.getElementById("sub_pay_cancel_btn").disabled = false;
						alert("' . __( 'This operation could not be completed.', 'woocommerce-mercadopago-module' ) . '");
					},
					success : function ( status, data ) {
						document.getElementById("sub_pay_cancel_btn").disabled = false;
						var mp_status = data.status;
						var mp_message = data.message;
						if (data.status == 200) {
							alert("' . __( 'Operation successfully completed.', 'woocommerce-mercadopago-module' ) . '");
						} else {
							alert(mp_message);
						}
					}
				});
			}
			MPSubscription.callSubscriptionRefund = function () {
				var url = "' . $domain . '";
				url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
				url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
				url += "&action_mp_payment_action=refund";
				document.getElementById("sub_pay_refund_btn").disabled = true;
				MPSubscription.AJAX({
					url: url,
					method : "GET",
					timeout : 5000,
					error: function() {
						document.getElementById("sub_pay_refund_btn").disabled = false;
						alert("' . __( 'This operation could not be completed.', 'woocommerce-mercadopago-module' ) . '");
					},
					success : function ( status, data ) {
						document.getElementById("sub_pay_refund_btn").disabled = false;
						var mp_status = data.status;
						var mp_message = data.message;
						if (data.status == 200) {
							alert("' . __( 'Operation successfully completed.', 'woocommerce-mercadopago-module' ) . '");
						} else {
							alert(mp_message);
						}
					}
				});
			}
			MPSubscription.AJAX = function( options ) {
				var useXDomain = !!window.XDomainRequest;
				var req = useXDomain ? new XDomainRequest() : new XMLHttpRequest()
				var data;
				options.url += ( options.url.indexOf( "?" ) >= 0 ? "&" : "?" );
				options.requestedMethod = options.method;
				if ( useXDomain && options.method == "PUT" ) {
					options.method = "POST";
					options.url += "&_method=PUT";
				}
				req.open( options.method, options.url, true );
				req.timeout = options.timeout || 1000;
				if ( window.XDomainRequest ) {
					req.onload = function() {
						data = JSON.parse( req.responseText );
						if ( typeof options.success === "function" ) {
							options.success( options.requestedMethod === "POST" ? 201 : 200, data );
						}
					};
					req.onerror = req.ontimeout = function() {
						if ( typeof options.error === "function" ) {
							options.error( 400, {
								user_agent:window.navigator.userAgent, error : "bad_request", cause:[]
							});
						}
					};
					req.onprogress = function() {};
				} else {
					req.setRequestHeader( "Accept", "application/json" );
					if ( options.contentType ) {
						req.setRequestHeader( "Content-Type", options.contentType );
					} else {
						req.setRequestHeader( "Content-Type", "application/json" );
					}
					req.onreadystatechange = function() {
						if ( this.readyState === 4 ) {
							if ( this.status >= 200 && this.status < 400 ) {
								// Success!
								data = JSON.parse( this.responseText );
								if ( typeof options.success === "function" ) {
									options.success( this.status, data );
								}
							} else if ( this.status >= 400 ) {
								data = JSON.parse( this.responseText );
								if ( typeof options.error === "function" ) {
									options.error( this.status, data );
								}
							} else if ( typeof options.error === "function" ) {
								options.error( 503, {} );
							}
						}
					};
				}
				if ( options.method === "GET" || options.data == null || options.data == undefined ) {
					req.send();
				} else {
					req.send( JSON.stringify( options.data ) );
				}
			}

			this.MPSubscription = MPSubscription;

		} ).call();

	</script>';

	$subscription_meta_box = '<table>' .
		'<tr class="total">' .
			'<td><label for="payment_id" style="margin-right:1px;">' .
				__( 'Payment ID:', 'woocommerce-mercadopago-module' ) .
			'</label></td>' .
			'<td><select id="payment_id" name="refund_payment_id" style="margin-left:1px;">' .
				$options .
			'</select></td>' .
		'</tr>' .
		'<tr class="total">' .
			'<td><label for="payment_amount" style="margin-right:1px;">' .
				__( 'Amount:', 'woocommerce-mercadopago-module' ) .
			'</label></td>' .
			'<td><input type="number" class="text amount_input" id="payment_amount" value="0" name="payment_amount"' .
				' placeholder="Decimal" min="0" step="0.01" value="0.00" style="width:117px; margin-left:1px;"' .
				' ng-pattern="/^[0-9]+(\.[0-9]{1,2})?$/"/>' .
			'</td>' .
		'</tr>' .
		'<tr class="total">' .
			'<td><input onclick="MPSubscription.callSubscriptionRefund();" type="button"' .
				' id="sub_pay_refund_btn" class="button button-primary" style="margin-left:1px; margin-top:2px;"' .
				' name="refund" value="' . __( 'Refund Payment', 'woocommerce-mercadopago-module' ) .
				'" style="margin-right:1px;"></td>' .
			'<td><input onclick="MPSubscription.callSubscriptionCancel();" type="button"' .
				' id="sub_pay_cancel_btn" class="button button-primary" style="margin-right:1px; margin-top:2px;"' .
				' name="cancel" value="' . __( 'Cancel Payment', 'woocommerce-mercadopago-module' ) .
				'" style="margin-left:1px;"></td>' .
		'</tr>' .
	'</table>';

	echo $subscription_js . $subscription_meta_box;

}

// Makes the recurrent product individually sold
add_filter( 'woocommerce_is_sold_individually', 'default_no_quantities', 10, 2 );
function default_no_quantities( $individually, $product ) {
	if ( method_exists( $product, 'get_id' ) ) {
		$product_id = $product->get_id();
	} else {
		$product_id = $product->id;
	}
	$is_recurrent = get_post_meta( $product_id, '_mp_recurring_is_recurrent', true );
	if ( $is_recurrent == 'yes' ) {
		$individually = true;
	}
	return $individually;
}

// Prevent selling recurrent products together with other products
add_action( 'woocommerce_check_cart_items', 'check_recurrent_product_singularity' );
function check_recurrent_product_singularity() {
	global $woocommerce;
	$w_cart = $woocommerce->cart;
	if ( ! isset( $w_cart ) ) {
		return;
	}
	$items = $w_cart->get_cart();
	if ( sizeof( $items ) > 1 ) {
		foreach ( $items as $cart_item_key => $cart_item ) {
			$is_recurrent = get_post_meta( $cart_item['product_id'], '_mp_recurring_is_recurrent', true );
			if ( $is_recurrent == 'yes' ) {
				wc_add_notice(
					__( 'A recurrent product is a signature that should be bought isolated in your cart. Please, create separated orders.', 'woocommerce-mercadopago-module' ),
					'error'
				);
			}
		}
	}
}

// Validate product date availability.
add_filter( 'woocommerce_is_purchasable', 'filter_woocommerce_is_purchasable', 10, 2 );
function filter_woocommerce_is_purchasable( $purchasable, $product ) {
	if ( method_exists( $product, 'get_id' ) ) {
		$product_id = $product->get_id();
	} else {
		$product_id = $product->id;
	}
	// skip this check if product is not a subscription
	$is_recurrent = get_post_meta( $product_id, '_mp_recurring_is_recurrent', true );
	if ( $is_recurrent !== 'yes' ) {
		return $purchasable;
	}
	$today_date = date( 'Y-m-d' );
	$end_date = get_post_meta( $product_id, '_mp_recurring_end_date', true );
	// If there is no date, we should just return the original value.
	if ( ! isset( $end_date ) || empty( $end_date ) ) {
		return $purchasable;
	}
	// If end date had passed, this product is no longer available.
	$days_diff = ( strtotime( $today_date ) - strtotime( $end_date ) ) / 86400;
	if ( $days_diff >= 0 ) {
		return false;
	}
	return $purchasable;
}

// Add the settings under 'general' sub-menu.
add_action( 'woocommerce_product_options_general_product_data', 'mp_add_recurrent_settings' );
function mp_add_recurrent_settings() {

	//global $woocommerce, $post, $thepostid;
	wp_nonce_field( 'woocommerce_save_data', 'woocommerce_meta_nonce' );
	//$thepostid = $post->ID;

	echo '<div class="options_group show_if_simple">';

		woocommerce_wp_checkbox(
			array(
				'id' => '_mp_recurring_is_recurrent',
				'label' => __( 'Recurrent Product', 'woocommerce-mercadopago-module' ),
				'description' => __( 'Make this product a subscription.', 'woocommerce-mercadopago-module' )
			)
		);

		woocommerce_wp_text_input(
			array(
				'id' => '_mp_recurring_frequency',
				'label' => __( 'Frequency', 'woocommerce-mercadopago-module' ),
				'placeholder' => '1',
				'desc_tip' => 'true',
				'description' => __( 'Amount of time (in days or months) for the execution of the next payment.', 'woocommerce-mercadopago-module' ),
				'type' => 'number'
			)
		);

		woocommerce_wp_select(
			array(
				'id' => '_mp_recurring_frequency_type',
				'label' => __( 'Frequency type', 'woocommerce-mercadopago-module' ),
				'desc_tip' => 'true',
				'description' => __( 'Indicates the period of time.', 'woocommerce-mercadopago-module' ),
				'options' => array(
					'days' => __( 'Days', 'woocommerce-mercadopago-module' ),
					'months' => __( 'Months', 'woocommerce-mercadopago-module' )
				)
			)
		);

		woocommerce_wp_text_input(
			array(
				'id' => '_mp_recurring_end_date',
				'label' => __( 'End date', 'woocommerce-mercadopago-module' ),
				'placeholder' => _x( 'YYYY-MM-DD', 'placeholder', 'woocommerce-mercadopago-module' ),
				'desc_tip' => 'true',
				'description' => __( 'Deadline to generate new charges. Defaults to never if blank.', 'woocommerce-mercadopago-module' ),
				'class' => 'date-picker',
				'custom_attributes' => array( 'pattern' => "[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" )
			)
		);

	echo '</div>';
}

// Persists the options saved in product metadata.
add_action( 'woocommerce_process_product_meta', 'mp_save_recurrent_settings' );
function mp_save_recurrent_settings( $post_id ) {

	$_mp_recurring_is_recurrent = $_POST['_mp_recurring_is_recurrent'];
	if ( ! empty( $_mp_recurring_is_recurrent ) ) {
		update_post_meta( $post_id, '_mp_recurring_is_recurrent', esc_attr( $_mp_recurring_is_recurrent ) );
	} else {
		update_post_meta( $post_id, '_mp_recurring_is_recurrent', esc_attr( null ) );
	}

	$_mp_recurring_frequency = $_POST['_mp_recurring_frequency'];
	if ( ! empty( $_mp_recurring_frequency ) ) {
		update_post_meta( $post_id, '_mp_recurring_frequency', esc_attr( $_mp_recurring_frequency ) );
	} else {
		update_post_meta( $post_id, '_mp_recurring_frequency', esc_attr( 1 ) );
	}

	$_mp_recurring_frequency_type = $_POST['_mp_recurring_frequency_type'];
	if ( ! empty( $_mp_recurring_frequency_type ) ) {
		update_post_meta( $post_id, '_mp_recurring_frequency_type', esc_attr( $_mp_recurring_frequency_type ) );
	} else {
		update_post_meta( $post_id, '_mp_recurring_frequency_type', esc_attr( 'days' ) );
	}

	$_mp_recurring_end_date = $_POST['_mp_recurring_end_date'];
	if ( ! empty( $_mp_recurring_end_date ) ) {
		update_post_meta( $post_id, '_mp_recurring_end_date', esc_attr( $_mp_recurring_end_date ) );
	} else {
		update_post_meta( $post_id, '_mp_recurring_end_date', esc_attr( null ) );
	}

}
