<?php
/*
 * Plugin Name: ZainpayNG WooCommerce Payment Gateway
 * Plugin URI: https://zainpay.ng/developers/woocommerce-plugin/
 * Description: ZainpayNG WooCommerce Payment Gateway allows you to accept payment on your WooCommerce store using ZainPayNG.
 * Author: Ibukun Akinlemibola
 * Author URI: https://ibukunakins.me
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: woo-zainpayng
 * Domain Path: /languages
 * Version: 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
define( 'WC_ZAINPAYNG_MAIN_FILE', __FILE__ );
define( 'WC_ZAINPAYNG_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'WC_ZAINPAYNG_VERSION', '1.0' );

add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);
add_action('plugins_loaded', 'zainpayng_gateway_init', 1);
function zainpayng_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    add_action('before_woocommerce_init', 'declare_zainpayng_checkout_blocks_compatibility');

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-zainpayng.php';

    add_filter( 'woocommerce_payment_gateways', 'zainpayng_add_gateway_class', 99 );

    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woo_zainpayng_plugin_action_links' );

    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action( 'woocommerce_blocks_loaded', 'zainpayng_register_payment_method' );
}

// Register Zainpay Payment Gateway
function zainpayng_add_gateway_class($gateways) {
    $currency = get_woocommerce_currency();
    if ($currency === 'NGN' || $currency === 'USD') {
        $gateways[] = 'WC_Gateway_Zainpayng';
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p><strong>ZainpayNG Woocommerce Plugin:</strong> ' . __('Only NGN and USD currencies are supported. Please change your WooCommerce currency to NGN or USD to use ZainpayNG', 'woo-zainpayng').'</p></div>';
        });
    }

    return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
 */
function declare_zainpayng_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action




function woo_zainpayng_plugin_action_links( $links ) : array {

    $settings_link = array(
        'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zainpayng' ) . '" title="' . __( 'View ZainpayNG WooCommerce Settings', 'woo-zainpayng' ) . '">' . __( 'Settings', 'woo-zainpayng' ) . '</a>',
    );

    return array_merge( $settings_link, $links );

}

/**
 * Custom function to register a payment method type

 */
function zainpayng_register_payment_method() : void {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-block-zainpayng.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new ZainpayNG_Gateway_Block );
        }
    );
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'make_billing_phone_field_required' );

// Our hooked in function - $fields is passed via the filter!
function make_billing_phone_field_required( $fields ) {
    $fields['order']['billing_phone']['required'] = true;
    return $fields;
}




// Include the Gateway Class


