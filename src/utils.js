import {getSetting} from '@woocommerce/settings';

export const getBlocksConfiguration = () => {
	const authnetServerData = getSetting( 'authnet_data', null );

	if ( ! authnetServerData ) {
		throw new Error( 'Authorize.net initialization data is not available' );
	}

	return authnetServerData;
};

export const getLoginID = () => {
	const loginID = getBlocksConfiguration()?.login_id;
	if ( ! loginID ) {
		throw new Error(
			'There is no Login ID available for Authorize.net. Make sure it is available on the wc.authnet_data.login_id property.'
		);
	}
	return loginID;
};

export const getClientKey = () => {
	return getBlocksConfiguration()?.client_key;
};

export const authnetDispatch = async ( paymentData ) => {

	const secureData = {
		authData: {
			apiLoginID: getLoginID(),
			clientKey: getClientKey()
		},
		cardData: paymentData
	};

	return new Promise((resolve, reject) => {
		if (window) {
			window.Accept.dispatchData(
				secureData,
				(response) => {
					if (response.messages.resultCode === 'Ok') {
						resolve(response);
					}
					reject(response);
				}
			);
		}
	});
};
