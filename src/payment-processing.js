import { useEffect, useCallback, useState } from '@wordpress/element';
import {authnetDispatch, getClientKey} from "./utils";

export const usePaymentProcessing = (
	billing,
	cardNumber,
	expiryDate,
	cvc,
	PAYMENT_METHOD_NAME,
	emitResponse,
	onPaymentSetup,
	onCheckoutFail
) => {

	const [ error, setError ] = useState( '' );
	const onAuthnetError = useCallback( ( message ) => {
		console.log( message );
		setError( message );
		return message ? message : false;
	}, [] );

	const [effectTrigger, setEffectTrigger] = useState(0);
	// hook into and register callbacks for events
	useEffect( () => {
	console.log("🚀 ~ file: payment-processing.js:22 ~ useEffect ~ useEffect:", effectTrigger)

	setEffectTrigger(effectTrigger+1);

		const createToken = async ( paymentData ) => {
			return await authnetDispatch( paymentData );
		};
		const onSubmit = async () => {
			try {
				const billingAddress = billing.billingAddress;
				// if there's an error return that.
				if ( error ) {
					console.log('returning');
					return {
						type: emitResponse.responseTypes.ERROR,
						message: error,
					};
				}
				// use token if it's set.
				/*if ( sourceId !== '' ) {
					return {
						type: emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								paymentMethod: PAYMENT_METHOD_NAME,
								paymentRequestType: 'cc',
								stripe_source: sourceId,
							},
							billingAddress,
						},
					};
				}*/
				const ownerInfo = {
					address: {
						line1: billingAddress.address_1,
						line2: billingAddress.address_2,
						city: billingAddress.city,
						state: billingAddress.state,
						postal_code: billingAddress.postcode,
						country: billingAddress.country,
					},
				};
				if ( billingAddress.phone ) {
					ownerInfo.phone = billingAddress.phone;
				}
				if ( billingAddress.email ) {
					ownerInfo.email = billingAddress.email;
				}
				if ( billingAddress.first_name || billingAddress.last_name ) {
					ownerInfo.name = `${ billingAddress.first_name } ${ billingAddress.last_name }`;
				}

				let authnetArgs = {};

				if( getClientKey() ) {
					const extractDate = ( expiryDate ) => {
						let splitDate = expiryDate.split('/');

						let data = [];
						for(var i in splitDate){
							if( ! data?.month ) {
								data.month = splitDate[i].trim();
							} else {
								data.year = splitDate[i].trim();
							}
						}
						console.log('date', data);
						return data;
					};

					const expires = extractDate( expiryDate );

					const paymentData = {
						cardNumber: cardNumber.replace( /\s/g, '' ),
						cardCode: cvc,
						month: expires?.month.toString(),
						year: expires?.year.toString().slice( -2 ),
						fullName: ownerInfo.name,
					};

					const response = await createToken( paymentData );
					console.log(response);
					if ( response?.messages?.resultCode === "Error" ) {
						var i = 0;
						while ( i < response.messages.message.length ) {
							//console.log( response.messages.message[i].code + ": " + response.messages.message[i].text );
							return {
								type: emitResponse.responseTypes.ERROR,
								message: onAuthnetError( response.messages.message[i] ),
							};
							i = i + 1;
						}

					}

					authnetArgs = {
						authnet_nonce: response?.opaqueData?.dataValue,
						authnet_data_descriptor: response?.opaqueData?.dataDescriptor
					};
				} else {
					authnetArgs = {
						'authnet-card-number': cardNumber,
						'authnet-card-expiry': expiryDate,
						'authnet-card-cvc': cvc
					};
				}

				/*const newPaymentMethodId =
					response?.paymentMethod?.id ?? response?.source?.id;
				if ( ! newPaymentMethodId ) {
					throw new Error(
						getErrorMessageForTypeAndCode( errorTypes.API_ERROR )
					);
				}
				setSourceId( newPaymentMethodId );*/
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							...authnetArgs,
							billing_email: ownerInfo.email,
							billing_first_name: billingAddress?.first_name ?? '',
							billing_last_name: billingAddress?.last_name ?? '',
							paymentMethod: PAYMENT_METHOD_NAME,
							paymentRequestType: 'cc',
						},
						billingAddress,
					},
				};
			} catch ( e ) {
				console.log('catch', e);
				return {
					type: emitResponse.responseTypes.ERROR,
					message: e,
				};
			}
		};
		const unsubscribeProcessing = onPaymentSetup( onSubmit );
		return () => {
			unsubscribeProcessing();
		};
	}, [
		onPaymentSetup,
		billing.billingAddress,
		onAuthnetError,
		error,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
	] );

	// hook into and register callbacks for events.
	useEffect( () => {
		const onError = ( { processingResponse } ) => {
			if ( processingResponse?.paymentDetails?.errorMessage ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: processingResponse.paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
			// so we don't break the observers.
			return true;
		};
		const unsubscribeAfterProcessing = onCheckoutFail(
			onError
		);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		onCheckoutFail,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
	] );
	return onAuthnetError;
};
