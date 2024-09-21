<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add a debug log function
function bitrequest_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Log when the plugin is loaded
bitrequest_debug_log('BitRequest plugin loaded');

// Add this near the top of the file, after the existing debug function
function bitrequest_log_url($url) {
    bitrequest_debug_log('BitRequest URL: ' . $url);
}

/*
Plugin Name: BitRequest WooCommerce Payment Gateway
Description: Accept Nano payments through WooCommerce using BitRequest
Version: 1.0
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    bitrequest_debug_log('WooCommerce is not active');
    return;
}

// Add the gateway to WooCommerce
function add_bitrequest_gateway($methods) {
    bitrequest_debug_log('Adding BitRequest gateway to WooCommerce');
    $methods[] = 'WC_Gateway_BitRequest';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_bitrequest_gateway');

// Initialize the gateway
function init_bitrequest_gateway() {
    bitrequest_debug_log('Initializing BitRequest gateway');
    if (class_exists('WC_Payment_Gateway')) {
        require_once('class-wc-gateway-bitrequest.php');
        // Create an instance of the gateway
        $GLOBALS['bitrequest_gateway'] = new WC_Gateway_BitRequest();
    } else {
        bitrequest_debug_log('WC_Payment_Gateway class does not exist');
    }
}
add_action('plugins_loaded', 'init_bitrequest_gateway', 11);

// Register BitRequest for WooCommerce Blocks
function bitrequest_register_gateway_for_blocks() {
    bitrequest_debug_log('Registering BitRequest for WooCommerce Blocks');
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once('class-wc-gateway-bitrequest.php'); // Add this line
        require_once('class-wc-gateway-bitrequest-blocks.php');
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_Gateway_BitRequest_Blocks_Support());
            }
        );
    } else {
        bitrequest_debug_log('AbstractPaymentMethodType class does not exist');
    }
}
add_action('woocommerce_blocks_loaded', 'bitrequest_register_gateway_for_blocks');

// Enqueue scripts and styles
function bitrequest_enqueue_scripts() {
    bitrequest_debug_log('bitrequest_enqueue_scripts called');
    if (is_checkout() || is_cart()) {
        bitrequest_debug_log('Enqueuing BitRequest scripts and styles');
        wp_enqueue_style('bitrequest-style', plugins_url('bitrequest-style.css', __FILE__));
        wp_enqueue_script('bitrequest-script', plugins_url('bitrequest-script.js', __FILE__), array('jquery'), '1.0', true);
        
        // Enqueue the blocks script
        wp_enqueue_script(
            'wc-bitrequest-blocks',
            plugins_url('bitrequest-blocks.js', __FILE__),
            array('wp-element', 'wp-components', 'wc-blocks-registry', 'wp-hooks'),
            '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'bitrequest_enqueue_scripts');

// Add support for WooCommerce Blocks
function bitrequest_gateway_class_name($class_name) {
    bitrequest_debug_log('bitrequest_gateway_class_name called with: ' . $class_name);
    if ($class_name === 'WC_Gateway_BitRequest') {
        return 'WC_Gateway_BitRequest_With_Blocks_Support';
    }
    return $class_name;
}
add_filter('woocommerce_payment_gateways_class_name', 'bitrequest_gateway_class_name');

// Add a test function to check if the plugin is loaded correctly
function bitrequest_test_function() {
    bitrequest_debug_log('BitRequest test function called');
}
add_action('init', 'bitrequest_test_function');

// Add this after the add_action('plugins_loaded', 'init_bitrequest_gateway', 0); line
add_action('woocommerce_thankyou', 'bitrequest_log_order_url', 10, 1);

function bitrequest_log_order_url($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_payment_method() === 'bitrequest') {
        $bitrequest_url = $order->get_meta('_bitrequest_url');
        bitrequest_log_url($bitrequest_url);
    }
}

// Modify this part
function bitrequest_thankyou_page($order_id) {
    global $bitrequest_gateway;
    if (isset($bitrequest_gateway) && method_exists($bitrequest_gateway, 'thankyou_page')) {
        $bitrequest_gateway->thankyou_page($order_id);
    } else {
        bitrequest_debug_log('BitRequest gateway or thankyou_page method not available');
    }
}
add_action('woocommerce_thankyou', 'bitrequest_thankyou_page', 10);

// Add new actions for confirmation and cancellation
add_action('wp_ajax_bitrequest_confirm_payment', 'bitrequest_confirm_payment');
add_action('wp_ajax_nopriv_bitrequest_confirm_payment', 'bitrequest_confirm_payment');

function bitrequest_confirm_payment() {
    check_ajax_referer('bitrequest-payment-nonce', 'nonce');

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($order_id === 0) {
        wp_send_json_error('Invalid order ID');
        return;
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error('Invalid order');
        return;
    }

    try {
        $order->update_status('processing', __('Payment confirmed via BitRequest.', 'woocommerce'));
        $order->set_payment_method_title('BitRequest (Nano)');
        $order->add_order_note('Payment completed via BitRequest');
        $order->save();

        wp_send_json_success(array(
            'redirect_url' => $order->get_checkout_order_received_url()
        ));
    } catch (Exception $e) {
        wp_send_json_error('Error updating order: ' . $e->getMessage());
    }
}

function bitrequest_cancel_order() {
    if (isset($_GET['cancel_order']) && isset($_GET['order_id']) && wp_verify_nonce($_GET['_wpnonce'], 'woocommerce-cancel_order')) {
        $order_id = absint($_GET['order_id']);
        $order = wc_get_order($order_id);
        
        if ($order && $order->needs_payment()) {
            $order->update_status('cancelled', __('Order cancelled by customer.', 'woocommerce'));
            WC()->cart->restore_cart();
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }
}
add_action('init', 'bitrequest_cancel_order');
