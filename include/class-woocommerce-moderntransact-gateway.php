<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/*
* @class 	WC_Moderntransact_Gateway
* @extends  WC_Payment_Gateway
* @author 	Modern Transact
* @version  1.0.0
*/

class WC_Moderntransact_Gateway extends WC_Payment_Gateway {

  /**
   * Constructor
   */
  public function __construct() {

    $this->id                 = 'moderntransact';
    $this->icon               = apply_filters( 'woocommerce_cod_icon', '' );
    $this->method_title       = __( 'ModernTransact', 'moderntransact' );
    $this->method_description = __( 'Demo ModernTransact.', 'moderntransact' );
    $this->has_fields 		  = true;

    // Load the settings
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.
    $this->title                  = $this->get_option( 'title' );
    $this->description            = $this->get_option( 'description' );
    $this->token                  = $this->get_option( 'api_token' );
    $this->gateway_url            = $this->get_option( 'gateway_url' );
    $this->moderntransact_transaction = 'yes' === $this->get_option( 'moderntransact_trans', 'no' );
    $this->debug                  = 'yes' === $this->get_option( 'enable_debug', 'no' );

    $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
    // ....

    // Define the supported features
    $this->supports = array(
      'products',
      'subscriptions',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation',
      'subscription_amount_changes',
      'subscription_date_changes',
      'subscription_payment_method_change',
      'subscription_payment_method_change_customer',
      'subscription_payment_method_change_admin',
      'multiple_subscriptions',
      'pre-orders',
    );


    // Save settings
    if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
    else
      add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
  }

  /**
  * Initialize Gateway Settings Form Fields
  */
  public function init_form_fields() {
    $shipping_methods = array();

    if ( is_admin() ) {
      foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
        $shipping_methods[ $method->id ] = $method->get_method_title();
      }
    }

    $this->form_fields = array(
      'enabled' => array(
        'title'       => __( 'Enable/Disable', 'moderntransact' ),
        'label'       => __( 'Enable ModernTransact Payments', 'moderntransact' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'title' => array(
        'title'       => __( 'Title', 'moderntransact' ),
        'type'        => 'text',
        'description' => __( 'This provides the title which the user sees during checkout.', 'moderntransact' ),
        'default'     => __( 'ModernTransact', 'moderntransact' ),
        'desc_tip'    => true,
      ),
      'description' => array(
        'title'       => __( 'Description', 'moderntransact' ),
        'type'        => 'textarea',
        'description' => __( 'This provides the description which the user sees during checkout.', 'moderntransact' ),
        'default'     => __( 'Pay via Credit Card, we accept MasterCard, Visa, Amex.', 'moderntransact' ),
        'desc_tip'    => true,
      ),
      'api_token' => array(
        'title'       => __( 'Gateway ApiToken', 'moderntransact' ),
        'type'        => 'text',
        'description' => __( 'Please enter your ModernTransact API Token; this is needed in order to take payment.', 'moderntransact' ),
        'default'     => __( '', 'moderntransact' ),
        'desc_tip'    => true,
      ),
      // 'paycertify_trans' => array(
      //   'title' 	  => __( 'Customer Verification', 'moderntransact' ),
      //   'type'        => 'checkbox',
      //   'label'       => __( 'Enable SMS/Email verifications ', 'moderntransact' ),
      //   'description' => __( 'We recommend you use ModernTransact transaction.', 'moderntransact' ),
      //   'default'     => 'yes',
      //   'desc_tip'    => true,
      // ),
      // 'developer_options' => array(
      //   'title'       => __( 'Developer Options', 'moderntransact' ),
      //   'type'        => 'title',
      //   'description' => '',
      // ),
      // 'enable_debug' => array(
      //   'title' 	  => __( 'Debug Log', 'moderntransact' ),
      //   'type'        => 'checkbox',
      //   'label'       => __( 'Enable Logging ', 'moderntransact' ),
      //   'default'     => 'no',
      //   'description' => sprintf( __( 'Log ModernTransact events, such as requests, inside %s', 'woocommerce' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'moderntransact' ) . '</code>' ),
      // ),
    );

  }

  /**
  * Validate fields
  */
  public function validate_fields() {
    $Ret = true;

    if(isset($_POST['cardholder_name']) && $_POST['cardholder_name'] == '') {
      $Ret = false;
      wc_add_notice( __('', 'moderntransact') . '<strong>NameOnCard</strong> is a required field.', 'error' );
    }
    if(isset($_POST['cardnum']) && $_POST['cardnum'] == '') {
      $Ret = false;
      wc_add_notice( __('', 'moderntransact') . '<strong>Card Number</strong> is a required field.', 'error' );
    }
    if(isset($_POST['exp_date']) && $_POST['exp_date'] == '') {
      $Ret = false;
      wc_add_notice( __('', 'moderntransact') . '<strong>Exp Date</strong> is a required field.', 'error' );
    }
    if(isset($_POST['cvv']) && $_POST['cvv'] == '') {
      $Ret = false;
      wc_add_notice( __('', 'moderntransact') . '<strong>CVV</strong> is a required field.', 'error' );
    }

    return $Ret;
  }

  public function admin_options(){
    echo '<h3>'.__('ModernTransact Payment Gateway', 'moderntransact').'</h3>';
    echo '<p>' . sprintf( __('ModernTransact accepts all major brand credit cards payment. See our %s. If you have any questions, get in touch at support[at]moderntransact[dot]com.'), '<a href="https://payment-plugin.github.io/docs/" target="_blank">documentation</a>' ).'</p>';
    echo '<table class="form-table">';
    // Generate the HTML For the settings form.
    $this -> generate_settings_html();
    echo '</table>';
  }

  /**
  *  payment fields.
  */
  public function payment_fields(){
    $html = '';
    if($this -> description) echo wpautop(wptexturize($this -> description));
    $html.= '<table>';
      $html.= '<tbody>';
        $html.= '<tr>';
          $html.= '<td colspan="2">';
            $html.='<input type="text" placeholder="NameOnCard" name="cardholder_name" id="cardholder_name" class="form-control required">';
          $html.= '</td>';
        $html.= '</tr>';
        $html.= '<tr>';
          $html.= '<td colspan="2">';
            $html.='<input type="text" placeholder="Card Number" name="cardnum" id="cardnum" class="form-control required">';
          $html.= '</td>';
        $html.= '</tr>';
        $html.= '<tr>';
          $html.= '<td>';
            $html.='<input type="text" maxlength="4" placeholder="MMYY" name="exp_date" id="exp_date" class="form-control required">';
          $html.= '</td>';
          $html.= '<td>';
            $html.='<input type="text" maxlength="4" placeholder="CVV" name="cvv" id="cvv" class="form-control required">';
          $html.= '</td>';
        $html.= '</tr>';
      $html.= '</tbody>';
    $html.= '</table>';
    $html.= "<script type='text/javascript'>jQuery('#cardnum').payment('formatCardNumber');</script>";

    echo $html;
  }

  /**
  * process payment
  */
  public function process_payment( $order_id ) {
    global $woocommerce;
    mtransact_register_session();

    require_once plugin_dir_path( __FILE__ ) . 'class-woocommerce-moderntransact-api.php';

    if ( $woocommerce->cart->get_cart_contents_count() == 0 ) {
      wc_add_notice( __('Cart Error : ', 'moderntransact') . '<strong>Cart</strong> is empty.', 'error' );
      return;
    }
    $order = wc_get_order( $order_id );

    $_SESSION['payment'] = $_POST;

    return $this->finishPayment($order_id, []);

  }

  /**
   * Finish the order
   */
  public function finishPayment( $order_id ) {
    global $woocommerce;
    mtransact_register_session();

    $error = '';
    // $_SESSION['3ds'] = null;

    require_once plugin_dir_path( __FILE__ ) . 'class-woocommerce-moderntransact-api.php';

    if ( $woocommerce->cart->get_cart_contents_count() == 0 ) {
      return wc_add_notice( __('Cart Error : ', 'moderntransact') . '<strong>Cart</strong> is empty.', 'error' );
    }
    $order = wc_get_order( $order_id );

    $Moderntransact_Process = new ModernTransact_API( $order,  $this->settings );
    $Ret = $Moderntransact_Process->do_transaction( $_SESSION['payment'] );

    // PNRef number
    $PNRef =  $Ret['data']['PNRef'] ? $Ret['data']['PNRef'] : '';
    update_post_meta( $order_id, 'PNRef', $PNRef  );

    if( isset( $Ret['success'] ) && $Ret['success'] == 1 ) {
      $order->payment_complete();
      $order->add_order_note( __('PNRef:'.$Ret['data']['PNRef'].' payment completed', 'moderntransact') );
      // Remove cart
      $woocommerce->cart->empty_cart();

      // Return thank you redirect
      return array(
        'result'    => 'success',
        'redirect'  => $this->get_return_url( $order )
      );
    }
    else {
      $i = 1;
      foreach($Ret['error'] as $k=>$v) {
        if(count($Ret['error']) == $i )
          $join = "";
        else
          $join = ", <br>";

        $error.= $v.$join;
        $i++;
      }

      // Mark as on-hold (we're awaiting the payment)
      $order->update_status( 'failed', sprintf( __( 'Payment error: %s.', 'moderntransact' ), $error ) );
      return wc_add_notice( __('Payment Error : ', 'moderntransact') . $error , 'error' );
    }
  }

  /**
   * process_refund function.
   */
  public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
    $order = wc_get_order( $order_id );

    $Moderntransact_Process = new Moderntransact_API( $order,  $this->settings );
    $Ret = $Moderntransact_Process->refund();

    if( isset( $Ret['success'] ) && $Ret['success'] == 1 ) {
      $order->add_order_note( __('ModernTransact Refund PNRef:'.$Ret['data']['PNRef'].' payment refund completed', 'moderntransact') );
      return true;
    }
    else {
      $error = '';
      $i = 1;
      foreach($Ret['error'] as $k=>$v) {
        if(count($Ret['error']) == $i )
          $join = "";
        else
          $join = ", <br>";

        $error.= $v.$join;
        $i++;
      }
      return new WP_Error( 'refund_error', __('Payment Refund error: ', 'moderntransact' ) . $error );
    }

  }

} // end \WC_Paycertify_Gateway
