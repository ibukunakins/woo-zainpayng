<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class ZainpayNG_Gateway_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'zainpayng';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_zainpayng_settings', [] );
        $this->gateway = new WC_Gateway_Zainpayng();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'zainpayng-blocks-integration',
            plugin_dir_url(__FILE__) . '../assets/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'zainpayng-blocks-integration');

        }
        return [ 'zainpayng-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
//            'logo' => plugin_dir_url(__FILE__) . 'assets/zainpayng-logo.png',
//            'testmode' => $this->gateway->testmode,
        ];
    }

}
?>