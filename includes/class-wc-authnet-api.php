<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Authnet_API class.
 *
 * Communicates with Authorize.net API.
 */
class WC_Authnet_API {

	private static $login_id = '';
	private static $transaction_key = '';
	private static $free_api_method = 'aim';
	private static $testmode;
	private static $logging;
	private static $debugging;
	private static $statement_descriptor;
	private static $better_error_messages;

	const LIVE_URL = 'https://api.authorize.net/xml/v1/request.api';
	const SANDBOX_URL = 'https://apitest.authorize.net/xml/v1/request.api';

	/**
	 * Set API Login ID.
	 *
	 * @param string $login_id
	 */
	public static function set_login_id( $login_id ) {
		self::$login_id = $login_id;
	}

	/**
	 * Set Transaction Key.
	 *
	 * @param string $transaction_key
	 */
	public static function set_transaction_key( $transaction_key ) {
		self::$transaction_key = $transaction_key;
	}

	/**
	 * Set free API method.
	 *
	 * @param string $free_api_method
	 */
	public static function set_free_api_method( $free_api_method ) {
		self::$free_api_method = $free_api_method;
	}

	public static function set_testmode( $testmode ) {
		self::$testmode = $testmode;
	}

	public static function set_logging( $logging ) {
		self::$logging = $logging;
	}

	public static function set_debugging( $debugging ) {
		self::$debugging = $debugging;
	}

	public static function set_statement_descriptor( $statement_descriptor ) {
		self::$statement_descriptor = $statement_descriptor;
	}

	/**
	 * Get API Login ID
	 * @return string
	 */
	public static function get_login_id() {
		if ( ! self::$login_id ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['login_id'] ) ) {
				self::set_login_id( $options['login_id'] );
			}
		}

		return self::$login_id;
	}

	/**
	 * Get Transaction Key.
	 * @return string
	 */
	public static function get_transaction_key() {
		if ( ! self::$transaction_key ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['transaction_key'] ) ) {
				self::set_transaction_key( $options['transaction_key'] );
			}
		}

		return self::$transaction_key;
	}

	/**
	 * Get free API method.
	 * @return string
	 */
	public static function get_free_api_method() {
		$options = get_option( 'woocommerce_authnet_settings' );

		if ( isset( $options['free_api_method'] ) ) {
			self::set_free_api_method( $options['free_api_method'] );
		} else {
			self::set_free_api_method( 'aim' );
		}

		return self::$free_api_method;
	}

	public static function is_testmode() {
		if ( ! is_bool( self::$testmode ) ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['testmode'] ) ) {
				self::set_testmode( $options['testmode'] === 'yes' );
			}
		}

		return self::$testmode;
	}

	public static function is_logging() {
		if ( ! is_bool( self::$logging ) ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['logging'] ) ) {
				self::set_logging( $options['logging'] === 'yes' );
			}
		}

		return self::$logging;
	}

	public static function is_debugging() {
		if ( ! is_bool( self::$debugging ) ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['debugging'] ) ) {
				self::set_debugging( $options['debugging'] === 'yes' );
			}
		}

		return self::$debugging;
	}

	public static function get_statement_descriptor() {
		if ( ! self::$statement_descriptor ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['statement_descriptor'] ) ) {
				self::set_statement_descriptor( $options['statement_descriptor'] );
			} else {
				self::set_statement_descriptor( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			}
		}

		return self::$statement_descriptor;
	}

	public static function set_better_error_messages( $use_better_error_messages ) {
		self::$better_error_messages = $use_better_error_messages;
	}

	/**
	 * Determine whether to use the default Authorize.net error messages which can sometimes be overly verbose and
	 * technical and does not give the user a good idea of what went wrong or use our own error messages which are
	 * better.
	 *
	 * @return bool
	 */
	public static function use_better_error_messages() {
		if ( ! is_bool( self::$better_error_messages ) ) {
			$options = get_option( 'woocommerce_authnet_settings' );

			if ( isset( $options['better_error_messages'] ) ) {
				self::set_better_error_messages( $options['better_error_messages'] === 'yes' );
			} else {
				self::set_better_error_messages( false );
			}
		}

		return self::$better_error_messages;
	}

	public static function execute( $request_method, $payment_args = array() ) {

		$request_url = self::is_testmode() ? self::SANDBOX_URL : self::LIVE_URL;
		$request_url = apply_filters( 'wc_authnet_request_url', $request_url );

		$auth_params = array(
			'merchantAuthentication' => array(
				'name'           => self::get_login_id(),
				'transactionKey' => self::get_transaction_key(),
			),
		);
		$auth_params = apply_filters( 'wc_authnet_api_keys', $auth_params );

		$request_args = array(
			$request_method => array_merge( $auth_params, $payment_args ),
		);

		// Setting custom timeout for the HTTP request
		add_filter( 'http_request_timeout', array( 'WC_Authnet_API', 'http_request_timeout' ), 9999 );

		$args = array(
			'headers' => array(	'Content-Type' => 'application/json' ),
			'body' 	  => json_encode( $request_args ),
		);
		$response = wp_remote_post( $request_url, $args );

		if( ! is_wp_error( $response ) ) {
			$response = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', wp_remote_retrieve_body( $response ) );
			$result   = json_decode( wc_clean( wp_unslash( $response ) ), true );
		} else {
			$result = $response;
		}

		if( empty( $result ) ) {
			self::log( "Empty Response. Trying without the wp_unslash." );
			$result   = json_decode( wc_clean( $response ), true );
		}

		$gateway_debug = ( self::is_logging() && self::is_debugging() );

		// Saving to Log here
		if ( $gateway_debug ) {
			$message = sprintf( "\nPosting to: \n%s\nRequest: \n%s\nResponse: \n%s", $request_url, print_r( $request_args, 1 ), print_r( $result, 1 ) );
			self::log( $message );
		}

		remove_filter( 'http_request_timeout', array( 'WC_Authnet_API', 'http_request_timeout' ), 9999 );

		if ( $result == null ) {
			return new WP_Error( 'cannot_connect', __( 'Unable to process request.', 'wc-authnet' ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$use_better_error_messages    = self::use_better_error_messages();
		$response_codes = self::api_response_codes();

		if ( ! empty( $result['transactionResponse']['errors'] ) ) {
			$error_messages  = $result['transactionResponse']['errors'];
			$error_code      = (string) $error_messages[0]['errorCode'];
			$default_message = ( $use_better_error_messages && isset( $response_codes[ $error_code ] ) ) ? $response_codes[ $error_code ] : $error_messages[0]['errorText'];
			$message         = apply_filters( 'wc_authnet_error_message', $default_message, $error_messages );
			$message         = apply_filters( 'wc_authnet_error_message_insert_specific_api_response_code', $message, $error_code, $result['transactionResponse'] );
			return new WP_Error( $error_code, $message, $result['transactionResponse'] );
		} elseif ( $result['messages']['resultCode'] != "Ok" ) {
			$error_messages = $result['messages']['message'];
			self::log( 'Error: Request Failed. ' . $error_messages[0]['code'] . ' - ' . $error_messages[0]['text'] );
			return new WP_Error( $error_messages[0]['code'], apply_filters( 'wc_authnet_error_message', $error_messages[0]['text'], $error_messages ) );
		} elseif ( isset( $result['transactionResponse']['responseCode'] ) && '1' !== (string) $result['transactionResponse']['responseCode'] ) {
			$response_code  = (string) $result['transactionResponse']['responseCode'];
			// not all API responses that indicate an error have filled in errors array. responseCode 4 which indicates that
			// a credit card has been flagged by the fraud detection system as possibly lost or stolen does not have an errors array.
			$error_messages = isset( $result['transactionResponse']['errors'] ) ? $result['transactionResponse']['errors'] : array();
			self::log( 'Error: Transaction not approved. Response code: ' . $response_code );

			if ( $use_better_error_messages && isset( $response_codes[ $response_code ] ) ) {
				$default_message = $response_codes[ $response_code ];
			} else {
				$default_message = ! empty( $error_messages[0]['errorText'] ) ? $error_messages[0]['errorText'] : __( 'Transaction not approved.', 'wc-authnet' );
			}

			$message = apply_filters( 'wc_authnet_error_message', $default_message, $error_messages );
			$message = apply_filters( sprintf( 'wc_authnet_error_message_%s', $response_code ), $default_message, $error_messages );
			return new WP_Error( $response_code, $message, $result['transactionResponse'] );
		} else {
			self::log( 'Request was successful.' );
		}

		return $result;

	}

	/**
	 * Logs
	 *
	 * @since 6.0.0
	 * @version 6.0.0
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		if ( self::is_logging() ) {
			WC_Authnet_Logger::log( $message );
		}
	}

	public static function http_request_timeout( $timeout_value ) {
		return 45; // 45 seconds. Too much for production, only for testing.
	}

	public static function api_response_codes() {
		return array(
			// Top-level response codes (transactionResponse.responseCode):
			// 1=Approved,
			// 2=Declined,
			// 3=Error,
			// 4=Held for Review/credit card has been flagged by fraud system as possibly lost or stolen
			'2'   => __( 'Your payment was declined by your card issuer. Please check your payment details and try again, or contact your bank for more information.', 'wc-authnet' ),
			'3'   => __( 'There was an error processing your payment. Please try again or contact us for assistance.', 'wc-authnet' ),
			'4'   => __( 'Your payment could not be completed at this time. Please contact your bank or try a different payment method.', 'wc-authnet' ),

			// Specific error/reason codes (transactionResponse.errors[].errorCode).
			'5'   => __( 'The payment amount could not be accepted. Please review your order and try again.', 'wc-authnet' ),
			'6'   => __( 'The card number appears to be invalid. Please check the card number and try again.', 'wc-authnet' ),
			'7'   => __( 'The card expiration date appears to be invalid. Please check the expiration date and try again.', 'wc-authnet' ),
			'8'   => __( 'This card appears to be expired. Please use a current card and try again.', 'wc-authnet' ),
			'11'  => __( 'This appears to be a duplicate payment attempt. Please wait a few minutes before trying again.', 'wc-authnet' ),
			'17'  => __( 'This card type is not accepted for this transaction. Please try a different card.', 'wc-authnet' ),
			'19'  => __( 'Your bank could not complete this transaction at this time. Please try again or use a different card.', 'wc-authnet' ),
			'23'  => __( 'Your bank could not complete this transaction at this time. Please try again or use a different card.', 'wc-authnet' ),
			'27'  => __( "The billing address does not match your card issuer's records. Please check your billing address and try again.", 'wc-authnet' ),
			'37'  => __( 'The card number is invalid or the card type is not accepted for this transaction. Please check the card number and try again.', 'wc-authnet' ),
			'42'  => __( 'Some required payment or billing information appears to be missing or invalid. Please review the checkout form and try again.', 'wc-authnet' ),
			'44'  => __( 'The card security code did not match. Please check the CVV/CVC on your card and try again.', 'wc-authnet' ),
			'45'  => __( 'The billing address and/or card security code did not match. Please check your billing details and security code, then try again.', 'wc-authnet' ),
			'49'  => __( 'The transaction amount exceeds the limit allowed by your card issuer. Please try a smaller amount or contact your bank.', 'wc-authnet' ),
			'57'  => __( 'Your bank could not complete this transaction at this time. Please try again or use a different card.', 'wc-authnet' ),
			'64'  => __( 'This payment could not be completed because the related transaction was not approved. Please try a different card.', 'wc-authnet' ),
			'65'  => __( 'The transaction was declined due to a card security code mismatch. Please check your CVV/CVC and try again.', 'wc-authnet' ),
			'78'  => __( 'The card security code appears to be invalid. Please check the CVV/CVC on your card and try again.', 'wc-authnet' ),
			'104' => __( 'This transaction is currently under review and has not been completed. Please do not resubmit the same payment immediately.', 'wc-authnet' ),
			'127' => __( "The billing address does not match your card issuer's records. Please check your billing address and try again.", 'wc-authnet' ),
			'295' => __( 'This card was only partially approved for the order total. Please try a different payment method.', 'wc-authnet' ),
			'312' => __( 'The card security code appears to be invalid. Please check the CVV/CVC on your card and try again.', 'wc-authnet' ),
			'315' => __( 'The card number appears to be invalid. Please check the card number and try again.', 'wc-authnet' ),
			'316' => __( 'The card expiration date appears to be invalid. Please check the expiration date and try again.', 'wc-authnet' ),
			'317' => __( 'This card appears to be expired. Please use a current card and try again.', 'wc-authnet' ),
			'318' => __( 'This appears to be a duplicate payment attempt. Please wait a few minutes before trying again.', 'wc-authnet' ),
			'325' => __( 'Some required payment or billing information appears to be missing or invalid. Please review the checkout form and try again.', 'wc-authnet' ),
			'326' => __( 'Some required payment or billing information appears to be missing or invalid. Please review the checkout form and try again.', 'wc-authnet' ),
		);
	}

}
