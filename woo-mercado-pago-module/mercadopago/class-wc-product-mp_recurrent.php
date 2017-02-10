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

// Adds the Recurring Product as an option in product type selector.
add_filter( 'product_type_selector', 'mp_add_recurrent_product_type' );
function mp_add_recurrent_product_type( $types ) {
    $types[ 'mp_recurrent_product' ] = __( 'Recurrent Product', 'woocommerce-mercadopago-module' );
    return $types;
}

// Creates the Mercado Pago Recurrent Product.
add_action( 'plugins_loaded', 'mp_create_recurrent_product_type' );
function mp_create_recurrent_product_type() {
    class WC_Product_Recurrent_MP extends WC_Product {
        public function __construct( $product ) {
            $this->product_type = 'mp_recurrent_product';
            parent::__construct( $product );
        }
    }
}

// Add the settings under 'general' sub-menu.
add_action( 'woocommerce_product_options_general_product_data', 'mp_add_recurrent_settings' );
function mp_add_recurrent_settings() {
    global $woocommerce, $post;
    echo '<div class="options_group show_if_mp_recurrent_product">';

    woocommerce_wp_text_input(
        array(
            'id' => 'mp_recurring_frequency',
            'label' => __( 'Frequency', 'woocommerce-mercadopago-module' ),
            'placeholder' => '1',
            'desc_tip' => 'true',
            'description' => __( 'Amount of time (in days or months) for the execution of the next payment.', 'woocommerce-mercadopago-module' ),
            'type' => 'number'
        )
    );

    woocommerce_wp_select(
        array(
            'id' => 'mp_recurring_frequency_type',
            'label' => __( 'Frequency type', 'woocommerce-mercadopago-module' ),
            'desc_tip' => 'true',
            'description' => __( 'Indicates the period of time.', 'woocommerce-mercadopago-module' ),
            'options' => array(
                '1' => __( 'Days', 'woocommerce-mercadopago-module' ),
                '2' => __( 'Months', 'woocommerce-mercadopago-module' )
            )
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => 'mp_recurring_transaction_amount',
            'label' => __( 'Transaction amount', 'woocommerce-mercadopago-module' ) .
                ' (' . get_woocommerce_currency_symbol() . ')',
            'placeholder' => wc_format_localized_price( 0 ),
            'desc_tip' => 'true',
            'description' => __( 'The amount to charge the payer each period.', 'woocommerce-mercadopago-module' ),
            'data_type' => 'price'
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => 'mp_recurring_start_date',
            'label' => __( 'Start date', 'woocommerce-mercadopago-module' ),
            'placeholder' => _x( 'YYYY-MM-DD', 'placeholder', 'woocommerce-mercadopago-module' ),
            'desc_tip' => 'true',
            'description' => __( 'First payment date (effective debit). Defaults to now if blank.', 'woocommerce-mercadopago-module' ),
            'class' => 'date-picker',
            'custom_attributes' => array( 'pattern' => "[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" )
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => 'mp_recurring_end_date',
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

    $mp_recurring_frequency = $_POST['mp_recurring_frequency'];
    if ( ! empty( $mp_recurring_frequency ) )
        update_post_meta( $post_id, 'mp_recurring_frequency', esc_attr( $mp_recurring_frequency ) );
    else
        update_post_meta( $post_id, 'mp_recurring_frequency', esc_attr( 1 ) );

    $mp_recurring_frequency_type = $_POST['mp_recurring_frequency_type'];
    if ( ! empty( $mp_recurring_frequency_type ) )
        update_post_meta( $post_id, 'mp_recurring_frequency_type', esc_attr( $mp_recurring_frequency_type ) );
    else
        update_post_meta( $post_id, 'mp_recurring_frequency_type', esc_attr( 'days' ) );

    $mp_recurring_transaction_amount = $_POST['mp_recurring_transaction_amount'];
    if ( ! empty( $mp_recurring_transaction_amount ) )
        update_post_meta( $post_id, 'mp_recurring_transaction_amount', esc_attr( $mp_recurring_transaction_amount ) );
    else
        update_post_meta( $post_id, 'mp_recurring_transaction_amount', esc_attr( 0 ) );

    $mp_recurring_start_date = $_POST['mp_recurring_start_date'];
    if ( ! empty( $mp_recurring_start_date ) )
        update_post_meta( $post_id, 'mp_recurring_start_date', esc_attr( $mp_recurring_start_date ) );
    else
        update_post_meta( $post_id, 'mp_recurring_start_date', esc_attr( null ) );

    $mp_recurring_end_date = $_POST['mp_recurring_end_date'];
    if ( ! empty( $mp_recurring_end_date ) )
        update_post_meta( $post_id, 'mp_recurring_end_date', esc_attr( $mp_recurring_end_date ) );
    else
        update_post_meta( $post_id, 'mp_recurring_end_date', esc_attr( null ) );

}

// This shows the Virtual and Downloadable checkboxes as options for this product.
add_action( 'product_type_options', 'wc_recurrent_product_type_options' );
function wc_recurrent_product_type_options( $options ) {
    $options['downloadable']['wrapper_class'] = 'show_if_simple show_if_mp_recurrent_product';
    $options['virtual']['wrapper_class'] = 'show_if_simple show_if_mp_recurrent_product';
    return $options;
}
