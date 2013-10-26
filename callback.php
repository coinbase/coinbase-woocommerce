<?php

// Only check for callback if WooCommerce is installed.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{
        
        function coinbase_callback()
        {                                
                if(isset($_GET['coinbase_callback']))
                {
                
                	$secret = $_GET['coinbase_callback'];
                	$correct = get_option("coinbase_callback_secret");
                	if($secret != $correct) {
                		return;
                	}
                        
                        global $woocommerce;
                        
                        $gateways = $woocommerce->payment_gateways->payment_gateways();
                        if (!isset($gateways['coinbase']))
                        {
                                // The Coinbase plugin is not enabled.
                                return;
                        }
                        $bp = $gateways['coinbase'];
                        
                        // Verify postback information
                        require_once(plugin_dir_path(__FILE__).'coinbase-php'.DIRECTORY_SEPARATOR.'Coinbase.php');
                        $coinbase = new Coinbase('fb9c14477034b3b3f979d91ddc988cdd6ad71fe56b64cd6426cdbc0e012d8559');
                        $postBody = json_decode(file_get_contents('php://input'));
                        $coinbaseOrderId = $postBody->order->id;
                        $orderInfo = $coinbase->getOrder($coinbaseOrderId);
                        if(!$orderInfo) {
                        	throw new Exception("Coinbase: callback for bad order ID $coinbaseOrderId");
                        	return; // Invalid callback.
                        }
                        
                        if($orderInfo->status != "completed") {
                        	$status = $orderInfo->status;
                        	throw new Exception("Coinbase: callback for order status $status");
                        	return; // Payment not complete
                        }
                        
                        $orderId = $orderInfo->custom;
                        $order = new WC_Order($orderId);
                        
                        if(in_array($order->status, array('on-hold', 'pending', 'failed'))) {
                        	// Payment complete!
                        	$order->payment_complete();
                        	$order->add_order_note("Received confirmation for payment from Coinbase. Coinbase Order ID: $coinbaseOrderId");
                        } else {
                        	throw new Exception("Coinbase: callback for non-pending order $orderId");
                        }

                } else if(isset($_GET['coinbase_orderpage'])) {
                	// When redirecting to the 'your order was successful' page, Coinbase deletes the 'order'
                	// parameter. Here we restore it so the order received page displays correctly.
                	$_GET['order'] = $_GET['order']['custom'];
                }
        }


        add_action('init', 'coinbase_callback');
        
}
