<?php
/*
Plugin Name: Coinbase Plugin for WooCommerce
Plugin URI: https://coinbase.com/
Description: Accept bitcoin on your WooCommerce-powered site with Coinbase.
Version: 1.0
Author: Coinbase
Author URI: https://coinbase.com/
License: 
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{

        function declareWooCoinbase() 
        {
                if ( ! class_exists( 'WC_Payment_Gateways' ) ) 
                        return;

                class WC_Coinbase extends WC_Payment_Gateway 
                {
                
                        public function __construct() 
                        {
                                $this->id = 'coinbase';
                                $this->icon = plugin_dir_url(__FILE__) . 'icon.png';
                                $this->has_fields = false;
                         
                                $this->init_form_fields();
                                $this->init_settings();
                         
                                $this->title = $this->settings['title'];
                                $this->description = "Pay with bitcoin, a virtual currency. <a href='http://bitcoin.org/' target='_blank'>What is bitcoin?</a>";
                         
                                add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));
                        }
                        
                        function init_form_fields() 
                        {
                                $this->form_fields = array(
                                        'enabled' => array(
                                                'title' => __( 'Enable Coinbase plugin', 'woothemes' ),
                                                'type' => 'checkbox',
                                                'label' => __( 'Show bitcoin as an option to customers during checkout?', 'woothemes' ),
                                                'default' => 'yes'
                                        ),
                                        'title' => array(
                                                'title' => __( 'Title', 'woothemes' ),
                                                'type' => 'text',
                                                'description' => __( 'The title customers will see during checkout.', 'woothemes' ),
                                                'default' => __( 'Bitcoin', 'woothemes' )
                                        ),
                                        'apiKey' => array(
                                                'title' => __('API Key', 'woothemes'),
                                                'type' => 'text',
                                                'description' => __('To be implemented'),
                                        ),
                                );
                        }
                                
                        public function admin_options() {
                                ?>
                                <h3><?php _e('Coinbase', 'woothemes'); ?></h3>
                                <p><?php _e('Accept bitcoin with Coinbase.', 'woothemes'); ?></p>
                                <table class="form-table">
                                <?php
                                        // Generate the HTML For the settings form.
                                        $this->generate_settings_html();
                                ?>
                                </table>
                                <?php
                        }

                        function payment_fields() {
                                if ($this->description) echo wpautop(wptexturize($this->description));
                        }
                         
                        function thankyou_page() {
                                if ($this->description) echo wpautop(wptexturize($this->description));
                        }

                        function process_payment( $order_id ) {
                                
        			require_once(plugin_dir_path(__FILE__).'coinbase-php'.DIRECTORY_SEPARATOR.'Coinbase.php');
                                global $woocommerce;

                                $order = &new WC_Order( $order_id );
				// Set order status 'on-hold'
                                $order->update_status('on-hold', __('Waiting for payment confirmation from Coinbase.', 'woothemes'));
                                
                                $callbackSecret = get_option("coinbase_callback_secret");
                                if($callbackSecret === false) {
                                	$callbackSecret = sha1(mt_rand());
                                	update_option("coinbase_callback_secret", $callbackSecret);
                                }
                                
                                $name = 'Order #' . $order_id;
				$amount = $order->order_total;
                                $currency = get_woocommerce_currency();
				$custom = $order_id;
				$params = array(
					'description' => $name,
					'callback_url' => get_option('siteurl') . "/?coinbase_callback=" . $callbackSecret,
					'success_url' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))) ."&coinbase_orderpage",
					'info_url' => get_option('siteurl'),
					'cancel_url' => get_option('siteurl'),
				);
				
				$woocommerce->add_error(var_export($params, TRUE));

				try {
					$coinbase = new Coinbase('fb9c14477034b3b3f979d91ddc988cdd6ad71fe56b64cd6426cdbc0e012d8559');
                                	$code = $coinbase->createButton($name, $amount, $currency, $custom, $params)->button->code;
                                } catch (Coinbase_TokensExpiredException $e) {
                                	// To be implemented...
                                } catch (Exception $e) {
                                        $order->add_order_note("Error while processing payment: " . var_export($e, TRUE));
                                        $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.'));
                                        return;
                                } 
                                
                                $woocommerce->cart->empty_cart();
                        
                                return array(
                                        'result'    => 'success',
                                        'redirect'  => "https://coinbase.com/checkouts/$code",
                                );                      
                        }
                }
        }

        include plugin_dir_path(__FILE__).'callback.php';

        function add_coinbase_gateway( $methods ) {
                $methods[] = 'WC_Coinbase'; 
                return $methods;
        }
        
        add_filter('woocommerce_payment_gateways', 'add_coinbase_gateway' );

        add_action('plugins_loaded', 'declareWooCoinbase', 0);
        
        
}
