<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * WC_Authnet_Customer class.
 *
 * Represents a Authorize.net Customer.
 */
class WC_Authnet_Customer {

	/**
	 * Authorize.net customer ID
	 * @var string
	 */
	private $id = '';

	/**
	 * WP User ID
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Constructor
	 * @param integer $user_id
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( get_user_meta( $user_id, '_authnet_customer_id', true ) );
		}
	}

	/**
	 * Get Authorize.net customer ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set Authorize.net customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 * @param int $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 * @return WP_User
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Create a customer via API.
	 * @param array $args
	 * @return WP_Error|int
	 */
	public function create_customer( $args = array() ) {
		if ( $user = $this->get_user() ) {
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true );

			$defaults = array(
				'email'       => $user->user_email,
				'description' => $billing_first_name . ' ' . $billing_last_name,
			);
		} else {
			$defaults = array(
				'email'       => '',
				'description' => '',
			);
		}

        $args = wp_parse_args( $args, $defaults );
        $this->log( "Info: Customer being added to Authorize.net. " . print_r( $args, 1 ) );

        $merchantAuthentication = WC_Authnet_API::get_authnet();
        
        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new AnetAPI\CustomerProfileType();
        $customerProfile->setDescription( $args['description'] );
        $customer_id = $this->get_user_id() ? $this->get_user_id() : 'guest_' . time();
        $customerProfile->setMerchantCustomerId( $customer_id );
        $customerProfile->setEmail( $args['email'] );
        
        // Assemble the complete transaction request
        $request = new AnetAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication( $merchantAuthentication );
        $request->setProfile( $customerProfile );
        
        // Create the controller and get the response
        $controller = new AnetController\CreateCustomerProfileController( $request );

		
		$response = WC_Authnet_API::execute( $controller, $request );

		if ( is_wp_error( $response ) ) {
            $this->log( "Error: Customer could not be added.");
			return $response;
		} elseif ( empty( $response->getCustomerProfileId() ) ) {
            $this->log( "Error: No customer ID returned.");
			return new WP_Error( 'authnet_error', __( 'Could not create Authorize.net customer.', 'wc-authnet' ) );
		}
        
        $this->log( "Success: Customer added to Authorize.net. Customer ID: " . $response->getCustomerProfileId() );

		$this->set_id( $response->getCustomerProfileId() );
		$this->clear_cache();

		if ( $this->get_user_id() ) {
			update_user_meta( $this->get_user_id(), '_authnet_customer_id', $response->getCustomerProfileId() );
		}

		do_action( 'woocommerce_authnet_add_customer', $args, $response );

		return $response->getCustomerProfileId();
	}

	/**
	 * Add a card for this authnet customer.
	 * @param string $source_args
	 * @param bool $retry
	 * @return WP_Error|int
	 */
	public function add_card( $source_args, $retry = true ) {
        
        $this->log( "Info: Card details being submitted to Authorize.net." );
        
		if ( ! $this->get_id() ) {
            $this->log( "Info: Customer does not exist Authorize.net." );
			if ( ( $response = $this->create_customer() ) && is_wp_error( $response ) ) {
				return $response;
			}
		}

        $merchantAuthentication = WC_Authnet_API::get_authnet();
        
        if( $source_args['source_type'] == 'card' ) {
            // Set credit card information for payment profile
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber( $source_args['card_number'] );
            $source_args['expiry'] = explode( '/',  $source_args['expiry'] );
            $creditCard->setExpirationDate( trim( $source_args['expiry'][1] ) . '-' . trim( $source_args['expiry'][0] ) );
            $creditCard->setCardCode( $source_args['cvc'] );
            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setCreditCard( $creditCard );
        } elseif( $source_args['source_type'] == 'nonce' ) {
            // Create the payment object for a payment nonce
            $opaqueData = new AnetAPI\OpaqueDataType();
            $opaqueData->setDataDescriptor( $source_args['descriptor'] );
            $opaqueData->setDataValue( $source_args['nonce'] );
            // Add the payment data to a paymentType object
            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setOpaqueData($opaqueData);
        }

        // Create the Bill To info for new payment type
        $billto = new AnetAPI\CustomerAddressType();
        $billto->setFirstName( get_user_meta( $this->get_user_id(), 'billing_first_name', true ) );
        $billto->setLastName( get_user_meta( $this->get_user_id(), 'billing_last_name', true ) );
        $billto->setCompany( get_user_meta( $this->get_user_id(), 'billing_company', true ) );
        $billto->setAddress( trim( get_user_meta( $this->get_user_id(), 'billing_address_1', true ) . ' ' . get_user_meta( $this->get_user_id(), 'billing_address_2', true ) ) );
        $billto->setCity( get_user_meta( $this->get_user_id(), 'billing_city', true ) );
        $billto->setState( get_user_meta( $this->get_user_id(), 'billing_state', true ) );
        $billto->setZip( get_user_meta( $this->get_user_id(), 'billing_postcode', true ) );
        $billto->setCountry( get_user_meta( $this->get_user_id(), 'billing_country', true ) );
        $billto->setPhoneNumber( get_user_meta( $this->get_user_id(), 'billing_phone', true ) );

        // Create a new Customer Payment Profile object
        $paymentprofile = new AnetAPI\CustomerPaymentProfileType();
        $paymentprofile->setBillTo( $billto );
        $paymentprofile->setPayment( $paymentCreditCard );

        // Assemble the complete transaction request
        $paymentprofilerequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
        $paymentprofilerequest->setMerchantAuthentication( $merchantAuthentication );
        
        // Add an existing profile id to the request
        $paymentprofilerequest->setCustomerProfileId( $this->get_id() );
        $paymentprofilerequest->setPaymentProfile( $paymentprofile );
        $paymentprofilerequest->setValidationMode( "liveMode" );
        
        // Create the controller and get the response
        $controller = new AnetController\CreateCustomerPaymentProfileController( $paymentprofilerequest );

		$response = WC_Authnet_API::execute( $controller, $paymentprofilerequest );

		if ( is_wp_error( $response ) ) {
			// It is possible the WC user once was linked to a customer on Authorize.net
			// but no longer exists. Instead of failing, lets try to create a
			// new customer.
			if ( preg_match( '/The record cannot be found./', $response->get_error_message() ) ) {
                $this->log( "Info: Local customer ID not found on Authorize.net. Creating a new customer first." );
				delete_user_meta( $this->get_user_id(), '_authnet_customer_id' );
				$this->create_customer();
				return $this->add_card( $source_args, false );
            } elseif ( preg_match( '/Invalid OTS Token/', $response->get_error_message() ) ) {
                $this->log( "Info: Authorize.net nonce expired. Returning to customer to let them try again." );
                return new WP_Error( 'error', __( 'The token for card details you entered has expired. Please enter your card details again.', 'wc-authnet' ) );
			} else {
                $this->log( "Error: Card could not be added." );
				return $response;
			}
		} elseif ( empty( $response->getCustomerPaymentProfileId() ) ) {
            $this->log( "Error: Authorize.net Payment Profile ID not returned." );
			return new WP_Error( 'error', __( 'Unable to add card.', 'wc-authnet' ) );
		}
        $this->log( "Success: Card was added to Authorize.net with Payment Profile ID: " . $response->getCustomerPaymentProfileId() );
        $paymentProfile = $this->get_customer_card( $response->getCustomerPaymentProfileId() );

		// Add token to WooCommerce
		if ( $paymentProfile && $this->get_user_id() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$token = new WC_Payment_Token_CC();
			$token->set_token( $response->getCustomerPaymentProfileId() );
			$token->set_gateway_id( 'authnet' );
			$token->set_card_type( strtolower( $paymentProfile->getPayment()->getCreditCard()->getCardType() ) );
			$token->set_last4( substr( $paymentProfile->getPayment()->getCreditCard()->getCardNumber(), -4 ) );
            $expiry = explode( '-', $paymentProfile->getPayment()->getCreditCard()->getExpirationDate() );
			$token->set_expiry_month( $expiry[1] );
			$token->set_expiry_year( $expiry[0] );
			$token->set_user_id( $this->get_user_id() );
			$token->save();
		}

		$this->clear_cache();

		do_action( 'woocommerce_authnet_add_card', $this->get_id(), $source_args, $response );

		return $response->getCustomerPaymentProfileId();
	}

	/**
	 * Get a customers saved cards using their Authorize.net ID. Cached.
	 *
	 * @param  string $customer_id
	 * @return array
	 */
	public function get_cards() {
		$cards = array();

		if ( $this->get_id() && false === ( $cards = get_transient( 'authnet_cards_' . $this->get_id() ) ) ) {
            
            $merchantAuthentication = WC_Authnet_API::get_authnet();
            
            $request = new AnetAPI\GetCustomerProfileRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setCustomerProfileId( $this->get_id() );
            $request->setUnmaskExpirationDate( true );
            $controller = new AnetController\GetCustomerProfileController( $request );
            
            $response = WC_Authnet_API::execute( $controller, $request );
            
			if ( is_wp_error( $response ) ) {
				return array();
			}

			if ( is_array( $response->getProfile()->getPaymentProfiles() ) ) {
				$cards = $response->getProfile()->getPaymentProfiles();
			}

			set_transient( 'authnet_cards_' . $this->get_id(), $cards, HOUR_IN_SECONDS * 48 );
		}

		return $cards;
	}

	/**
	 * Delete a card from authnet.
	 * @param string $source_id
	 */
	public function delete_card( $source_id ) {
        $this->log( "Info: Deleting card from Authorize.net Payment Profile ID: $source_id" );
        $merchantAuthentication = WC_Authnet_API::get_authnet();
        
        $request = new AnetAPI\DeleteCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication( $merchantAuthentication );
        $request->setCustomerProfileId( $this->get_id() );
        $request->setCustomerPaymentProfileId( $source_id );
        $controller = new AnetController\DeleteCustomerPaymentProfileController( $request );
        
        $response = WC_Authnet_API::execute( $controller, $request );
		$this->clear_cache();

		if ( ! is_wp_error( $response ) ) {
			do_action( 'wc_authnet_delete_card', $this->get_id(), $response );
            $this->log( "Success: Card deleted from Authorize.net." );
			return true;
		}
        $this->log( "Error: Card could not be deleted from Authorize.net." );
		return false;
	}

	/**
	 * Set default card in Authorize.net
	 * @param string $source_id
	 */
	public function set_default_card( $source_id ) {
        $this->log( "Info: Setting default payment on Authorize.net Payment Profile ID: $source_id" );
		$currentPaymentProfile = $this->get_customer_card( $source_id );
        
        if( $currentPaymentProfile ) {
            
            $merchantAuthentication = WC_Authnet_API::get_authnet();
            
            $billto = new AnetAPI\CustomerAddressType();
            $billto = $currentPaymentProfile->getbillTo();

            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber( $currentPaymentProfile->getPayment()->getCreditCard()->getCardNumber() );
            $creditCard->setExpirationDate( $currentPaymentProfile->getPayment()->getCreditCard()->getExpirationDate() );
            
            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setCreditCard( $creditCard );
            
            $paymentprofile = new AnetAPI\CustomerPaymentProfileExType();
            $paymentprofile->setBillTo( $billto );
            $paymentprofile->setCustomerPaymentProfileId( $source_id );
            $paymentprofile->setPayment( $paymentCreditCard );	
       
            // Submit a UpdatePaymentProfileRequest
            $request = new AnetAPI\UpdateCustomerPaymentProfileRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setCustomerProfileId( $this->get_id() );
            $request->setPaymentProfile( $paymentprofile );
            $paymentprofile->setDefaultPaymentProfile( true );
            
            $controller = new AnetController\UpdateCustomerPaymentProfileController( $request );
            
            $response = WC_Authnet_API::execute( $controller, $request );
            
            $this->clear_cache();

            if ( ! is_wp_error( $response ) ) {
                $this->log( "Success: Card set as default on Authorize.net." );
                do_action( 'wc_authnet_set_default_card', $this->get_id(), $response );

                return true;
            }
        }
        $this->log( "Error: Card could not be set as default on Authorize.net." );
		return false;
	}
    
    /**
	 * Update billing details on card in Authorize.net
	 * @param string $source_id
	 */
	public function update_card_billing( $source_id ) {
        $this->log( "Info: Updating billing details on Authorize.net from a new order. Payment Profile ID: $source_id" );
		$currentPaymentProfile = $this->get_customer_card( $source_id );
        
        if( $currentPaymentProfile ) {
            
            $merchantAuthentication = WC_Authnet_API::get_authnet();
            
            $billto = new AnetAPI\CustomerAddressType();
            $billto = $currentPaymentProfile->getbillTo();
            
            $billto->setFirstName( get_user_meta( $this->get_user_id(), 'billing_first_name', true ) );
            $billto->setLastName( get_user_meta( $this->get_user_id(), 'billing_last_name', true ) );
            $billto->setCompany( get_user_meta( $this->get_user_id(), 'billing_company', true ) );
            $billto->setAddress( trim( get_user_meta( $this->get_user_id(), 'billing_address_1', true ) . ' ' . get_user_meta( $this->get_user_id(), 'billing_address_2', true ) ) );
            $billto->setCity( get_user_meta( $this->get_user_id(), 'billing_city', true ) );
            $billto->setState( get_user_meta( $this->get_user_id(), 'billing_state', true ) );
            $billto->setZip( get_user_meta( $this->get_user_id(), 'billing_postcode', true ) );
            $billto->setCountry( get_user_meta( $this->get_user_id(), 'billing_country', true ) );
            $billto->setPhoneNumber( get_user_meta( $this->get_user_id(), 'billing_phone', true ) );

            
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber( $currentPaymentProfile->getPayment()->getCreditCard()->getCardNumber() );
            $creditCard->setExpirationDate( $currentPaymentProfile->getPayment()->getCreditCard()->getExpirationDate() );
            
            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setCreditCard( $creditCard );
            
            $paymentprofile = new AnetAPI\CustomerPaymentProfileExType();
            $paymentprofile->setBillTo( $billto );
            $paymentprofile->setCustomerPaymentProfileId( $source_id );
            $paymentprofile->setPayment( $paymentCreditCard );	
       
            // Submit a UpdatePaymentProfileRequest
            $request = new AnetAPI\UpdateCustomerPaymentProfileRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setCustomerProfileId( $this->get_id() );
            $request->setPaymentProfile( $paymentprofile );
            
            $controller = new AnetController\UpdateCustomerPaymentProfileController( $request );
            
            $response = WC_Authnet_API::execute( $controller, $request );
            
            $this->clear_cache();

            if ( ! is_wp_error( $response ) ) {
                do_action( 'wc_authnet_update_card_billing', $this->get_id(), $response );
                $this->log( "Success: Billing details updated on Authorize.net." );
                return true;
            }
        }
        $this->log( "Error: Billing details could not be updated on Authorize.net." );
		return false;
	}
    
    public function get_customer_card( $source_id ) {
        $merchantAuthentication = WC_Authnet_API::get_authnet();
        
        //request requires customerProfileId and customerPaymentProfileId
        $request = new AnetAPI\GetCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
      
        $request->setCustomerProfileId( $this->get_id() );
        $request->setCustomerPaymentProfileId( $source_id );
        $request->setUnmaskExpirationDate( true );
        $controller = new AnetController\GetCustomerPaymentProfileController( $request );
        $response = WC_Authnet_API::execute( $controller, $request );
        
        if ( is_wp_error( $response ) || empty( $response->getPaymentProfile()->getCustomerPaymentProfileId() ) ) {
			
			return false;
		}
        return $response->getPaymentProfile();

    }

	/**
	 * Deletes caches for this users cards.
	 */
	public function clear_cache() {
		delete_transient( 'authnet_cards_' . $this->get_id() );
		delete_transient( 'authnet_customer_' . $this->get_id() );
	}
}
