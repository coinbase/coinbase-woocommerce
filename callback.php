<?php

// Only check for callback if WooCommerce is installed.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        
  function coinbase_callback() {                                
    if(isset($_GET['coinbase_callback'])) {
    
      $secret = $_GET['coinbase_callback'];
      $correct = get_option("coinbase_callback_secret");
      if($secret != $correct) {
        return;
      }
            
      global $woocommerce;
      
      $gateways = $woocommerce->payment_gateways->payment_gateways();
      if (!isset($gateways['coinbase'])) {
        // The Coinbase plugin is not enabled.
        return;
      }
      $gateway = $gateways['coinbase'];
      
      // Verify postback information
      require_once(plugin_dir_path(__FILE__).'coinbase-php'.DIRECTORY_SEPARATOR.'Coinbase.php');
      $oauth = new Coinbase_Oauth($gateway->settings['clientId'], $gateway->settings['clientSecret'], null);
      $tokens = unserialize($gateway->settings['tokens']);
      $coinbase = new Coinbase($oauth, $tokens);
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
        if($orderInfo->status == "completed") {
          // Payment complete!
          $order->payment_complete();
          $order->add_order_note("Received confirmation for payment from Coinbase. Coinbase Order ID: $coinbaseOrderId");
        } else {
          $order->cancel_order("Coinbase payment cancelled.");
        }
      } else {
        throw new Exception("Coinbase: callback for non-pending order $orderId");
      }

    } else if(isset($_GET['coinbase_orderpage'])) {
      // When redirecting to the 'your order was successful' page, Coinbase deletes the 'order'
      // parameter. Here we restore it so the order received page displays correctly.
      $_GET['order'] = $_GET['order']['custom'];
    } else if(isset($_GET['coinbase_ordercancel'])) {
      $order = new WC_Order($_GET['order']['custom']);
      
      if($order->status == "on-hold" && $order->payment_method == "coinbase") {
        $order->cancel_order("User canceled Coinbase order.");
      }
      
      unset($_GET['order']);
    }
  }

  add_action('init', 'coinbase_callback');
}
