<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_BitRequest extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'bitrequest';
        $this->icon = plugin_dir_url(__FILE__) . 'assets/bitrequest-icon.png';
        $this->has_fields = false;
        $this->method_title = 'BitRequest';
        $this->method_description = 'Accept Nano payments through BitRequest';

        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->nano_address = $this->get_option('nano_address');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'remove_order_pay_buttons'), 10);
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable BitRequest Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'BitRequest (Nano)',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Payment method description that the customer will see on your checkout.',
                'default' => 'Pay with Nano via BitRequest.',
            ),
            'nano_address' => array(
                'title' => 'Nano Address',
                'type' => 'text',
                'description' => 'Enter your Nano address to receive payments.',
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        wp_enqueue_style('bitrequest-styles', 'https://bitrequest.github.io/assets_styles_lib_bitrequest.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('bitrequest-checkout', 'https://bitrequest.github.io/assets_js_lib_bitrequest_checkout.js', array('jquery'), null, true);
        wp_add_inline_script('bitrequest-checkout', 'window.$ = window.jQuery;', 'before');
        wp_enqueue_style('bitrequest-custom-styles', plugin_dir_url(__FILE__) . 'bitrequest-style.css', array(), '1.0.0');
        wp_enqueue_script('bitrequest-auto-payment', plugin_dir_url(__FILE__) . 'bitrequest-script.js', array('jquery', 'bitrequest-checkout'), null, true);

        global $wp;
        $order_id = 0;
        $order = null;

        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
        }

        wp_localize_script('bitrequest-auto-payment', 'bitrequestData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'order_id' => $order_id,
            'order_total' => $order ? $order->get_total() : 0,
            'order_currency' => $order ? $order->get_currency() : get_woocommerce_currency(),
            'nonce' => wp_create_nonce('bitrequest-payment-nonce'),
            'nano_address' => $this->nano_address,
        ));
    }

    public function payment_fields() {
        // Display description
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Add dropdown for cryptocurrency selection
        ?>
        <div id="bitrequest-payment-option">
            <label for="bitrequest_crypto">Choose cryptocurrency:</label>
            <select name="bitrequest_crypto" id="bitrequest_crypto">
                <option value="nano">Nano</option>
            </select>
        </div>
        <?php
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function receipt_page($order_id) {
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->needs_payment()) {
                echo '<div id="bitrequest-payment-container" data-order-id="' . esc_attr($order_id) . '"></div>';
            } else {
                echo '<p>This order cannot be paid for. Please contact us if you need assistance.</p>';
            }
        } else {
            echo '<p>Invalid order. Please contact support.</p>';
        }
    }

    public function remove_order_pay_buttons($order_id) {
        echo '<style>#order_review #place_order, .woocommerce-order-pay #place_order { display: none !important; }</style>';
    }

    public function check_payment_status($order_id) {
        $order = wc_get_order($order_id);
        
        // Here you would implement the logic to check the payment status
        // This could involve making an API call to BitRequest or checking the blockchain
        
        $payment_received = true; // This should be the result of your check
        
        if ($payment_received) {
            $order->payment_complete();
            return true;
        }
        
        return false;
    }
}