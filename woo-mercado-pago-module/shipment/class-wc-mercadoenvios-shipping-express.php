<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
* Mercado Envios Shipping Method for Mercado Pago.
*
* A simple shipping method allowing free pickup as a shipping method for Mercado Pago.
*
* @class 		WC_MercadoPago_Shipping_MercadoEnvios
* @version		2.1.9
* @package		WooCommerce/Classes/Shipping
* @author 		Mercado Pago
*/


class WC_MercadoEnvios_Shipping_Express extends WC_MercadoEnvios_Shipping {


  protected $shipments_id = array(
    "MLA" => 73330,
    "MLB" => 182,
    "MLM" => 501345
  );

  /**
  * Constructor.
  */
  public function __construct( $instance_id = 0 ) {

    $this->id                    = 'mercadoenvios-express';
    $this->method_title          = __( 'Mercado Envios - Express', 'woocommerce' );
    parent::__construct( $instance_id );

  }
}
