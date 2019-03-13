jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Authnet payment forms.
	 */
	var wc_authnet_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_authnet',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( document )
				.on(
					'change',
					'#wc-authnet-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'authnetError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);
		},

		isAuthnetChosen: function() {
			return $( '#payment_method_authnet' ).is( ':checked' ) && ( ! $( 'input[name="wc-authnet-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-authnet-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return ( 0 < $( 'input.authnet_nonce' ).length ) && ( 0 < $( 'input.authnet_data_descriptor' ).length );
		},

		block: function() {
			wc_authnet_form.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},

		unblock: function() {
			wc_authnet_form.form.unblock();
		},

		onError: function( e, responseObject ) {
			//console.log(responseObject.response);
			var message = responseObject.response.text;

			$( '.wc-authnet-error, .authnet_nonce' ).remove();
			$( '.wc-authnet-error, .authnet_data_descriptor' ).remove();
			$( '#authnet-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-authnet-error"><li>' + message + '</li></ul>' );
			wc_authnet_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_authnet_form.isAuthnetChosen() && ! wc_authnet_form.hasToken() ) {
				e.preventDefault();
				wc_authnet_form.block();

				var card_allowed = false,
                    card       = $( '#authnet-card-number' ).val(),
					cvc        = $( '#authnet-card-cvc' ).val(),
					expires    = $( '#authnet-card-expiry' ).payment( 'cardExpiryVal' ),
					first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_authnet_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_authnet_params.billing_last_name;

                wc_authnet_params.allowed_card_types.forEach(function (card_type) {
                    if( $( '#authnet-card-number' ).hasClass( card_type.replace( 'diners-club', 'dinersclub' ) ) ) {
                        card_allowed = true;
                    }
                });

                if( ! card_allowed ) {
					$( '.wc-authnet-error, .authnet_nonce' ).remove();
					$( '.wc-authnet-error, .authnet_data_descriptor' ).remove();
					$( '#authnet-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-authnet-error"><li>' + wc_authnet_params.card_disallowed_error + '</li></ul>' );
					wc_authnet_form.unblock();
					return false;
				}
                
                if( cvc == '' ) {
					$( '.wc-authnet-error, .authnet_nonce' ).remove();
					$( '.wc-authnet-error, .authnet_data_descriptor' ).remove();
					$( '#authnet-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-authnet-error"><li>' + wc_authnet_params.no_cvv_error + '</li></ul>' );
					wc_authnet_form.unblock();
					return false;
				}

				var authData = {};
				authData.clientKey = wc_authnet_params.client_key;
				authData.apiLoginID = wc_authnet_params.login_id;

				var cardData = {};
				cardData.cardNumber = card.replace( /\s/g, '' );
				cardData.month = expires.month.toString();
				cardData.year = expires.year.toString().slice( -2 );
				cardData.cardCode = cvc;
				if ( first_name && last_name ) {
					cardData.fullName = first_name + ' ' + last_name;
				}

				var secureData = {};
				secureData.authData = authData;
				secureData.cardData = cardData;

				Accept.dispatchData( secureData, wc_authnet_form.onAuthnetResponse );

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-authnet-error, .authnet_nonce' ).remove();
			$( '.wc-authnet-error, .authnet_data_descriptor' ).remove();
		},

		onAuthnetResponse: function( response ) {

			if ( response.messages.resultCode === "Error" ) {
				var i = 0;
				while ( i < response.messages.message.length ) {
					//console.log( response.messages.message[i].code + ": " + response.messages.message[i].text );
					$( document ).trigger( 'authnetError', { response: response.messages.message[i] } );
					i = i + 1;
				}
			} else {
				//console.log(response);
				// insert the token into the form so it gets submitted to the server
				wc_authnet_form.form.append( "<input type='hidden' class='authnet_nonce' name='authnet_nonce' value='" + response.opaqueData.dataValue + "'/>" );
				wc_authnet_form.form.append( "<input type='hidden' class='authnet_data_descriptor' name='authnet_data_descriptor' value='" + response.opaqueData.dataDescriptor + "'/>" );
				wc_authnet_form.form.submit();
			}
		},

		clearToken: function() {
			$( '.authnet_nonce' ).remove();
			$( '.authnet_data_descriptor' ).remove();
		}
	};

	wc_authnet_form.init();
} );
