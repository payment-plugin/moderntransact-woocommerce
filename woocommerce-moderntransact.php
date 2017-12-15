<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

 /**
  * Plugin Name: Modern Transact Payment Gateway
  * Plugin URI:  http://moderntransact.com/carts/woocommerce
  * Description: The plugin enables you to take credit card payments from all card brands through the Modern Transact Payment Gateway.
  * Version: 	 1.0.0
  * author 	Modern Transact
  * Author URI:  https://payment-plugin.com
  * License: 	 GPLv2
  *
  * Text Domain: moderntransact
  *
  * @class       WC_ModernTransact
  * @version     1.0.0
  * @package     WooCommerce/Classes/Payment
  * @author      Modern Transact
  */

class WC_Moderntransact {

  // Gateway
  public $gateway;

  /** @var bool Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log = false;

  /**
   * Constructor
   */
  public function __construct() {
    define( 'Moderntransact_Plugin_Url', plugin_dir_path( __FILE__ ) );

    add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
    add_action( 'wp_enqueue_scripts', array( $this, 'moderntransact_assets' ) );
  }


  /**
   * Loading assets
   */
  public function moderntransact_assets() {
    wp_enqueue_style( 'style-name',  plugins_url('assets/css/style.css', __FILE__), array()  );
    // wp_enqueue_script( 'script', plugins_url('assets/js/script.js', __FILE__) ,array('jquery'),false,true);
    wp_enqueue_script( 'creditcardvalidator', plugins_url('assets/js/jquery.payment.min.js', __FILE__),array('jquery'),false,false);
  }

  /**
   * Init function
   */
  public function init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
      add_action( 'admin_notices', array( $this, 'woocommerce_gw_fallback_notice_moderntransact') );
      return;
    }

    // Includes
    include_once( 'include/class-woocommerce-moderntransact-gateway.php' );
    include_once( 'include/class-woocommerce-moderntransact-api.php' );

    // Add ModernTransact Gateway
    add_filter( 'woocommerce_payment_gateways', array( $this, 'add_moderntransact_gateway' ) );
  }

  /**
   *  Add moderntransact_gateway to existing woocommerce gateway
   */
  public function add_moderntransact_gateway( $gateways ) {

    if ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) {
      $gateways[] = 'WC_Moderntransact_Gateway';
    } else {
      $gateways[] = 'WC_Moderntransact_Gateway';
    }
      return $gateways;
  }

  /**
   * Fallback_notice_moderntransact
   */
  public function woocommerce_gw_fallback_notice_moderntransact() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce ModernTransact Gateway depends on the last version of %s to work!', 'wcPG' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
  }
}

$woocommerce_moderntransact = new WC_ModernTransact();

/*
* Write log
*/
function mtransact_write_log( $msg, $logTime = true, $source = true ) {
  global $woocommerce_moderntransact;

  if( !$woocommerce_moderntransact->gateway->debug ){
    return false;
  }

  $logger = wc_get_logger();
  $filename = 'log.txt';

  $logger->log( 'info', $msg, array( 'source' => 'moderntransact' ) );
}

add_action( 'template_redirect', 'mtransact_override_page_template' );

function mtransact_override_page_template( $page_template )
{
  global $wp;

  $current_url = home_url(add_query_arg(array(),$wp->request));

  return;
}

function mtransact_register_session(){
    if( !session_id() )
        session_start();
}
