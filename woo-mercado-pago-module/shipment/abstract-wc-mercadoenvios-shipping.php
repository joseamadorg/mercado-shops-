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
    $this->method_description    = __( 'Mercado Envios is a shipping method available only for payments with Mercado Pago.', 'woocommerce-mercadopago-module' );
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
    $this->free_shipping	= $this->get_option( 'free_shipping' );
    $this->show_delivery_time	= $this->get_option( 'show_delivery_time' );

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
    }else{
      $list_shipping_methods = $this->get_shipping_methods_zone_by_shipping_id($this->instance_id);


      foreach ($list_shipping_methods as $key => $shipping_object) {


        if($key == 'mercadoenvios-normal' || $key == 'mercadoenvios-express'){

          //WTF?
          $shipping_object = new $shipping_object($shipping_object->instance_id);

          if($shipping_object->get_option('free_shipping') == 'yes'){
            $temp_shipping_method_id = $shipping_object->get_shipping_method_id($checkout_standard->site_id);
            $params['free_method'] = $temp_shipping_method_id;
          }
        }
      }
    }

    $response = $this->mp->get("/shipping_options", $params);
    $this->log->add($this->id, "[calculate_shipping] Params sent: " . json_encode($params));
    $this->log->add($this->id, "[calculate_shipping] Shipments Response API: " . json_encode($response));

    if($response['status'] == 200){

      // $shippiments = array();
      foreach ($response['response']['options'] as $shipping ) {

        if($shipping_method_id == $shipping['shipping_method_id']){

          $label_free_shipping = "";

          if($this->get_option( 'free_shipping' ) == 'yes' || $shipping['cost'] == 0){
            $label_free_shipping = __( 'Free Shipping', 'woocommerce-mercadopago-module' );
          }

          $label_delivery_time = "";
          if($this->get_option( 'show_delivery_time' ) == 'yes' ){
            $days = $shipping['estimated_delivery_time']['shipping'] / 24;
            if($days <= 1){
              $label_delivery_time = "$days " . __( 'Day', 'woocommerce-mercadopago-module' ) ;
            }else{
              $label_delivery_time = "$days " . __( 'Days', 'woocommerce-mercadopago-module' ) ;;
            }
          }

          $separator = "";
          if($label_free_shipping != "" && $label_delivery_time != ""){
            $separator = " - ";
          }

          $label_info = "";
          if($label_free_shipping != "" || $label_delivery_time){
            $label_info = " ($label_delivery_time$separator$label_free_shipping)";
          }

          $option = array(
            "label" => "Mercado Envios - " . $shipping['name'] . $label_info,
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

    //force quit loop
    $mp = WC_WooMercadoPago_Module::init_mercado_pago_gateway_class();
    if(isset($mp->mercado_envios_loop) && $mp->mercado_envios_loop){
      return false;
    }

    $warning_active_shipping_methods = "";

    if($this->show_message_shipping_methods()){
      $warning_active_shipping_methods = '<img width="12" height="12" src="' .
        plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' . ' ' .
        __( 'Enable the two shipping methods the Mercado Envios (Express and Normal) for the proper functioning of the module.', 'woocommerce-mercadopago-module' );
    }

    $this->instance_form_fields = array(
      'mercado_envios_title' => array(
				'title' => __( 'Mercado Envios', 'woocommerce-mercadopago-module' ),
				'type' => 'title',
				'description' => sprintf( '%s', $warning_active_shipping_methods )
			),

      // 'enabled' => array(
      //   'title'   => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
      //   'type'    => 'checkbox',
      //   'label'   => __( 'Enable this shipping method', 'woocommerce-mercadopago-module' ),
      //   'default' => 'yes',
      // ),

      'title' => array(
        'title'       => __( 'Mercado Envios', 'woocommerce-mercadopago-module' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-mercadopago-module' ),
        'default'     => __( 'Mercado Envios', 'woocommerce-mercadopago-module' ),
        'desc_tip'    => true,
      ),

      'free_shipping' => array(
        'title'   => __( 'Free Shipping', 'woocommerce-mercadopago-module' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable free shipping for this shipping method', 'woocommerce-mercadopago-module' ),
        'default' => 'no',
      ),

      'show_delivery_time' => array(
        'title'       => __( 'Delivery Time', 'woocommerce-mercadopago-module' ),
        'type'        => 'checkbox',
        'label'       => __( 'Show estimated delivery time', 'woocommerce-mercadopago-module' ),
        'description' => __( 'Display the estimated delivery time in working days.', 'woocommerce-mercadopago-module' ),
        'desc_tip'    => true,
        'default'     => 'no',
      )

      );
    }

    /**
    * Return shipping methods by zone and shipping id
    */

    public function get_shipping_methods_zone_by_shipping_id($shipping_id){

      $shipping_zone = WC_Shipping_Zones::get_zone_by("instance_id", $shipping_id);

      // set looping shipping methods
      $mp = WC_WooMercadoPago_Module::init_mercado_pago_gateway_class();
      $mp->mercado_envios_loop = true;

      $shipping_methods_list = array();
      foreach ($shipping_zone->get_shipping_methods() as $key => $shipping_object) {
        $shipping_methods_list[$shipping_object->id] = $shipping_object;
      } //end foreach shipping

      // end looping
      $mp->mercado_envios_loop = false;

      return $shipping_methods_list;
    }

    /**
    * Validate if it is necessary to enable message
    */

    public function show_message_shipping_methods(){

      //is admin?
      if ( is_admin() ) {
        if($this->instance_id > 0){
          $shipping_methods_list = $this->get_shipping_methods_zone_by_shipping_id($this->instance_id);

          $shipping_methods = array();
          foreach ($shipping_methods_list as $key => $shipping_object) {
            $shipping_methods[$shipping_object->id] = $shipping_object->is_enabled();
          }

          if( isset($shipping_methods['mercadoenvios-normal']) && isset($shipping_methods['mercadoenvios-express']) ){

            if($shipping_methods['mercadoenvios-normal'] === true && $shipping_methods['mercadoenvios-express'] === true){
              //add settings
              $this->update_settings_api('true');

              //not display message
              return false;
            }elseif($shipping_methods['mercadoenvios-normal'] === false && $shipping_methods['mercadoenvios-express'] === false){
              //remove settings
              $this->update_settings_api('false');

              //not display message
              return false;
            }
          }

          //show message
          return true;

        }else{
          return false;
        }
      }
    }

    /**
    * Return shipping method id Mercado Envios
    */

    public function get_shipping_method_id($site_id){
      return $this->shipments_id[$site_id];
    }

    /**
    * Update settings api
    */
    public function update_settings_api($status){
      $checkout_standard = new WC_WooMercadoPago_Gateway();
      $client_id = $checkout_standard->get_option( 'client_id' );
      $client_secret = $checkout_standard->get_option( 'client_secret' );

      if($client_id != "" && $client_secret != ""){
        $this->mp = new MP(
          WC_WooMercadoPago_Module::get_module_version(),
          $client_id,
          $client_secret
        );

        //get default data
        $infra_data = WC_WooMercadoPago_Module::get_common_settings();
        $infra_data['mercado_envios'] = $status;

        //request
        $response = $this->mp->analytics_save_settings( $infra_data );

        $this->log->add(
          $this->id,
          '[update_settings_api] - analytics response: ' .
          json_encode( $response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
        );
      }

    }
  }
