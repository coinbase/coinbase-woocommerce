coinbase-woocommerce
====================

Accept Bitcoin on your WooCommerce-powered website with Coinbase.

## Installation

First generate an API key with the 'wallet:checkouts:create' permission at https://www.coinbase.com/settings/api. If you don't have a Coinbase account, sign up at https://www.coinbase.com/merchants. Your merchant profile must be filled out to accept orders. Coinbase offers daily payouts for merchants in the United States. For more infomation on setting up payouts, see https://www.coinbase.com/docs/merchant_tools/payouts.

To install the plugin:

1. [Download](https://github.com/coinbase/coinbase-woocommerce/archive/master.zip) the plugin as a .zip file.
2. In your WordPress administration console, navigate to Plugins > Add New > Upload.
3. Upload the .zip file downloaded in step 1.
4. Click 'Install Now' and then 'Activate Plugin.'
5. Navigate to WooCommerce > Settings, and then click on the Checkout tab at the top of the screen.
6. Click on Coinbase.
7. Enter your API Credentials and click on 'Save changes'.
8. Set your callback URL on https://www.coinbase.com/merchant_settings to "$YOUR_SITE/?wc-api=WC_Gateway_Coinbase"; for example, "http://www.my-store.com/?wc-api=WC_Gateway_Coinbase"
