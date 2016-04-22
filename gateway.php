<?php
/*
Plugin Name: Authorize.net Payment Gateway For WooCommerce
Description: Extends WooCommerce to Process Payments with the Authorize.net payment gateway
Version: 3.5.2
Plugin URI: https://pledgedplugins.com/products/authorize-net-payment-gateway-woocommerce/
Author: Pledged Plugins
Author URI: https://pledgedplugins.com/products/authorize-net-payment-gateway-woocommerce/
License: Under GPL2
*/

add_action( 'plugins_loaded', 'woocommerce_tech_authoaim_init', 0 );

function woocommerce_tech_authoaim_init() {

	if( !class_exists( 'WC_Payment_Gateway' ) )
		return;

	/**
	* Localisation
	*/
	load_plugin_textdomain( 'pledged', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	* Payment Gateway class
	*/
	class WC_Tech_Authoaim extends WC_Payment_Gateway {

		protected $msg = array();

		public function __construct() {

			$this->id               = 'authorizeaim';
			$this->method_title     = __( 'Authorize.net', 'pledged' );
			$this->icon             = WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/images/logo.gif';
			$this->has_fields       = true;
			$this->init_form_fields();
			$this->init_settings();
			$this->title            = $this->settings['title'];
			$this->description      = $this->settings['description'];
			$this->login            = $this->settings['login_id'];
			$this->mode             = 'false';
			$this->transaction_key  = $this->settings['transaction_key'];
			$this->success_message  = $this->settings['success_message'];
			$this->failed_message   = $this->settings['failed_message'];
			$this->liveurl          = 'https://secure2.authorize.net/gateway/transact.dll';
			$this->testurl          = 'https://test.authorize.net/gateway/transact.dll';
			$this->msg['message']   = "";
			$this->msg['class']     = "";

			if( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			add_action( 'woocommerce_receipt_authorizeaim', array( &$this, 'receipt_page' ) );
			add_action( 'woocommerce_thankyou_authorizeaim', array( &$this, 'thankyou_page' ) );

		}

		function init_form_fields() {

			$this->form_fields = array(
				'enabled'      => array(
					'title'        => __( 'Enable/Disable', 'pledged' ),
					'type'         => 'checkbox',
					'label'        => __( 'Enable This Payment Module.', 'pledged' ),
					'default'      => 'no'
				),
				'title'        => array(
					'title'        => __( 'Title:', 'pledged' ),
					'type'         => 'text',
					'description'  => __( 'This controls the title which the user sees during checkout.', 'pledged' ),
					'default'      => __( 'Authorize.net', 'pledged' )
				),
				'description'  => array(
					'title'        => __( 'Description:', 'pledged' ),
					'type'         => 'textarea',
					'description'  => __( 'This controls the description which the user sees during checkout.', 'pledged' ),
					'default'      => __( 'Pay securely by Credit or Debit Card through Authorize.net.', 'pledged' )
				),
				'login_id'     => array(
					'title'        => __( 'Login ID', 'pledged' ),
					'type'         => 'text',
					'description'  => __( 'This is your API Login ID with the gateway.', 'pledged' )
				),
				'transaction_key' => array(
					'title'        => __( 'Transaction Key', 'pledged' ),
					'type'         => 'password',
					'description'  => __( 'This is your Transaction Key.', 'pledged' )
				),
				'success_message' => array(
					'title'        => __( 'Transaction Success Message', 'pledged' ),
					'type'         => 'textarea',
					'description'  => __( 'Message to be displayed on successful transaction.', 'pledged' ),
					'default'      => __( 'Your payment has been processed successfully.', 'pledged' )
				),
				'failed_message'  => array(
					'title'        => __( 'Transaction Failed Message', 'pledged' ),
					'type'         => 'textarea',
					'description'  => __( 'Message to be displayed on failed transaction.', 'pledged' ),
					'default'      => __( 'Your transaction has been declined.', 'pledged' )
				)
			);
		}

		/**
		* Admin Panel Options
		*
		**/
		public function admin_options() {
			echo '<center><h3>' . __( 'Authorize.net Payment Gateway', 'pledged' ) . '</h3>';
			echo '<a href="https://pledgedplugins.com/"><img src="https://pledgedplugins.com/external/small.png"><br><b>We guarantee all of our products.</b></center></a>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 *  Fields for credit card entry
		**/
		function payment_fields() {
			if ( $this->description )
				echo wpautop( wptexturize( $this->description ) );
				echo '<label style="margin-right:46px; line-height:40px;">Credit Card :</label> <input type="text" name="' . $this->id . '_credircard" /><br/>';
				echo '<label style="margin-right:30px; line-height:40px;">Expiry (MMYY) :</label> <input type="text"  style="width:50px;" name="' . $this->id . '_ccexpdate" maxlength="4" /><br/>';
				echo '<label style="margin-right:89px; line-height:40px;">CCV :</label> <input type="text" name="' . $this->id . '_ccvnumber"  maxlength=4 style="width:40px;" /><br/>';
		}

		/*
		* Basic Card validation
		*/
		public function validate_fields() {
			global $woocommerce;

			if( !$this->isCreditCardNumber( $_POST[$this->id . '_credircard'] ) )
				wc_add_notice( $message = 'Credit Card Number is not valid.', $notice_type = 'error' );

			if( !$this->isCorrectExpireDate( $_POST[$this->id . '_ccexpdate'] ) )
				wc_add_notice( $message = 'Credit Card expiration date is invalid.', $notice_type = 'error' );

			if( !$this->isCCVNumber( $_POST[$this->id . '_ccvnumber'] ) )
				wc_add_notice( $message = 'CCV (Credit Card Validation Number) is not valid.', $notice_type = 'error' );
		}

		/*
		* Check card
		*/
		private function isCreditCardNumber( $toCheck ) {
			if( !is_numeric( $toCheck ) )
				return false;

			$number = preg_replace( '/[^0-9]+/', '', $toCheck );
			$strlen = strlen( $number );
			$sum    = 0;

			if( $strlen < 13 )
				return false;

			for( $i = 0; $i < $strlen; $i++ ) {
				$digit = substr( $number, $strlen - $i - 1, 1 );
				if( $i % 2 == 1 ) {
					$sub_total = $digit * 2;
					if( $sub_total > 9 ) {
						$sub_total = 1 + ( $sub_total - 10 );
					}
				} else {
					$sub_total = $digit;
				}
				$sum += $sub_total;
			}

			if( $sum > 0 AND $sum % 10 == 0 )
				return true;

			return false;
		}

		private function isCCVNumber( $toCheck ) {
			$length = strlen( $toCheck );
			return is_numeric( $toCheck ) AND $length > 2 AND $length < 5;
		}

		/*
		* Check expiry date
		*/
		private function isCorrectExpireDate( $date ) {
			if( is_numeric( $date ) && ( strlen( $date ) == 4 ) ) {
				return true;
			}
			return false;
		}

		public function thankyou_page( $order_id ) {

		}

		/**
		* Receipt Page
		**/
		function receipt_page( $order ) {
			echo '<p>' . __( 'Thank you for your order.', 'pledged' ) . '</p>';
		}

		/**
		* Process the payment and return the result
		**/
		function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );

			if( $this->mode == 'true' ) {
				$process_url = $this->testurl;
			} else {
				$process_url = $this->liveurl;
			}

			$params = $this->generate_authorizeaim_params( $order );

			$post_string = "";
			foreach( $params as $key => $value ) {
				$post_string .= "$key=" . urlencode( $value ) . "&";
			}
			$post_string = rtrim( $post_string, "& " );

			$request = curl_init( $process_url ); // initiate curl object
			curl_setopt( $request, CURLOPT_HEADER, 0 ); // set to 0 to eliminate header info from response
			curl_setopt( $request, CURLOPT_RETURNTRANSFER, 1 ); // Returns response data instead of TRUE(1)
			curl_setopt( $request, CURLOPT_POSTFIELDS, $post_string ); // use HTTP POST to send form data
			curl_setopt( $request, CURLOPT_SSL_VERIFYPEER, FALSE ); // uncomment this line if you get no gateway response.
			$post_response = curl_exec( $request ); // execute curl post and store results in $post_response
			curl_close( $request );

			$response_array = explode( '|', $post_response );


			if( count( $response_array ) > 1 ) {

				if( $response_array[0] == '1' ) {

					if( $order->status != 'completed' ) {
						$order->payment_complete( $response_array[6] );
						$woocommerce->cart->empty_cart();

						$order->add_order_note( $this->success_message . $response_array[3] . 'Transaction ID: ' . $response_array[6] );
						unset( $_SESSION['order_awaiting_payment'] );
					}
					return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );

				} else {

					$order->add_order_note( $this->failed_message . $response_array[3] );
					wc_add_notice( $message = '(Transaction Error) ' . $response_array[3], $notice_type = 'error' );
				}

			} else {

				$order->add_order_note( $this->failed_message );
				$order->update_status( 'failed' );
				wc_add_notice( $message = '(Transaction Error) Error processing payment.', $notice_type = 'error' );

			}

		}

		/**
		* Generate payment gateway link
		**/
		public function generate_authorizeaim_params( $order ) {

			$authorizeaim_args = array(
				'x_login'                  => $this->login,
				'x_tran_key'               => $this->transaction_key,
				'x_version'                => '3.1',
				'x_delim_data'             => 'TRUE',
				'x_delim_char'             => '|',
				'x_relay_response'         => 'FALSE',
				'x_type'                   => 'AUTH_CAPTURE',
				'x_method'                 => 'CC',
				'x_card_num'               => $_POST[$this->id . '_credircard'],
				'x_exp_date'               => $_POST[$this->id . '_ccexpdate'],
				'x_description'            => 'Order #'.$order->id,
				'x_amount'                 => $order->order_total,
				'x_first_name'             => $order->billing_first_name,
				'x_last_name'              => $order->billing_last_name,
				'x_company'                => $order->billing_company,
				'x_address'                => $order->billing_address_1 . ' '. $order->billing_address_2,
				'x_country'                => $order->billing_country,
				'x_phone'                  => $order->billing_phone,
				'x_state'                  => $order->billing_state,
				'x_city'                   => $order->billing_city,
				'x_zip'                    => $order->billing_postcode,
				'x_email'                  => $order->billing_email,
				'x_card_code'              => $_POST[$this->id . '_ccvnumber'],
				'x_ship_to_first_name'     => $order->shipping_first_name,
				'x_ship_to_last_name'      => $order->shipping_last_name,
				'x_ship_to_address'        => $order->shipping_address_1,
				'x_ship_to_city'           => $order->shipping_city,
				'x_ship_to_zip'            => $order->shipping_postcode,
				'x_ship_to_state'          => $order->shipping_state,
				'x_customer_ip'            => WC_Geolocation::get_ip_address(),
            );
			return $authorizeaim_args;
		}

	}

	/**
	* Add this Gateway to WooCommerce
	**/
	function woocommerce_add_tech_authoaim_gateway( $methods ) {
		$methods[] = 'WC_Tech_Authoaim';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_tech_authoaim_gateway' );

}