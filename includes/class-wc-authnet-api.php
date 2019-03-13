<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * WC_Authnet_API class.
 *
 * Communicates with Authorize.net API.
 */
class WC_Authnet_API {

	private static $login_id = '';
	private static $transaction_key = '';
	private static $testmode;
	private static $logging;
	private static $debugging;
	private static $statement_descriptor;

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_login_id( $login_id ) {
		self::$login_id = $login_id;
	}

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_transaction_key( $transaction_key ) {
		self::$transaction_key = $transaction_key;
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
	 * Get secret key.
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
	 * Get secret key.
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
	/**
	 * Send the request to Authorize.net's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public static function get_authnet() {
		// Load AuthNet PHP library.
		if ( ! class_exists( 'AuthNet\AuthNet', false ) ) {
			require_once( dirname( WC_AUTHNET_MAIN_FILE ) . '/vendor/autoload.php' );
		}

		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName( self::get_login_id() );
		$merchantAuthentication->setTransactionKey( self::get_transaction_key() );

		return $merchantAuthentication;
	}

	public static function execute( $controller, $request ) {
		$payment_mode = self::is_testmode() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

		$response = $controller->executeWithApiResponse( $payment_mode );

		if( self::is_debugging() ) {
			self::log( 'Request: ' . print_r( $request, 1 ) );
			self::log( 'Response: ' . print_r( $response, 1 ) );
		}

		if( $response == null ) {
			return new WP_Error( 'cannot_connect', __( 'Unable to process request.', 'wc-authnet' ) );
		}

		if( $response->getMessages()->getResultCode() == "Ok" ) {
			self::log( 'Request was successful.' );
		} else {
			$errorMessages = $response->getMessages()->getMessage();
			self::log( 'Error: Request Failed. ' . $errorMessages[0]->getCode() . ' - ' .$errorMessages[0]->getText() );
			return new WP_Error( $errorMessages[0]->getCode(), $errorMessages[0]->getText() );
		}
		return $response;
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		if ( self::is_logging() ) {
			WC_Authnet_Logger::log( $message );
		}
	}
}
