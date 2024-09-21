<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_BitRequest_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'bitrequest';

    public function __construct() {
        $this->gateway = new WC_Gateway_BitRequest();
    }

    public function initialize() {
        $this->settings = get_option('woocommerce_bitrequest_settings', array());
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        return array('wc-bitrequest-blocks');
    }

    public function get_payment_method_data() {
        return array(
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->gateway->supports,
        );
    }
}

class WC_Gateway_BitRequest_With_Blocks_Support extends WC_Gateway_BitRequest {
    public function __construct() {
        parent::__construct();
        add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_payment_for_blocks'), 10, 2);
    }

    public function process_payment_for_blocks($response, $payment_context) {
        $order = $payment_context->get_order();
        return $this->process_payment($order->get_id());
    }
}