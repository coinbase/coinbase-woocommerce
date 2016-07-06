<?php
/**
 * Plugin Name: coinbase-woocommerce
 * Plugin URI: https://github.com/coinbase/coinbase-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with Coinbase.
 * Version: 2.1.3
 * Author: Coinbase Inc.
 * Author URI: https://coinbase.com
 * License: MIT
 * Text Domain: coinbase-woocommerce
 */

/*  Copyright 2014 Coinbase Inc.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	function coinbase_woocommerce_init() {

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * Coinbase Payment Gateway
		 *
		 * Provides a Coinbase Payment Gateway.
		 *
		 * @class       WC_Gateway_Coinbase
		 * @extends     WC_Payment_Gateway
		 * @version     2.0.1
		 * @author      Coinbase Inc.
		 */
		class WC_Gateway_Coinbase extends WC_Payment_Gateway {
			var $notify_url;

			public function __construct() {
				$this->id   = 'coinbase';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/coinbase.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Proceed to Coinbase', 'coinbase-woocommerce');
				$this->notify_url        = $this->construct_notify_url();

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
				$this->description = $this->get_option('description');

				// Actions
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				));
				add_action('woocommerce_receipt_coinbase', array(
					$this,
					'receipt_page'
				));

				// Payment listener/API hook
				add_action('woocommerce_api_wc_gateway_coinbase', array(
					$this,
					'check_coinbase_callback'
				));
			}

			public function admin_options() {
				echo '<h3>' . __('Coinbase Payment Gateway', 'coinbase-woocommerce') . '</h3>';
				$coinbase_account_email = get_option("coinbase_account_email");
				$coinbase_error_message = get_option("coinbase_error_message");
				if ($coinbase_account_email != false) {
					echo '<p>' . __('Successfully connected Coinbase account', 'coinbase-woocommerce') . " '$coinbase_account_email'" . '</p>';
				} elseif ($coinbase_error_message != false) {
					echo '<p>' . __('Could not validate API Key:', 'coinbase-woocommerce') . " $coinbase_error_message" . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function process_admin_options() {
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'coinbase-php' . DIRECTORY_SEPARATOR . 'Coinbase.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					$coinbase = Coinbase::withApiKey($api_key, $api_secret);
					$user     = $coinbase->getUser();
					update_option("coinbase_account_email", $user->email);
					update_option("coinbase_error_message", false);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("coinbase_account_email", false);
					update_option("coinbase_error_message", $error_message);
					return;
				}
			}

			function construct_notify_url() {
				$callback_secret = get_option("coinbase_callback_secret");
				if ($callback_secret == false) {
					$callback_secret = sha1(openssl_random_pseudo_bytes(20));
					update_option("coinbase_callback_secret", $callback_secret);
				}
				$notify_url = WC()->api_request_url('WC_Gateway_Coinbase');
				$notify_url = add_query_arg('callback_secret', $callback_secret, $notify_url);
				return $notify_url;
			}

			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable Coinbase plugin', 'coinbase-woocommerce'),
						'type' => 'checkbox',
						'label' => __('Show bitcoin as an option to customers during checkout?', 'coinbase-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'woocommerce'),
						'type' => 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Bitcoin', 'coinbase-woocommerce')
					),
					'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
						'default'     => __('Pay with bitcoin, a virtual currency.', 'coinbase-woocommerce')
											. " <a href='http://bitcoin.org/' target='_blank'>"
											. __('What is bitcoin?', 'coinbase-woocommerce')
											. "</a>"
	             	),
					'apiKey' => array(
						'title' => __('API Key', 'coinbase-woocommerce'),
						'type' => 'text',
						'description' => __('')
					),
					'apiSecret' => array(
						'title' => __('API Secret', 'coinbase-woocommerce'),
						'type' => 'password',
						'description' => __('')
					)
				);
			}

			function process_payment($order_id) {

				require_once(plugin_dir_path(__FILE__) . 'coinbase-php' . DIRECTORY_SEPARATOR . 'Coinbase.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_coinbase', true, $this->get_return_url($order));

				// Coinbase mangles the order param so we have to put it somewhere else and restore it on init
				$cancel_url = $order->get_cancel_order_url_raw();
				$cancel_url = add_query_arg('return_from_coinbase', true, $cancel_url);
				$cancel_url = add_query_arg('cancelled', true, $cancel_url);
				$cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);

				$params = array(
					'name'               => 'Order #' . $order_id,
					'price_string'       => $order->get_total(),
					'price_currency_iso' => get_woocommerce_currency(),
					'callback_url'       => $this->notify_url,
					'custom'             => $order_id,
					'success_url'        => $success_url,
					'cancel_url'         => $cancel_url,
				);

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				if ($api_key == '' || $api_secret == '') {
					if ( version_compare( $woocommerce->version, '2.1', '>=' ) ) {
						wc_add_notice(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'coinbase-woocommerce'), 'error' );
					} else {
						$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'coinbase-woocommerce'));
					}
					return;
				}

				try {
					$coinbase = Coinbase::withApiKey($api_key, $api_secret);
					$code     = $coinbase->createButtonWithOptions($params)->button->code;
				}
				catch (Exception $e) {
					$order->add_order_note(__('Error while processing coinbase payment:', 'coinbase-woocommerce') . ' ' . var_export($e, TRUE));
					if ( version_compare( $woocommerce->version, '2.1', '>=' ) ) {
						wc_add_notice(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'coinbase-woocommerce'), 'error' );
					} else {
						$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'coinbase-woocommerce'));
					}
					return;
				}

				return array(
					'result'   => 'success',
					'redirect' => "https://coinbase.com/checkouts/$code"
				);
			}

			function check_coinbase_callback() {
				$callback_secret = get_option("coinbase_callback_secret");
				if ($callback_secret != false && $callback_secret == $_REQUEST['callback_secret']) {
					$post_body = json_decode(file_get_contents("php://input"));
					if (isset($post_body->order)) {
						$coinbase_order = $post_body->order;
						$order_id       = $coinbase_order->custom;
						$order          = new WC_Order($order_id);
					} else if (isset($post_body->payout)) {
						header('HTTP/1.1 200 OK');
						exit("Coinbase Payout Callback Ignored");
					} else {
						header("HTTP/1.1 400 Bad Request");
						exit("Unrecognized Coinbase Callback");
					}
				} else {
					header("HTTP/1.1 401 Not Authorized");
					exit("Spoofed callback");
				}

				// Legitimate order callback from Coinbase
				header('HTTP/1.1 200 OK');

				// Add Coinbase metadata to the order
				update_post_meta($order->id, __('Coinbase Order ID', 'coinbase-woocommerce'), wc_clean($coinbase_order->id));
				if (isset($coinbase_order->customer) && isset($coinbase_order->customer->email)) {
					update_post_meta($order->id, __('Coinbase Account of Payer', 'coinbase-woocommerce'), wc_clean($coinbase_order->customer->email));
				}

				switch (strtolower($coinbase_order->status)) {

					case 'completed':

						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('Coinbase payment completed', 'coinbase-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('Coinbase reports payment cancelled.', 'coinbase-woocommerce'));
						break;

				}

				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_coinbase_gateway($methods) {
			$methods[] = 'WC_Gateway_Coinbase';
			return $methods;
		}

		function woocommerce_handle_coinbase_return() {
			if (!isset($_GET['return_from_coinbase']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled coinbase payment', 'coinbase-woocommerce'));
				}
			}

			// Coinbase order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}

		add_action('init', 'woocommerce_handle_coinbase_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_coinbase_gateway');
	}

	add_action('plugins_loaded', 'coinbase_woocommerce_init', 0);
}
