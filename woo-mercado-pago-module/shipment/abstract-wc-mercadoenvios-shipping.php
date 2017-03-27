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

include_once dirname( __FILE__ ) . '/../mercadopago/sdk/lib/mercadopago.php';


abstract class WC_MercadoEnvios_Shipping extends WC_Shipping_Method {

  protected $shipments_id = array();

  /**
  * Constructor.
  */
  public function __construct( $instance_id = 0 ) {


    $this->instance_id 			     = absint( $instance_id );
    $this->method_description    = __( 'Allow customers to pick up orders themselves. By default, when using local pickup store base taxes will apply regardless of customer address.', 'woocommerce' );
    $this->supports              = array(
      'shipping-zones',
      'instance-settings',
      'instance-settings-modal',
    );

    //log
    $this->log = new WC_Logger();
    $this->init();
  }

  /**
  * Initialize local pickup.
  */
  public function init() {

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->title = $this->get_option( 'title' );
    $this->tax_status	= $this->get_option( 'tax_status' );
    $this->cost	= $this->get_option( 'cost' );

    // Actions
    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );


  }

  /**
  * Calculate shipping function.
  */
  public function calculate_shipping( $package = array() ) {

    $checkout_standard = new WC_WooMercadoPago_Gateway();

    if($checkout_standard->get_option('enabled') != 'yes'){
      $this->log->add($this->id, "[calculate_shipping] mercado pago standard needs to be active... ");
      return;
    }

    $client_id = $checkout_standard->get_option( 'client_id' );
    $client_secret = $checkout_standard->get_option( 'client_secret' );

    $site_id = $checkout_standard->site_id;
    $shipping_method_id = $this->get_shipping_method_id($site_id);

    $this->mp = new MP(
      WC_WooMercadoPago_Module::get_module_version(),
      $client_id,
      $client_secret
    );

    //object package
    $me_package = new WC_MercadoEnvios_Package($package);
    $dimensions = $me_package->get_data();

    //set zipcode
    $zip_code = $package['destination']['postcode'];

    //height x width x length (centimeters), weight (grams)
    $params = array(
      "dimensions" => (int)$dimensions['height'] . "x" . (int)$dimensions['width'] . "x" . (int)$dimensions['length'] . "," . $dimensions['weight'] * 1000,
      "zip_code" => preg_replace( '([^0-9])', '', sanitize_text_field( $zip_code ) ),
      "item_price" => $package['contents_cost'],
      'access_token' => $this->mp->get_access_token()
    );

    if($this->get_option( 'free_shipping' ) == 'yes' ){
      $params['free_method'] = $shipping_method_id;
    }

    $response = $this->mp->get("/shipping_options", $params);

    $this->log->add($this->id, "-----> Params sent: " . json_encode($params));
    $this->log->add($this->id, "-----> Shipments Response API: " . json_encode($response));

    if($response['status'] == 200){

      // $shippiments = array();
      foreach ($response['response']['options'] as $shipping ) {

        if($shipping_method_id == $shipping['shipping_method_id']){

          $free_shipping_text = "";

          if($this->get_option( 'free_shipping' ) == 'yes' ){
            $free_shipping_text = " (" . __( 'Free Shipping', 'woocommerce' ) . ")";
          }

          $option = array(
            "label" => "Mercado Envios - " . $shipping['name'] . $free_shipping_text,
            "package" => $package,
            "cost" => (float) $shipping['cost'],
            "meta_data" => array(
              "dimensions" => $params['dimensions'],
              "shipping_method_id" => $shipping_method_id,
              "free_shipping" => $this->get_option( 'free_shipping' )
            )
          );

          $this->log->add($this->id, "-----> Optiond added: " . json_encode($option));

          $this->add_rate($option);
        }
      }

    }else{
      return false;
    }

  }
  /**
  * Replace comma by dot.
  *
  * @param  mixed $value Value to fix.
  *
  * @return mixed
  */
  private function fix_format( $value ) {
    $value = str_replace( ',', '.', $value );

    return $value;
  }

  /**
  * Init form fields.
  */
  public function init_form_fields() {
    $this->instance_form_fields = array(
      'enabled' => array(
        'title'   => __( 'Enable/Disable', 'woocommerce-mercado-envios' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable this shipping method', 'woocommerce-mercado-envios' ),
        'default' => 'yes',
      ),

      'title' => array(
        'title'       => __( 'Mercado Envios', 'woocommerce-mercado-envios' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-mercado-envios' ),
        'default'     => __( 'Mercado Envios', 'woocommerce-mercado-envios' ),
        'desc_tip'    => true,
      ),

      'free_shipping' => array(
        'title'   => __( 'Free Shipping', 'woocommerce-mercado-envios' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable free shipping for this shipping method', 'woocommerce-mercado-envios' ),
        'default' => 'no',
        )
      );
    }

    /**
    * Return shipping method id Mercado Envios
    */

    public function get_shipping_method_id($site_id){
      return $this->shipments_id[$site_id];
    }


  }
