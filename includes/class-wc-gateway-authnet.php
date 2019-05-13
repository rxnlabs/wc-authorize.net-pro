<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
use  net\authorize\api\contract\v1 as AnetAPI ;
use  net\authorize\api\controller as AnetController ;
/**
 * WC_Gateway_Authnet class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Authnet extends WC_Payment_Gateway_CC
{
    public  $capture ;
    public  $statement_descriptor ;
    public  $saved_cards ;
    public  $login_id ;
    public  $transaction_key ;
    public  $client_key ;
    public  $testmode ;
    public  $logging ;
    public  $debugging ;
    const  ACCEPT_JS_URL_LIVE = 'https://js.authorize.net/v1/Accept.js' ;
    const  ACCEPT_JS_URL_TEST = 'https://jstest.authorize.net/v1/Accept.js' ;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'authnet';
        $this->method_title = __( 'Authorize.Net', 'wc-authnet' );
        $this->method_description = sprintf( esc_html__( 'Live merchant accounts cannot be used in a sandbox environment, so to test the plugin, please make sure you are using a separate sandbox account. If you do not have a sandbox account, you can sign up for one from %shere%s.', 'wc-authnet' ), '<a href="https://developer.authorize.net/hello_world/sandbox.html" target="_blank">', '</a>' );
        $this->has_fields = true;
        $this->method_description .= '<h3>' . __( 'Upgrade to Enterprise', 'wc-authnet' ) . '</h3>' . sprintf( esc_html__( 'Enterprise version is a full blown plugin that provides full support for processing subscriptions, pre-orders and payments via saved cards. The credit card information is saved in your Authorize.net account and is reused to charge future orders, recurring payments or pre-orders at a later time. %sClick here%s to upgrade to Enterprise version or to know more about it.', 'wc-authnet' ), '<a href="' . wc_authnet_fs()->get_upgrade_url() . '" target="_blank">', '</a>' );
        $this->supports = array( 'products', 'refunds' );
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        // Get setting values.
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->testmode = ( $this->get_option( 'testmode' ) === 'yes' ? true : false );
        $this->capture = ( $this->get_option( 'capture', 'yes' ) === 'yes' ? true : false );
        $this->statement_descriptor = $this->get_option( 'statement_descriptor', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
        $this->login_id = $this->get_option( 'login_id' );
        $this->transaction_key = $this->get_option( 'transaction_key' );
        $this->client_key = $this->get_option( 'client_key' );
        $this->logging = ( $this->get_option( 'logging' ) === 'yes' ? true : false );
        $this->debugging = ( $this->get_option( 'debugging' ) === 'yes' ? true : false );
        $this->allowed_card_types = $this->get_option( 'allowed_card_types' );
        $this->customer_receipt = ( $this->get_option( 'customer_receipt' ) === 'yes' ? true : false );
        
        if ( $this->testmode ) {
            $this->description .= "\n\n<strong>" . __( 'TEST MODE ENABLED', 'wc-authnet' ) . "</strong>\n";
            $this->description .= sprintf( __( 'In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date or check the %sAuthorize.net Testing Guide%s for more card numbers and generate various test scenarios before going live.', 'wc-authnet' ), '<a href="https://developer.authorize.net/hello_world/testing_guide/" target="_blank">', '</a>' );
        }
        
        if ( $this->client_key ) {
            $this->supports[] = 'tokenization';
        }
        WC_Authnet_API::set_login_id( $this->login_id );
        WC_Authnet_API::set_transaction_key( $this->transaction_key );
        WC_Authnet_API::set_testmode( $this->testmode );
        WC_Authnet_API::set_logging( $this->logging );
        WC_Authnet_API::set_debugging( $this->debugging );
        WC_Authnet_API::set_statement_descriptor( $this->statement_descriptor );
        // Hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
    
    /**
     * get_icon function.
     *
     * @access public
     * @return string
     */
    public function get_icon()
    {
        $icon = '';
        if ( in_array( 'visa', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.svg' ) . '" alt="Visa" width="32" />';
        }
        if ( in_array( 'mastercard', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.svg' ) . '" alt="Mastercard" width="32" />';
        }
        if ( in_array( 'amex', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.svg' ) . '" alt="Amex" width="32" />';
        }
        if ( in_array( 'discover', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.svg' ) . '" alt="Discover" width="32" />';
        }
        if ( in_array( 'jcb', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.svg' ) . '" alt="JCB" width="32" />';
        }
        if ( in_array( 'diners-club', $this->allowed_card_types ) ) {
            $icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners.svg' ) . '" alt="Diners Club" width="32" />';
        }
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }
    
    /**
     * Check if SSL is enabled and notify the user
     */
    public function admin_notices()
    {
        if ( $this->enabled == 'no' ) {
            return;
        }
        // Check required fields
        
        if ( !$this->login_id ) {
            echo  '<div class="error"><p>' . sprintf( __( 'Gateway error: Please enter your API Login ID <a href="%s">here</a>', 'wc-authnet' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=authnet' ) ) . '</p></div>' ;
            return;
        } elseif ( !$this->transaction_key ) {
            echo  '<div class="error"><p>' . sprintf( __( 'Gateway error: Please enter your Transaction Key <a href="%s">here</a>', 'wc-authnet' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=authnet' ) ) . '</p></div>' ;
            return;
        }
        
        // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
        if ( !wc_checkout_is_https() ) {
            echo  '<div class="notice notice-warning"><p>' . sprintf( __( 'Authorize.Net is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'wc-authnet' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>' ;
        }
    }
    
    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        
        if ( $this->enabled == "yes" ) {
            // Required fields check
            if ( !$this->login_id || !$this->transaction_key ) {
                return false;
            }
            return true;
        }
        
        return parent::is_available();
    }
    
    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = apply_filters( 'wc_authnet_settings', array(
            'enabled'              => array(
            'title'       => __( 'Enable/Disable', 'wc-authnet' ),
            'label'       => __( 'Enable Authorize.Net', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ),
            'title'                => array(
            'title'       => __( 'Title', 'wc-authnet' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'wc-authnet' ),
            'default'     => __( 'Credit card', 'wc-authnet' ),
        ),
            'description'          => array(
            'title'       => __( 'Description', 'wc-authnet' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'wc-authnet' ),
            'default'     => sprintf( __( 'Pay with your credit card via %s.', 'wc-authnet' ), $this->method_title ),
        ),
            'testmode'             => array(
            'title'       => __( 'Sandbox mode', 'wc-authnet' ),
            'label'       => __( 'Enable Sandbox Mode', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => sprintf( esc_html__( 'Check the Authorize.net testing guide %shere%s. This will display "sandbox mode" warning on checkout.', 'wc-authnet' ), '<a href="https://developer.authorize.net/hello_world/testing_guide/" target="_blank">', '</a>' ),
            'default'     => 'yes',
        ),
            'login_id'             => array(
            'title'       => __( 'API Login ID', 'wc-authnet' ),
            'type'        => 'text',
            'description' => esc_html__( 'Get it from Account → Security Settings → API Credentials & Keys page in your Authorize.net account.', 'wc-authnet' ),
            'default'     => '',
        ),
            'transaction_key'      => array(
            'title'       => __( 'Transaction Key', 'wc-authnet' ),
            'type'        => 'password',
            'description' => esc_html__( 'Get it from Account → Security Settings → API Credentials & Keys page in your Authorize.net account. For security reasons, you cannot view your Transaction Key, but you will be able to generate a new one.', 'wc-authnet' ),
            'default'     => '',
        ),
            'client_key'           => array(
            'title'       => __( 'Public Client Key', 'wc-authnet' ),
            'type'        => 'text',
            'description' => esc_html__( 'Get it from Account → Security Settings → Manage Public Client Key page in your Authorize.net account.', 'wc-authnet' ),
        ),
            'statement_descriptor' => array(
            'title'       => __( 'Statement Descriptor', 'wc-authnet' ),
            'type'        => 'text',
            'description' => __( 'Extra information about a charge. This will appear in your order description. Defaults to site name.', 'wc-authnet' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
            'capture'              => array(
            'title'       => __( 'Capture', 'wc-authnet' ),
            'label'       => __( 'Capture charge immediately', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later.', 'wc-authnet' ),
            'default'     => 'yes',
        ),
            'saved_cards'          => array(
            'title'       => __( 'Saved Cards', 'wc-authnet' ),
            'label'       => __( 'Enable Payment via Saved Cards', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Authorize.Net servers, not on your store.', 'wc-authnet' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
            'logging'              => array(
            'title'       => __( 'Logging', 'wc-authnet' ),
            'label'       => __( 'Log debug messages', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => sprintf( __( 'Save debug messages to the WooCommerce System Status log file <code>%s</code>.', 'wc-authnet' ), WC_Log_Handler_File::get_log_file_path( 'woocommerce-gateway-authnet' ) ),
            'default'     => 'no',
        ),
            'debugging'            => array(
            'title'       => __( 'Gateway Debug', 'wc-authnet' ),
            'label'       => __( 'Log gateway requests and response to the WooCommerce System Status log.', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => __( '<strong>CAUTION! Enabling this option will write gateway requests possibly including card numbers and CVV to the logs.</strong> Do not turn this on unless you have a problem processing credit cards. You must only ever enable it temporarily for troubleshooting or to send requested information to the plugin author. It must be disabled straight away after the issues are resolved and the plugin logs should be deleted.', 'wc-authnet' ) . ' ' . sprintf( __( '<a href="%s">Click here</a> to check and delete the full log file.', 'wc-authnet' ), admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . WC_Log_Handler_File::get_log_file_name( 'woocommerce-gateway-authnet' ) ) ),
            'default'     => 'no',
        ),
            'allowed_card_types'   => array(
            'title'       => __( 'Allowed Card types', 'wc-authnet' ),
            'class'       => 'wc-enhanced-select',
            'type'        => 'multiselect',
            'description' => __( 'Select the card types you want to allow payments from.', 'wc-authnet' ),
            'default'     => array(
            'visa',
            'mastercard',
            'discover',
            'amex'
        ),
            'options'     => array(
            'visa'        => __( 'Visa', 'wc-authnet' ),
            'mastercard'  => __( 'MasterCard', 'wc-authnet' ),
            'discover'    => __( 'Discover', 'wc-authnet' ),
            'amex'        => __( 'American Express', 'wc-authnet' ),
            'jcb'         => __( 'JCB', 'wc-authnet' ),
            'diners-club' => __( 'Diners Club', 'wc-authnet' ),
        ),
        ),
            'customer_receipt'     => array(
            'title'       => __( 'Receipt', 'wc-authnet' ),
            'label'       => __( 'Send Gateway Receipt', 'wc-authnet' ),
            'type'        => 'checkbox',
            'description' => __( 'If enabled, the customer will be sent an email receipt from Authorize.Net.', 'wc-authnet' ),
            'default'     => 'no',
        ),
        ) );
        unset( $this->form_fields['saved_cards'] );
    }
    
    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $this->form();
    }
    
    /**
     * Payment_scripts function.
     *
     * Outputs scripts used for authnet payment
     *
     * @since 3.1.0
     * @version 4.0.0
     */
    public function payment_scripts()
    {
        if ( !$this->client_key || !is_cart() && !is_checkout() && !isset( $_GET['pay_for_order'] ) && !is_add_payment_method_page() ) {
            return;
        }
        $js_url = ( $this->testmode ? self::ACCEPT_JS_URL_TEST : self::ACCEPT_JS_URL_LIVE );
        wp_enqueue_script(
            'authnet-accept',
            $js_url,
            '',
            null,
            true
        );
        wp_enqueue_script(
            'woocommerce_authnet',
            plugins_url( 'assets/js/authnet.js', WC_AUTHNET_MAIN_FILE ),
            array( 'jquery-payment', 'authnet-accept' ),
            WC_AUTHNET_VERSION,
            true
        );
        $authnet_params = array(
            'login_id'              => $this->login_id,
            'client_key'            => $this->client_key,
            'allowed_card_types'    => $this->allowed_card_types,
            'i18n_terms'            => __( 'Please accept the terms and conditions first', 'wc-authnet' ),
            'i18n_required_fields'  => __( 'Please fill in required checkout fields first', 'wc-authnet' ),
            'no_cvv_error'          => __( 'CVC code is required.', 'wc-authnet' ),
            'card_disallowed_error' => __( 'Card Type Not Accepted.', 'wc-authnet' ),
        );
        // If we're on the pay page we need to pass authnet.js the address of the order.
        
        if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
            $order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
            $order = wc_get_order( $order_id );
            $authnet_params['billing_first_name'] = $order->get_billing_first_name();
            $authnet_params['billing_last_name'] = $order->get_billing_last_name();
        }
        
        wp_localize_script( 'woocommerce_authnet', 'wc_authnet_params', apply_filters( 'wc_authnet_params', $authnet_params ) );
    }
    
    /**
     * Generate the request for the payment.
     * @param  WC_Order $order
     * @param  object $source
     * @return array()
     */
    protected function generate_payment_request( $order, $source, $recurring_description = '' )
    {
        $merchantAuthentication = WC_Authnet_API::get_authnet();
        
        if ( $source->source['source_type'] == 'card' ) {
            // Create the payment data for a credit card
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber( $source->source['card_number'] );
            $expiry = explode( '/', $source->source['expiry'] );
            $creditCard->setExpirationDate( '20' . substr( trim( $expiry[1] ), -2 ) . '-' . trim( $expiry[0] ) );
            $creditCard->setCardCode( $source->source['cvc'] );
            // Add the payment data to a paymentType object
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard( $creditCard );
        } elseif ( $source->source['source_type'] == 'nonce' ) {
            // Create the payment object for a payment nonce
            $opaqueData = new AnetAPI\OpaqueDataType();
            $opaqueData->setDataDescriptor( $source->source['descriptor'] );
            $opaqueData->setDataValue( $source->source['nonce'] );
            // Add the payment data to a paymentType object
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setOpaqueData( $opaqueData );
        }
        
        // Set the customer's Bill To address
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerAddress->setFirstName( $order->get_billing_first_name() );
        $customerAddress->setLastName( $order->get_billing_last_name() );
        $customerAddress->setCompany( $order->get_billing_company() );
        $customerAddress->setAddress( trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ) );
        $customerAddress->setCity( $order->get_billing_city() );
        $customerAddress->setState( $order->get_billing_state() );
        $customerAddress->setZip( $order->get_billing_postcode() );
        $customerAddress->setCountry( $order->get_billing_country() );
        $customerAddress->setPhoneNumber( $order->get_billing_phone() );
        // Create a TransactionRequestType object and add the previous objects to it
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setPayment( $paymentOne );
        $transactionRequestType->setBillTo( $customerAddress );
        // Create order information
        $anet_order = new AnetAPI\OrderType();
        $anet_order->setInvoiceNumber( $order->get_id() );
        $description = trim( sprintf(
            __( '%1$s - Order %2$s %3$s', 'wc-authnet' ),
            $this->statement_descriptor,
            $order->get_order_number(),
            $recurring_description
        ) );
        $anet_order->setDescription( $description );
        // Set the customer's identifying information
        $customerData = new AnetAPI\CustomerDataType();
        $customer_id = ( is_user_logged_in() ? get_current_user_id() : 'guest_' . time() );
        $customerData->setId( $customer_id );
        $customerData->setEmail( $order->get_billing_email() );
        // Create a customer shipping address
        $customerShippingAddress = new AnetAPI\CustomerAddressType();
        $customerShippingAddress->setFirstName( $order->get_shipping_first_name() );
        $customerShippingAddress->setLastName( $order->get_shipping_last_name() );
        $customerShippingAddress->setCompany( $order->get_shipping_company() );
        $customerShippingAddress->setAddress( trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() ) );
        $customerShippingAddress->setCity( $order->get_shipping_city() );
        $customerShippingAddress->setState( $order->get_shipping_state() );
        $customerShippingAddress->setZip( $order->get_shipping_postcode() );
        $customerShippingAddress->setCountry( $order->get_shipping_country() );
        // Add values for transaction settings
        $duplicateWindowSetting = new AnetAPI\SettingType();
        $duplicateWindowSetting->setSettingName( 'duplicateWindow' );
        $duplicateWindowSetting->setSettingValue( '60' );
        $emailCustomerSetting = new AnetAPI\SettingType();
        $emailCustomerSetting->setSettingName( 'emailCustomer' );
        $emailCustomerSetting->setSettingValue( $this->customer_receipt );
        $merchantDefinedField1 = new AnetAPI\UserFieldType();
        $merchantDefinedField1->setName( __( 'Customer Name', 'wc-authnet' ) );
        $merchantDefinedField1->setValue( sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() ) );
        $merchantDefinedField2 = new AnetAPI\UserFieldType();
        $merchantDefinedField2->setName( __( 'Customer Email', 'wc-authnet' ) );
        $merchantDefinedField2->setValue( sanitize_email( $order->get_billing_email() ) );
        $transaction_type = ( $this->capture ? 'authCaptureTransaction' : 'authOnlyTransaction' );
        $transactionRequestType->setTransactionType( $transaction_type );
        $transactionRequestType->setOrder( $anet_order );
        $transactionRequestType->setAmount( $order->get_total() );
        $transactionRequestType->setShipTo( $customerShippingAddress );
        $transactionRequestType->setCustomer( $customerData );
        $transactionRequestType->setCustomerIP( WC_Geolocation::get_ip_address() );
        $transactionRequestType->addToTransactionSettings( $duplicateWindowSetting );
        $transactionRequestType->addToTransactionSettings( $emailCustomerSetting );
        $transactionRequestType->addToUserFields( $merchantDefinedField1 );
        $transactionRequestType->addToUserFields( $merchantDefinedField2 );
        $i = 1;
        foreach ( $order->get_items() as $id => $item ) {
            $product = $item->get_product();
            $lineItem[$i] = new AnetAPI\LineItemType();
            $lineItem[$i]->setItemId( ( is_object( $product ) && $product->get_sku() ? $product->get_sku() : $product->get_id() ) );
            $lineItem[$i]->setName( substr( $item['name'], 0, 30 ) );
            $lineItem[$i]->setUnitPrice( ( isset( $item['recurring_line_total'] ) ? $item['recurring_line_total'] : $order->get_item_total( $item ) ) );
            $lineItem[$i]->setQuantity( $item['qty'] );
            $lineItem[$i]->setTaxable( $product->is_taxable() );
            $lineItems[$i - 1] = $lineItem[$i];
            $i++;
        }
        $transactionRequestType->setLineItems( $lineItems );
        $taxCharges = new AnetAPI\ExtendedAmountType();
        $taxCharges->setAmount( $order->get_total_tax() );
        $transactionRequestType->setTax( $taxCharges );
        $shippingCharges = new AnetAPI\ExtendedAmountType();
        $shippingCharges->setAmount( $order->get_total_shipping() );
        $transactionRequestType->setShipping( $shippingCharges );
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication( $merchantAuthentication );
        $request->setTransactionRequest( $transactionRequestType );
        return apply_filters(
            'wc_authnet_generate_payment_request',
            $request,
            $order,
            $source
        );
    }
    
    /**
     * Get payment source. This can be a new token or existing card.
     *
     * @param string $user_id
     * @param bool  $force_customer Should we force customer creation.
     *
     * @throws Exception When card was not added or for and invalid card.
     * @return object
     */
    protected function get_source( $user_id, $force_customer = false )
    {
        $authnet_source = false;
        $token_id = false;
        $authnet_customer = false;
        WC_Authnet_API::log( "Info: Getting payment source with new card details." );
        // New CC info was entered and we have a new token to process
        
        if ( isset( $_POST['authnet_nonce'] ) && isset( $_POST['authnet_data_descriptor'] ) ) {
            $authnet_source_args = array(
                'nonce'      => wc_clean( $_POST['authnet_nonce'] ),
                'descriptor' => wc_clean( $_POST['authnet_data_descriptor'] ),
            );
            $new_source = $authnet_source_args['source_type'] = 'nonce';
        } elseif ( isset( $_POST['authnet-card-number'] ) && !empty($_POST['authnet-card-number']) && isset( $_POST['authnet-card-expiry'] ) && isset( $_POST['authnet-card-cvc'] ) ) {
            $authnet_source_args = array(
                'card_number' => wc_clean( str_replace( ' ', '', $_POST['authnet-card-number'] ) ),
                'expiry'      => wc_clean( $_POST['authnet-card-expiry'] ),
                'cvc'         => wc_clean( $_POST['authnet-card-cvc'] ),
            );
            // Check for card type supported or not
            
            if ( !in_array( $this->get_card_type( $authnet_source_args['card_number'], 'pattern', 'name' ), $this->allowed_card_types ) ) {
                WC_Authnet_API::log( sprintf( __( 'Card type being used is not one of supported types in plugin settings: %s', 'wc-authnet' ), $this->get_card_type( $authnet_source_args['card_number'], 'pattern', 'name' ) ) );
                WC_Authnet_API::log( "Error: Card Type Not Accepted." );
                throw new Exception( __( 'Card Type Not Accepted.', 'wc-authnet' ) );
            }
            
            
            if ( empty($authnet_source_args['cvc']) ) {
                WC_Authnet_API::log( "Error: CVC code is empty." );
                throw new Exception( __( 'CVC code is required.', 'wc-authnet' ) );
            }
            
            $new_source = $authnet_source_args['source_type'] = 'card';
        }
        
        if ( isset( $new_source ) ) {
            // Not saving token, so don't define customer either.
            $authnet_source = $authnet_source_args;
        }
        return (object) array(
            'token_id' => $token_id,
            'customer' => ( $authnet_customer ? $authnet_customer->get_id() : false ),
            'source'   => $authnet_source,
        );
    }
    
    /**
     * Process the payment
     *
     * @param int  $order_id Reference.
     * @param bool $retry Should we retry on fail.
     * @param bool $force_customer Force user creation.
     *
     * @throws Exception If payment will not be accepted.
     *
     * @return array|void
     */
    public function process_payment( $order_id, $retry = true, $force_customer = false )
    {
        $order = wc_get_order( $order_id );
        WC_Authnet_API::log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );
        try {
            $source = $this->get_source( get_current_user_id(), $force_customer );
            
            if ( empty($source->source) && empty($source->customer) ) {
                WC_Authnet_API::log( "Error: Payment source could not be found." );
                $error_msg = __( 'Please enter your card details to make a payment.', 'wc-authnet' );
                $error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'wc-authnet' );
                throw new Exception( $error_msg );
            }
            
            // Result from Authorize.Net API request.
            $response = null;
            // Handle payment.
            
            if ( $order->get_total() > 0 ) {
                // Make the request.
                $request = $this->generate_payment_request( $order, $source );
                $controller = new AnetController\CreateTransactionController( $request );
                $response = WC_Authnet_API::execute( $controller, $request );
                
                if ( is_wp_error( $response ) ) {
                    $message = $response->get_error_message();
                    $order->add_order_note( $message );
                    throw new Exception( $message );
                }
                
                $trx_response = $response->getTransactionResponse();
                
                if ( $trx_response->getErrors() ) {
                    WC_Authnet_API::log( 'Error in Transaction Response: ' . $trx_response->getErrors()[0]->getErrorCode() . ' - ' . $trx_response->getErrors()[0]->getErrorText() );
                    throw new Exception( $trx_response->getErrors()[0]->getErrorText() );
                }
                
                // Process valid response.
                $this->process_response( $trx_response, $order );
            } else {
                $order->payment_complete();
            }
            
            // Remove cart.
            WC()->cart->empty_cart();
            do_action( 'wc_gateway_authnet_process_payment', $response, $order );
            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        } catch ( Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            WC_Authnet_API::log( sprintf( __( 'Error: %s', 'wc-authnet' ), $e->getMessage() ) );
            if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
                $this->send_failed_order_email( $order_id );
            }
            do_action( 'wc_gateway_authnet_process_payment_error', $e, $order );
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }
    
    /**
     * Store extra meta data for an order from a Authorize.Net Response.
     */
    public function process_response( $response, $order )
    {
        $order_id = $order->get_id();
        // Store charge data
        $order->update_meta_data( '_authnet_charge_id', $response->getTransId() );
        $order->update_meta_data( '_authnet_charge_captured', ( $this->capture ? 'yes' : 'no' ) );
        $order->update_meta_data( '_authnet_cc_last4', substr( $response->getAccountNumber(), -4 ) );
        $order->set_transaction_id( $response->getTransId() );
        
        if ( $this->capture ) {
            $order->payment_complete( $response->getTransId() );
            $order->update_meta_data( 'Authorize.Net Payment ID', $response->getTransId() );
            $complete_message = sprintf( __( 'Authorize.Net charge complete (Charge ID: %s)', 'wc-authnet' ), $response->getTransId() );
            $order->add_order_note( $complete_message );
            WC_Authnet_API::log( 'Success: ' . $complete_message );
        } else {
            $order->update_meta_data( '_transaction_id', $response->getTransId() );
            if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
                wc_reduce_stock_levels( $order_id );
            }
            $authorized_message = sprintf( __( 'Authorize.Net charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'wc-authnet' ), $response->getTransId() );
            $order->update_status( 'on-hold', $authorized_message );
            WC_Authnet_API::log( "Success: " . $authorized_message );
        }
        
        $order->save();
        do_action( 'wc_gateway_authnet_process_response', $response, $order );
        return $response;
    }
    
    /**
     * Add payment method via account screen.
     * We don't store the token locally, but to the Authorize.Net API.
     * @since 3.0.0
     */
    public function add_payment_method()
    {
        return parent::add_payment_method();
    }
    
    /**
     * Refund a charge
     * @param  int $order_id
     * @param  float $amount
     * @return bool
     */
    public function process_refund( $order_id, $amount = null, $reason = '' )
    {
        $order = wc_get_order( $order_id );
        if ( !$order || !$order->get_transaction_id() || $amount <= 0 ) {
            return false;
        }
        
        if ( $amount == $order->get_total() ) {
            $instance = new WC_Authnet();
            $instance->cancel_payment( $order_id );
            $order = wc_get_order( $order_id );
            $void_status = $order->get_meta( '_authnet_void' );
        } else {
            $void_status = 'failed';
        }
        
        
        if ( $void_status == 'failed' ) {
            WC_Authnet_API::log( "Info: Beginning refund for order {$order_id} for the amount of {$amount}" );
            $merchantAuthentication = WC_Authnet_API::get_authnet();
            // Create the payment data for a credit card
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber( $order->get_meta( '_authnet_cc_last4' ) );
            $creditCard->setExpirationDate( "XXXX" );
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard( $creditCard );
            $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType( "refundTransaction" );
            $transactionRequest->setAmount( $amount );
            $transactionRequest->setPayment( $paymentOne );
            $transactionRequest->setRefTransId( $order->get_transaction_id() );
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setTransactionRequest( $transactionRequest );
            $controller = new AnetController\CreateTransactionController( $request );
            $response = WC_Authnet_API::execute( $controller, $request );
            $error_message = false;
            
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
            } else {
                $trx_response = $response->getTransactionResponse();
                
                if ( $trx_response->getErrors() ) {
                    WC_Authnet_API::log( 'Gateway Error: ' . $trx_response->getErrors()[0]->getErrorCode() . ' - ' . $trx_response->getErrors()[0]->getErrorText() );
                    $error_message = $trx_response->getErrors()[0]->getErrorText();
                }
            
            }
            
            
            if ( $error_message ) {
                $order->add_order_note( __( 'Gateway Error: ', 'wc-authnet' ) . $error_message );
                throw new Exception( $error_message );
            }
            
            $refund_message = sprintf(
                __( 'Refunded %s - Refund ID: %s - Reason: %s', 'wc-authnet' ),
                $amount,
                $trx_response->getTransId(),
                $reason
            );
            $order->add_order_note( $refund_message );
            $order->save();
            WC_Authnet_API::log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
        }
        
        return true;
    }
    
    function get_card_type( $value, $field = 'pattern', $return = 'label' )
    {
        $card_types = array(
            array(
            'label'        => 'American Express',
            'name'         => 'amex',
            'pattern'      => '/^3[47]/',
            'valid_length' => '[15]',
        ),
            array(
            'label'        => 'JCB',
            'name'         => 'jcb',
            'pattern'      => '/^35(2[89]|[3-8][0-9])/',
            'valid_length' => '[16]',
        ),
            array(
            'label'        => 'Discover',
            'name'         => 'discover',
            'pattern'      => '/^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)/',
            'valid_length' => '[16]',
        ),
            array(
            'label'        => 'MasterCard',
            'name'         => 'mastercard',
            'pattern'      => '/^5[1-5]/',
            'valid_length' => '[16]',
        ),
            array(
            'label'        => 'Visa',
            'name'         => 'visa',
            'pattern'      => '/^4/',
            'valid_length' => '[16]',
        ),
            array(
            'label'        => 'Maestro',
            'name'         => 'maestro',
            'pattern'      => '/^(5018|5020|5038|6304|6759|676[1-3])/',
            'valid_length' => '[12, 13, 14, 15, 16, 17, 18, 19]',
        ),
            array(
            'label'        => 'Diners Club',
            'name'         => 'diners-club',
            'pattern'      => '/^3[0689]/',
            'valid_length' => '[14]',
        )
        );
        foreach ( $card_types as $type ) {
            $card_type = $type['name'];
            $compare = $type[$field];
            if ( $field == 'pattern' && preg_match( $compare, $value, $match ) || $compare == $value ) {
                return $type[$return];
            }
        }
    }
    
    /**
     * Returns the order_id if on the checkout pay page
     *
     * @since 3.0.0
     * @return int order identifier
     */
    public function get_checkout_pay_page_order_id()
    {
        global  $wp ;
        return ( isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0 );
    }
    
    /**
     * Sends the failed order email to admin
     *
     * @version 1.0.2
     * @since 1.0.2
     * @param int $order_id
     * @return null
     */
    public function send_failed_order_email( $order_id )
    {
        $emails = WC()->mailer()->get_emails();
        if ( !empty($emails) && !empty($order_id) ) {
            $emails['WC_Email_Failed_Order']->trigger( $order_id );
        }
    }
    
    public function get_tokens()
    {
        return parent::get_tokens();
    }

}