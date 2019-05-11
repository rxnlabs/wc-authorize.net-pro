=== Authorize.Net Payment Gateway For WooCommerce ===
Contributors: mohsinoffline, freemius
Donate link: https://wpgateways.com/support/send-payment/
Tags: woocommerce Authorize.Net, Authorize.Net, payment gateway, woocommerce, woocommerce payment gateway, woocommerce subscriptions, recurring payments, pre order
Plugin URI: https://pledgedplugins.com/products/authorize-net-payment-gateway-woocommerce/
Author URI: https://pledgedplugins.com
Requires at least: 4.4
Tested up to: 5.2
Requires PHP: 5.6
Stable tag: 5.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin enables you to use the Authorize.Net payment gateway and accept credit cards directly on your WooCommerce powered WordPress e-commerce website without redirecting customers away to the gateway website.

== Description ==

[Authorize.Net](https://www.authorize.net/) Payment Gateway allows you to accept credit cards from all over the world on your websites and deposit funds automatically into your merchant bank account.

[WooCommerce](https://woocommerce.com/) is one of the oldest and most powerful e-commerce solutions for WordPress. This platform is very widely supported in the WordPress community which makes it easy for even an entry level e-commerce entrepreneur to learn to use and modify.

#### FREE Pro Version Features
* **Easy Install**: Like all Pledged Plugins add-ons, this plugin installs with one click. After installing, you will have only a few fields to fill out before you are ready to accept credit cards on your store.
* **Secure Credit Card Processing**: Uses [Accept.js](https://developer.authorize.net/api/reference/features/acceptjs.html) library to send secure payment data directly to Authorize.net so no worries about certifying with PCI-DSS.
* **Refund via Dashboard**: Process full or partial refunds, directly from your WordPress dashboard! No need to search order in your Authorize.net account.
* **Authorize Now, Capture Later**: Optionally choose only to authorize transactions, and capture at a later date.
* **Restrict Card Types**: Optionally choose to restrict certain card types and the plugin will hide its icon and provide a proper error message on checkout.
* **Gateway Receipts**: Optionally choose to send receipts from your Authorize.Net merchant account.
* **Logging**: Enable logging so you can debug issues that arise if any.

> #### Enterprise Version Features
> * **Process Subscriptions:**  Use with  [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)  extension to **create and manage products with recurring payments**  — payments that will give you residual revenue you can track and count on.
> * **Setup Pre-Orders:**  Use with  [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/)  extension so customers can order products before they’re available by submitting their card details. The card is then automatically charged when the pre-order is available.
> * **Pay via Saved Cards:** Enable option to use saved card details on the gateway servers for quicker checkout. No sensitive card data is stored on the website!
> 
> [Click here](https://pledgedplugins.com/products/authorize-net-payment-gateway-woocommerce/) for Pricing details.

#### Requirements
* Active  [Authorize.net](https://www.authorize.net/)  account – Sign up for a sandbox account  [here](https://developer.authorize.net/hello_world/sandbox.html)  if you need to test.
* [**WooCommerce**](https://woocommerce.com/)  version 3.0.0 or later.
* A valid SSL certificate is required to ensure your customer credit card details are safe and make your site PCI DSS compliant. This plugin does not store the customer credit card numbers or sensitive information on your website.
#### Extend, Contribute, Integrate
Visit the [plugin page](https://pledgedplugins.com/products/authorize-net-payment-gateway-woocommerce/) for more details. Contributors are welcome to send pull requests via [Bitbucket repository](https://bitbucket.org/pledged/authorize.net/).

For custom payment gateway integration with your WordPress website, please [contact us here](https://wpgateways.com/support/custom-payment-gateway-integration/).

#### Disclaimer
This plugin is not affiliated with or supported by Authorize.Net, WooCommerce.com or Automattic. All logos and trademarks are the property of their respective owners. 

== Installation ==

1. Upload `woo-authorize-net-gateway-aim` folder/directory to the `/wp-content/plugins/` directory
2. Activate the plugin (WordPress -> Plugins).
3. Go to the WooCommerce settings page (WordPress -> WooCommerce -> Settings) and select the Payments tab.
4. Under the Payments tab, you will find all the available payment methods. Find the 'Authorize.Net' link in the list and click it.
5. On this page you will find all of the configuration options for this payment gateway.
6. Enable the method by using the checkbox.
7. Enter the Authorize.Net account details (API Login ID, Transaction Key and Public Client Key).

**IMPORTANT:** Live merchant accounts cannot be used in a sandbox environment, so to test the plugin, please make sure you are using a separate sandbox account. If you do not have a sandbox account, you can sign up for one from <https://developer.authorize.net/hello_world/sandbox.html>. Check the Authorize.net testing guide from <https://developer.authorize.net/hello_world/testing_guide/> to generate various test scenarios before going live.

That's it! You are ready to accept credit cards with your Authorize.Net merchant account now connected to WooCommerce.

== Frequently Asked Questions ==

= Which API method does this plugin use? =
Since version 5.0.0, the plugin uses the latest Authorize.net  [Payment Transactions API](https://developer.authorize.net/api/reference/features/payment_transactions.html) along with [Accept.js](https://developer.authorize.net/api/reference/features/acceptjs.html) integration to provide maximum security to your transactions.

= Does this plugin support Authorize.Net AIM Emulation? =
Unfortunately, the [Authorize.net emulation method is deprecated](https://developer.authorize.net/api/upgrade_guide/#aim), and will soon be phased out. If you are using another merchant account provider that supports Authorize.net AIM emulator, we would advise you to use it's native API instead of emulation and chances are that *we already have* a **[WooCommerce integration](https://pledgedplugins.com/product-category/woocommerce/)** available for it.

= I **still** need to use Authorize.Net AIM Emulation? =
You are in luck! The [4.0.4](https://downloads.wordpress.org/plugin/woo-authorize-net-gateway-aim.4.0.4.zip) version of the plugin offers the same functionality and uses Authorize.Net AIM integration, so you can use it and change the transaction processing URL. Make sure you DO NOT update thereafter or you will be upgraded to the latest API :).

= Is SSL Required to use this plugin? =
A valid SSL certificate is required to ensure your customer credit card details are safe and make your site PCI DSS compliant. This plugin does not store the customer credit card numbers or sensitive information on your website.

== Changelog ==

= 5.0.2 =
* Added line item data to gateway requests
* Added shipping and tax amounts to gateway requests

= 5.0.1 =
* Updated "WC tested up to" header to 3.6
* Replaced deprecated function "reduce_order_stock" with "wc_reduce_stock_levels"


= 5.0.0 - MAJOR UPDATE =
* Updated transaction methods to Payment Transactions API and implemented Authorize.net SDK
* Removed Authorize.net AIM code
* Added Freemius integration for analytics, upgrade and support

= 4.0.4 =
* Fixed issue with shipping field values

= 4.0.3 =
* Fixed PHP notices
* Changed logging method
* Removed deprecated script code
* Updated post meta saving method
* Added "Refund" transaction feature
* Added shipping fields to gateway request
* Added JCB, Diners Club in Allowed Card types option
* Prevented the "state" parameter from being sent in "capture", "void" or "credit" transactions

= 4.0.2 =
* Changed plugin description

= 4.0.1 =
* Added GDPR privacy support
* Fixed false negative on SSL warning notice in admin
* Added "minimum required" and "tested upto" headers for version check in WooCommerce 3.4

= 4.0.0 =
* Added "authorize only" option
* Added logging option
* Added option to restrict card types
* Added test mode option and made HTTPS mandatory for live mode
* Passed billing details to "Pay for Order" page
* Complete overhaul of the plugin with massive improvements to the code base

= 3.5.2 =
* Updated transaction endpoint URL

= 3.5.1 =
* Made "Order Received" link dynamic
* Included customer IP in the data sent to the gateway
* Added POT file for translation

= 3.5 =
* Fixed compatibility issues with other payment gateway plugins

= 3.2.1 =
* Compatible to WooCommerce 2.3.x
* Compatible to WordPress 4.x

= 3.0 =
* Compatible to WooCommerce 2.2.2
* Compatible to WordPress 4.0

= 2.0 =
* Compatible to WooCommerce 2.1.1
