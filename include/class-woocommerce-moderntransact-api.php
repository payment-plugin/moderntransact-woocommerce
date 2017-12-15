<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/*
* @class 	Moderntransact_API
* @author 	Modern Transact
* @version  1.0.0
*/
class Moderntransact_API {

  // ENDPOINT URL
  private $url = 'https://prod.moderntransact.com';

  // SETTINGS
  protected $settings = '';

  // ORDER
  protected $order = '';

  // Gateway
  protected $gateway;

  // Compatibility
  protected $compatibility = false;

  /**
   * constructor
   *
   * @param 	$order
   * @param 	$settings
   */
  public function __construct( $order ,$settings = array() ) {
    $this->settings = $settings;
    $this->order = $order;
    $this->gateway = new WC_Moderntransact_Gateway();

    if ($settings['compatibility_mode'] === 'yes') {
      $this->compatibility = true;
      $this->url = 'https://prod.moderntransact.com';
    }
  }

  /**
   * prepare_req
   *
   * @param 	void
   * @return  array
   */
  private function prepare_req( $formParams ) {
    $params = array();
    $action = 'ProcessCreditCard';

    // Card Information ( get order meta )
    $customer_id 	= get_post_meta( $this->order->id, 'customer_id', true );
    $token 			= get_post_meta( $this->order->id, 'token', true );

    // Required
    if(isset($this->settings['api_token']) && $this->settings['api_token'] != '')
      $params['ApiToken'] = trim($this->settings['api_token']);

    if( $customer_id != '' &&  $token != '' ) {

      $action = 'ProcessStoredCard';

      $params['TransType']= 'Sale';
      $params['TokenMode']= 'DEFAULT';

      $params['CardToken']= $token ;
      $params['Amount'] 	= $this->order->get_total();
      $params['InvNum'] 	= $this->order->id;
      $params['PNRef'] 	= '';

    } else {

      // Default param
      $params['TransType'] = 'Sale';
      $params['MagData'] = '';
      $params['PNRef'] = '';
      $params['TipAmt'] = 0.00;
      $params['TaxAmt'] = 0.00;
      $params['SureChargeAmt'] = 0.00;
      $params['CashBackAmt'] = 0.00;

      // Amount
      $params['Amount'] 	= $this->order->get_total();
      $params['InvNum'] 	= $this->order->id;

      // Testing Transaction
      if(isset($this->settings['enable_testmode']) && $this->settings['enable_testmode'] == 'yes')
        $params['Ineligible'] = '1';

      if(isset($this->settings['moderntransact_trans']) && $this->settings['moderntransact_trans'] == 'yes')
        $params['moderntransactTransaction'] = 1;

      $cardholder_name = $formParams['cardholder_name'];
      $cardnum 		 = $formParams['cardnum'];
      $exp_date 		 = $formParams['exp_date'];
      $cvv			 = $formParams['cvv'];

      if( isset( $cardholder_name ) && $cardholder_name != '' )
        $params['NameOnCard'] = trim( $cardholder_name );

      if(isset( $cardnum ) && $cardnum != '')
        $params['CardNum'] = preg_replace( '/\s+/', '', $cardnum ); //remove the white spaces from

      if(isset( $exp_date ) && $exp_date != '')
        $params['ExpDate'] = trim( $exp_date );

      if(isset( $cvv ) && $cvv != '')
        $params['CVNum'] = trim( $cvv );

      // Billing Information
      if(isset( $this->order->billing_address_1 ) && $this->order->billing_address_1 != '')
        $params['Street'] = trim( $this->order->billing_address_1 );

      if(isset( $this->order->billing_city ) && $this->order->billing_city != '')
        $params['City'] = trim( $this->order->billing_city );

      if(isset( $this->order->billing_state ) && $this->order->billing_state != '')
        $params['State'] = trim( $this->order->billing_state );

      if(isset( $this->order->billing_country ) && $this->order->billing_country != '')
        $params['Country'] = trim( $this->order->billing_country );

      if(isset( $this->order->billing_postcode ) && $this->order->billing_postcode != '')
        $params['Zip'] = trim( $this->order->billing_postcode );

      // Shipping Information
      if(isset( $this->order->shipping_address_1 ) && $this->order->shipping_address_1 != '')
        $params['shippingStreet'] = trim( $this->order->shipping_address_1 );

      if(isset( $this->order->shipping_city ) && $this->order->shipping_city != '')
        $params['shippingCity'] = trim( $this->order->shipping_city );

      if(isset( $this->order->shipping_state ) && $this->order->shipping_state != '')
        $params['shippingState'] = trim( $this->order->shipping_state );

      if(isset( $this->order->shipping_country ) && $this->order->shipping_country != '')
        $params['shippingCountry'] = trim( $this->order->shipping_country );

      if(isset( $this->order->shipping_postcode ) && $this->order->shipping_postcode != '')
        $params['shippingZip'] = trim( $this->order->shipping_postcode );

      /*
      * Required for ModernTransact transaction
      */
      if(isset( $this->order->billing_email ) && $this->order->billing_email != '')
        $params['Email'] = trim( $this->order->billing_email );

      if(isset( $this->order->billing_phone ) && $this->order->billing_phone != '')
        $params['Phone'] = trim( $this->order->billing_phone );

        $params['ServerID']	 = $_SERVER['REMOTE_ADDR'];

    }

    $params['action'] = $action;

    return $params;
  }

  /**
   * validate_req
   *
   * @param 	$params
   * @return  array
   */
  private function validate_req($params) {
    $errors = array();

    if($params['Amount'] <= 0)
      $errors[] = 'Amount is required field.';

    if($params['ApiToken'] == '')
      $errors[] = 'ApiToken is required field.';

    return $errors;
  }

  /**
   * process_transaction
   *
   * @param 	$params
   * @return  array
   */
  private function process_transaction( $params ) {

    $url = $this->url . '/gateway.php';
    $params['op'] = $params['action'];

    if ($this->compatibility) {
      if ( isset( $params['action'] ) && $params['action'] != '') {
        switch (  $params['action'] ) {
          case 'ProcessCreditCard':
            $url = $this->url.'/ws/encgateway2.asmx/' .$params['action'] ;
          break;
          case 'ProcessStoredCard':
            $url = $this->url.'/ws/cardsafe.asmx/' .$params['action'] ;
          break;
        }
      }
    }

    unset( $params['action'] );
    return $this->remote_post($url, $params);
  }

  /**
   * create_customer
   *
   * @param 	$gateway
   * @return  array
   */
  public function create_customer ( $gateway ) {
    $params 	 = array();
    $action 	 = 'ManageCustomerProfile';
    $url		 = $this->url.'/ws/recurring.asmx/'.$action;
    $transType 	 = 'ADD';
    $customer_id = get_current_user_id();
    $success  	 = '';

    if(isset($this->settings['api_token']) && $this->settings['api_token'] != '')
      $params['ApiToken'] = trim($this->settings['api_token']);

    $params['TransType'] 	= 'ADD';
    $params['CustomerID'] 	= uniqid();
    $params['FirstName'] 	= wc_clean( get_user_meta( $customer_id, 'billing_first_name', true ) );
    $params['LastName'] 	= wc_clean( get_user_meta( $customer_id, 'billing_last_name', true ) );
    $params['Company'] 		= wc_clean( get_user_meta( $customer_id, 'billing_company', true ) );
    $params['Street1'] 		= wc_clean( get_user_meta( $customer_id, 'billing_address_1', true ) );
    $params['Street2'] 		= wc_clean( get_user_meta( $customer_id, 'billing_address_2', true ) );
    $params['City'] 		= wc_clean( get_user_meta( $customer_id, 'billing_city', true ) );
    $params['StateID'] 		= wc_clean( get_user_meta( $customer_id, 'billing_state', true ) );
    $params['CountryID'] 	= wc_clean( get_user_meta( $customer_id, 'billing_country', true ) ) ;
    $params['Zip'] 			= wc_clean( get_user_meta( $customer_id, 'billing_postcode', true ) );
    $params['DayPhone'] 	= wc_clean( get_user_meta( $customer_id, 'billing_phone', true ) );
    $params['Email'] 		= wc_clean( get_user_meta( $customer_id, 'billing_email', true ) );
    $params['Status'] 		= 'ACTIVE';
    $params['NameOnCard'] 	= wc_clean($_POST['cardholder_name']);
    $params['CardNum'] 		= preg_replace( '/\s+/', '', $_POST['cardnum'] ); ;
    $params['ExpDate'] 		= wc_clean($_POST['exp_date']);
    $params['TokenMode']	= 'DEFAULT';

    $response = $this->remote_post ( $url, $params );

    if(isset( $response['Result'] ) && $response['Result'] == 0) {
      $success = 1;
    }

    $Return['success'] 	= $success;
    $Return['data'] 	= $response;

    return $Return;
  }

  /**
   * remote_post
   *
   * @param 	$url
   * @param 	$params
   * @param 	$options 	for execeptional use
   * @return  array
   */
  private function remote_post( $url , $params = array(), $options = array() ) {

    $args = array(
      'body' => $params,
      'timeout' => '120',
      'sslverify' => false
    );

    $request = wp_remote_post($url, $args);
    $response = simplexml_load_string($request['body']);

    mtransact_write_log( 'req -> ' . var_export($request, true) );
    mtransact_write_log( 'url -> ' . $url );
    mtransact_write_log( 'response -> ' . var_export($response, true) );

    return json_decode(json_encode($response), true);
  }

  /**
   * do_transaction
   *
   * @param 	void
   * @return  array
   */
  public function do_transaction( $formParams ) {
    $Ret  = array();
    $error = array();
    $data = '';
    $success = 0;

    $params 	= $this->prepare_req( $formParams );
    $validate 	= $this->validate_req( $params );

    mtransact_write_log( 'parameter for process transacion -> '.print_r( $params, true ) );

    if( count( $validate ) ) {
      $error = array_merge( $error,$validate );
    } else {
      $action = $params['action'];
      $response = $this->process_transaction( $params );

      if( isset( $action ) &&  $action == 'ProcessStoredCard' ) {
        if( isset( $response['error']) ) {
          $error[] = $response['error'];
        } else {

          if( isset( $response['TransactionResult'] ) ) {

            if ( isset( $response['TransactionResult']['Result'] ) &&  $response['Result'] == 0 ) {
              $success = 1;
              $data = $response['TransactionResult'];
            }
            elseif ( isset( $response['TransactionResult']['RespMSG'] ) && $response['TransactionResult']['RespMSG'] != '' && $response['TransactionResult']['Result'] != '0') {
              $error[] = $response['TransactionResult']['RespMSG'] . ( $response['TransactionResult']['Message'] ? $response['TransactionResult']['Message'] : '' ) ;
            }
            else {
              $error[] = 'Something went wrong ! Please contact your ModernTransact administrator.';
            }

          }
        }

      } else {

        if(isset( $response['Result'] ) && $response['Result'] == 0) {
          $data = $response;
          $success = 1;
        } elseif(isset( $response['RespMSG'] ) && $response['RespMSG'] != '' && $response['Result'] != '0') {
          $error[] = $response['RespMSG'] . ( isset($response['Message']) ? $response['Message'] : '' ) ;
        } else {
          $error[] = 'Something went wrong ! Please contact your ModernTransact administrator.';
        }

      }
    }

    $Ret['success'] = $success;
    $Ret['error'] = $error;
    $Ret['data']  = $data;

    return $Ret;
  }


  /**
   * refund transaction
   *
   * @param 	void
   * @return  array
   */
  public function refund() {
    $success = 0;

    $Ret = array();

    $params = array();

    $url = $this->url.'/ws/encgateway2.asmx/ProcessCreditCard' ;

    $PNRef 	= get_post_meta( $this->order->id, 'PNRef', true );

    // Required
    if(isset($this->settings['api_token']) && $this->settings['api_token'] != '')
      $params['ApiToken'] = trim($this->settings['api_token']);

    // Amount
    $params['Amount'] 		= $this->order->get_total();
    $params['InvNum'] 		= $this->order->id;

    $params['TransType'] 	= 'Refund';
    $params['TipAmt'] 		= 0.00;
    $params['TaxAmt'] 		= 0.00;
    $params['SureChargeAmt']= 0.00;
    $params['CashBackAmt']  = 0.00;

    $response = $this->remote_post( $url,$params );

    if(isset( $response['Result'] ) && $response['Result'] == 0) {
      $data = $response;
      $success = 1;
    } elseif(isset( $response['RespMSG'] ) && $response['RespMSG'] != '' && $response['Result'] != '0') {
      $error[] = $response['RespMSG'] . ( isset($response['Message']) ? $response['Message'] : '' ) ;
    } else {
      $error[] = 'Something went wrong ! Please contact your ModernTransact administrator.';
    }

    $Ret['success'] = $success;
    $Ret['data'] 	= $data;
    $Ret['data'] 	= $error;

    return $Ret;
  }

}
?>
