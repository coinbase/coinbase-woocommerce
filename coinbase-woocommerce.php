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
                         
                                add_action('woocommerce_update_options_payment_gateways_coinbase', array(&$this, 'process_admin_options'));
                        }
                        
                        function process_admin_options() {
                        
                        	// Handle the OAuth settings
                        	
                        	return parent::process_admin_options();
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
                                        'tokens' => array(
                                                'title' => __('Merchant Account', 'woothemes'),
                                                'type' => 'text',
                                                'description' => __(''),
                                        ),
                                        'clientId' => array(
                                                'title' => __('Client ID', 'woothemes'),
                                                'type' => 'text',
                                                'description' => __(''),
                                        ),
                                        'clientSecret' => array(
                                                'title' => __('Client Secret', 'woothemes'),
                                                'type' => 'text',
                                                'description' => __(''),
                                        ),
                                );
                        }
                                
                        public function admin_options() {
                        
				$pageUrl = 'http';
				if ($_SERVER["HTTPS"] == "on") {
					$pageUrl .= "s";
				}
				$pageUrl .= "://";
				if ($_SERVER["SERVER_PORT"] != "80") {
					$pageUrl .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
				} else {
					$pageUrl .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
				}
				$params = $_GET;
				$params['coinbase_oauth_callback'] = true;
				unset($params['saved']); // Do not include 'saved' parameter in redirect URL
				unset($params['code']); // Do not include 'code' parameter in redirect URL
                        
                        	$redirectUrl = strtok($pageUrl,'?');
                        	$redirectUrlWithQuery = $redirectUrl . "?" . http_build_query($params);
                        	
                        	unset($params['coinbase_oauth_callback']);
                        	$formSubmitUrl = $redirectUrl . "?" . http_build_query($params);
                        	
                        	require_once(plugin_dir_path(__FILE__).'coinbase-php'.DIRECTORY_SEPARATOR.'Coinbase.php');
                        	$oauth = new Coinbase_Oauth($this->settings['clientId'], $this->settings['clientSecret'], $redirectUrlWithQuery);
                        	$oauthUrl = $oauth->createAuthorizeUrl('merchant');
                        	
                        	if(isset($_GET['coinbase_oauth_callback'])) {
                        		// This page request is a callback from OAuth authorize
                        		// Get tokens
                        		$tokens = $oauth->getTokens($_GET['code']);
                        		// They are saved in the javascript below
                        	}
                        
                                ?>
                                <h3><?php _e('Coinbase', 'woothemes'); ?></h3>
                                <p><?php _e('Accept bitcoin with Coinbase.', 'woothemes'); ?></p>
                                <table class="form-table">
                                <?php
                                        // Generate the HTML For the settings form.
                                        $this->generate_settings_html();
                                ?>
                                </table>
                                <style type="text/css">
                                	.woocommerce_coinbase_list {
                                		list-style: disc outside none;
                                	}
                                	.woocommerce_coinbase_list > li {
                                		margin-left: 30px;
                                	}
                                </style>
                                <script type="text/javascript">
                                	function coinbase_processForm() {
		                        	maInput = document.getElementById('woocommerce_coinbase_tokens');
		                        	maInput.style.display = 'none';
		                        	var instructions = document.createElement("p");
		                        	
		                        	var haveTokens = maInput.value.length > 0;
		                        	var haveClient = document.getElementById('woocommerce_coinbase_clientId').value.length > 0;
		                        	
		                        	if (haveTokens) {
		                        		ihtml = "<b>Merchant account connected.</b> <a href=\"javascript:coinbase_disconnect();\">Disconnect account</a>";
		                        	} else if (haveClient) {
		                        		ihtml = "<b>No merchant account connected.</b> To connect a merchant account, <a href=\"<?php echo $oauthUrl; ?>\">click here</a>.";
		                        	} else {
		                        		ihtml = "<b>No merchant account connected.</b> To connect a merchant account, go to <a href=\"https://coinbase.com/oauth/applications/new\">https://coinbase.com/oauth/applications/new</a> and enter the following values:<br><ul class='woocommerce_coinbase_list'><li><b>Name:</b> a name for this WooCommerce installation.</li><li><b>Redirect uri:</b> <input type='text' value='<?php echo $redirectUrl; ?>' readonly></li></ul>Click 'Create Application' and then copy the generated Client ID and Client Secret into the fields below.";
		                        	}
		                        	
		                        	instructions.innerHTML = ihtml;
		                        	maInput.parentNode.insertBefore(instructions, maInput.nextSibling);
		                        	
		                        	<?php if(isset($tokens)) { ?>
		                        		document.getElementById('woocommerce_coinbase_tokens').value = <?php echo json_encode(serialize($tokens)); ?>;
		                        		document.getElementById('woocommerce_coinbase_tokens').form.action = <?php echo json_encode($formSubmitUrl); ?>;
		                        		document.getElementById('woocommerce_coinbase_tokens').form.submit();
		                        	<?php } ?>
                                	}
                                	coinbase_processForm();
                                	
                                	function coinbase_disconnect() {
	                        		document.getElementById('woocommerce_coinbase_tokens').value = "";
	                        		document.getElementById('woocommerce_coinbase_clientId').value = "";
	                        		document.getElementById('woocommerce_coinbase_clientSecret').value = "";
	                        		document.getElementById('woocommerce_coinbase_tokens').form.submit();
                                	}
                                </script>
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
					'cancel_url' => get_option('siteurl') . "?coinbase_ordercancel",
				);
				
				$oauth = new Coinbase_Oauth($this->settings['clientId'], $this->settings['clientSecret'], null);
				$tokens = unserialize($this->settings['tokens']);
				if($tokens == "") {
		                	$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)'));
		                	return;
				}

				try {
					$coinbase = new Coinbase($oauth, $tokens);
                                	$code = $coinbase->createButton($name, $amount, $currency, $custom, $params)->button->code;
                                } catch (Coinbase_TokensExpiredException $e) {
                                	// Try to refresh tokens
                                	try {
                                		$tokens = $oauth->refreshTokens($tokens);
                                	} catch (Exception $e) {
                                        	$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (token refresh error)'));
                                        	return;
                                	}
                                	
                                	// Save tokens
                                	$settings = get_option('woocommerce_coinbase_settings');
                                	$settings['tokens'] = serialize($tokens);
                                	update_option('woocommerce_coinbase_settings', $settings);
                                	$coinbase = new Coinbase($oauth, $tokens);
                                	
                                	// Try again
                                	$code = $coinbase->createButton($name, $amount, $currency, $custom, $params)->button->code;
                                } catch (Exception $e) {
                                        $order->add_order_note("Error while processing payment: " . var_export($e, TRUE));
                                        $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (' . $e->getMessage() . ')'));
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
