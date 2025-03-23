<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Zainpay Payment Gateway Class
 */
class WC_Gateway_Zainpayng extends WC_Payment_Gateway {

    public bool $testmode;
    public string $autocomplete_order;
    public string $test_secret_key;
    public string $test_public_key;
    private string $live_secret_key;
    public string $live_public_key;
    public string $zainbox_code;
    public string $customer_logo_url;
    public string $public_key;
    public string $secret_key;
    public string $payment_completion_status;

    public function __construct() {
        $this->id = 'zainpayng';
        $this->icon = apply_filters('woocommerce_zainpayng_icon', plugin_dir_url(__FILE__) . '../assets/zainpay-logo.png');
        $this->has_fields = false;
        // gateways can support subscriptions, refunds, saved payment methods,
        $this->supports = array(
            'products'
        );
        $this->logger = new WC_Logger();
        $gateway_title = $this->get_option( 'title' );
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->method_title       = sprintf( __( 'ZainPay by BetaStack - %s', 'woo-zainpayng') , $gateway_title );
        $this->method_description = sprintf( __( 'ZainPay by Betastack provides the easiest way to collect money for fast payments using Mastercard, Visa, Verve Cards and Bank Accounts. <a href="%1$s" target="_blank">Sign up</a> for a ZainPay account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'woo-zainpayng' ), 'https://zainpay.ng', 'https://zainpay.ng/merchant/dashboard/settings' );
        // Retrieve settings
        $this->title                        = $this->get_option('title');
        $this->description                  = $this->get_option('description');
        $this->enabled                      = $this->get_option('enabled');
        $this->payment_page                 = $this->get_option('payment_page');
        $this->testmode                     = $this->get_option('testmode') === 'yes' ? true : false;
        $this->payment_completion_status    = $this->get_option( 'payment_completion_status' );
        $this->test_secret_key              = $this->get_option('test_secret_key');
        $this->test_public_key              = $this->get_option('test_public_key');
        $this->test_inline_key              = $this->get_option('test_inline_key');
        $this->live_secret_key              = $this->get_option('live_secret_key');
        $this->live_public_key              = $this->get_option('live_public_key');
        $this->live_inline_key              = $this->get_option('live_inline_key');
        $this->zainbox_code                 = $this->get_option('zainbox_code');
        $this->customer_logo_url            = has_custom_logo() ? wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] :
                                                WC_HTTPS::force_https_url( plugin_dir_url(__FILE__) . '../assets/zainpayng-logo.png') ;

        $this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
        $this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;
        $this->inline_key = $this->testmode ? $this->test_inline_key : $this->live_inline_key;

        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options',
            )
        );

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

        // Payment listener/API hook.
        add_action( 'woocommerce_api_wc_gateway_zainpayng', array( $this, 'verify_zainpayng_transaction' ) );

        // TODO: Webhook listener/API hook.
        //add_action( 'woocommerce_api_wc_zainpayng_webhook', array( $this, 'process_webhooks' ) );

        // Check if the gateway can be used.
        if ( ! $this->is_valid_for_use() ) {
            $this->enabled = false;
        }
    }

    /**
     * Plugin settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'               => array(
                'title'     => __('Enable/Disable', 'woo-zainpayng'),
                'type'      => 'checkbox',
                'label'     => __('Enable ZainpayNG Payment option on the checkout page', 'woo-zainpayng'),
                'default'   => 'no',
                'desc_tip'  => true,
            ),
            'title'                 => array(
                'title'       => __('Title', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('This controls the payment method title the user sees during checkout.', 'woo-zainpayng'),
                'default'     => 'ZainpayNG - Debit/Credit Cards',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'woo-zainpayng'),
                'type'        => 'textarea',
                'description' => __('This controls the payment method description the user sees during checkout.', 'woo-zainpayng'),
                'default'     => __('Pay securely using ZainpayNG.', 'woo-zainpayng'),
            ),
            'testmode'                         => array(
                'title'       => __('Test mode', 'woo-zainpayng'),
                'label'       => __('Enable Test Mode', 'woo-zainpayng'),
                'type'        => 'checkbox',
                'description' => __('Test mode enables you to test payments before going live. Ensure the LIVE MODE is enabled on your ZainpayNG account before you uncheck this', 'woo-zainpayng'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'payment_page'                     => array(
                'title'       => __('Payment Option', 'woo-zainpayng'),
                'type'        => 'select',
                'description' => __('Popup shows the payment popup on the page while Redirect will redirect the customer to ZainPayNG to make payment.', 'woo-zainpayng'),
                'default'     => '',
                'desc_tip'    => false,
                'options'     => array(
                    ''          => __('Select One', 'woo-zainpayng'),
                    'inline'    => __('Popup', 'woo-zainpayng'),
                    'redirect'  => __('Redirect', 'woo-zainpayng'),
                ),
            ),
            'zainbox_code'                  => array(
                'title'       => __('Zainbox Code', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('Enter your Zainbox code here', 'woo-zainpayng'),
                'default'     => '',
            ),
            'test_secret_key'                  => array(
                'title'       => __('Test Secret Key', 'woo-zainpayng'),
                'type'        => 'password',
                'description' => __('Enter your Test Secret Key here', 'woo-zainpayng'),
                'default'     => '',
            ),
            'test_public_key'                  => array(
                'title'       => __('Test Public Key', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('Enter your Test Public Key here.', 'woo-zainpayng'),
                'default'     => '',
            ),
            'test_inline_key'                  => array(
                'title'       => __('Test Inline Payment Key', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('Enter your Inline Payment Key here if you want to use Pop up Payment.', 'woo-zainpayng'),
                'default'     => '',
            ),

            'live_secret_key' => array(
                'title'       => __('Live Secret Key', 'woo-zainpayng'),
                'type'        => 'password',
                'description' => __('Enter your Live Secret Key here.', 'woo-zainpayng'),
                'default'     => '',
            ),
            'live_public_key' => array(
                'title'       => __('Live Public Key', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('Enter your Live Public Key here.', 'woo-zainpayng'),
                'default'     => '',
            ),
            'live_inline_key' => array(
                'title'       => __('Live Inline Payment Key', 'woo-zainpayng'),
                'type'        => 'text',
                'description' => __('Enter your Live Inline Payment Key here if you want to use Pop up Payment.', 'woo-zainpayng'),
                'default'     => '',
            ),
            'payment_completion_status' => array(
                'title'       => __('Payment Completion Status', 'woo-zainpayng'),
                'type'        => 'select',
                'description' => __('The Status of the order when payment is successful.', 'woo-zainpayng'),
                'default'     => 'wc-processing',
                'desc_tip'    => true,
                'options'     => array(
                    'wc-processing' => __('Processing', 'woo-zainpayng'),
                    'wc-completed'  => __('Completed', 'woo-zainpayng'),
                    'wc-on-hold'    => __('On Hold', 'woo-zainpayng'),
                ),
            ),

        );
    }


    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use() {

        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_zainpayng_supported_currencies', array( 'NGN', 'USD') ) ) ) {

            $this->msg = sprintf( __('ZainpayNG does not support your store currency. Kindly set it to either NGN (&#8358) or USD (&#36;) <a href="%s">here</a>', 'woo-zainpayng'), admin_url( 'admin.php?page=wc-settings&tab=general' )) ;

            return false;

        }

        return true;

    }

    /**
     * Check if ZainpayNG gateway is enabled.
     *
     * @return bool
     */
    public function is_available() {

        if ( 'yes' == $this->enabled ) {

            if ( ! ( $this->public_key && $this->secret_key ) ) {

                return false;

            }
            return true;
        }

        return false;

    }


    /**
     * Check if ZainpayNG merchant details is filled.
     */
    public function admin_notices() {

        if ( $this->enabled == 'no' ) {
            return;
        }

        // Check required fields.
        if ( ! ( $this->public_key && $this->secret_key) ) {
            echo '<div class="error"><p>' . sprintf(__('Please enter your ZainpayNG merchant and Zainbox code details <a href="%s">here</a> to be able to use the ZainpayNG WooCommerce Payment Plugin.', 'woo-zainpayng'), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zainpayng' )) . '</p></div>';
            return;
        }

    }

    /**
     * Process payment and redirect to ZainpayNG
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $this->logger->debug('Processing payment for order: ' . $order_id);
        if ( 'redirect' === $this->payment_page ) {
            return $this->process_redirect_payment_option( $order_id );
        }

        if('inline' === $this->payment_page && $_POST['zainpayng_txnref']) {
            $txnRef = sanitize_text_field( $_POST[ 'zainpayng_txnref' ] );
            return $this->verify_inline_payment($order_id, $txnRef);
        }

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        );
    }

    private function process_redirect_payment_option($order_id) {
        $order = wc_get_order($order_id);
        $response = $this->send_zainpayng_request($order);
        $this->logger->add('zainpayng', 'Response: ' . $response->data . ' and ' . $response->description);
        if ($response->code === '00') {
            // Redirect to Zainpay for payment
            return array(
                'result'   => 'success',
                'redirect' => $response->data
            );
        } else {
            wc_add_notice('Payment error: ' . $response['message'], 'error');

            return array(
                'result' => 'failure'
            );
        }
    }


    /**
     * Send API request to Zainpay
     */
    private function send_zainpayng_request($order) {
        $api_url = $this->get_zainpay_api_base_url() . '/zainbox/card/initialize/payment';
        if(has_custom_logo()) {
            $this->customer_logo_url = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0];
        }
        $txnRef =  $order->id .'_'.uniqid();
        $body = json_encode(array(
            'amount'       => $order->get_total(), // Convert to smallest unit
            'emailAddress' => $order->get_billing_email(),
            'currencyCode' => $order->get_currency(),
            'mobileNumber' => $order->get_billing_phone(),
            'txnRef'       => $txnRef,
            'zainboxCode'  => $this->zainbox_code,
            'reference'    => $order->get_order_key(),
            'logoUrl'      => $this->customer_logo_url,
            'callBackUrl'  => WC()->api_request_url( 'WC_Gateway_Zainpayng' )
        ));

        $order->update_meta_data( '_zainpay_txn_ref', $txnRef );
        $order->save();

        $this->logger->add('zainpayng', 'Request: ' . json_encode($body));
        $headers = array(
            'Authorization' => 'Bearer ' . $this->public_key,
            'Content-Type'  => 'application/json',
        );
        $response = wp_remote_post($api_url, array(
                'headers'   => $headers,
                'body'      => $body,
                'timeout'   => 45,
        ));
        $this->logger->add('zainpayng', 'Response: ' . json_encode($response));
        if ( is_wp_error( $response ) && 200 !== wp_remote_retrieve_response_code( $response ) ) {

            return array(
                'result'  => 'error',
                'message' => 'Failed to connect to ZainpayNG. Please try again later.'
            );

        } else {
            $zainpay_response = json_decode( wp_remote_retrieve_body( $response ) );

            return $zainpay_response;
        }
    }

    /**
     * Displays the payment page.
     *
     * @param $order_id
     */
    public function receipt_page( $order_id ) {

        $order = wc_get_order( $order_id );

        echo '<div id="wc_zainpayng_form">';

        echo '<p>' .'Thank you for your order, please click the button below to pay with ZainPay.' . '</p>';

        echo '<div id="zainpayng_payment_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Gateway_Zainpayng' ) . '"></form> <button class="button" id="zainpayng_payment_button">' . __('Pay Now', 'woo-zainpayng') . '</button>';

        echo '  <a class="button cancel" id="zainpayng-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __('Cancel order &amp; restore cart', 'woo-zainpayng') . '</a></div>';

        echo '</div>';

    }
    /**
     * Verify Paystack payment.
     */
    public function verify_zainpayng_transaction() {

        $this->logger->add('zainpayng', 'ZainpayNG Payment Verification' . $_GET['txnRef']);

        $txnRef = false;
        if ( isset( $_REQUEST['txnRef'] ) ) {
            $txnRef = sanitize_text_field( $_REQUEST['txnRef'] );
        }
        if(isset( $_REQUEST['zainpayng_txnref'])){
            $txnRef = sanitize_text_field( $_REQUEST['zainpayng_txnref'] );
        }

        if( ! $txnRef ) {
            $this->logger->add('zainpayng', 'Transaction Reference not found');
            wp_redirect( wc_get_page_by_title( 'Checkout' ) );
            return;
        }

        @ob_clean();

        $zainpay_response = $this->get_zainpayng_transaction_details($txnRef);

        if(false === $zainpay_response){
            $this->logger->add('zainpayng', 'Failed to verify transaction: ' . $txnRef);
            return;
        }
        $order_details = explode('_', $zainpay_response->data->txnRef);
        $order_id = $order_details[0];
        $order = wc_get_order($order_id);
        $this->logger->add('zainpayng', 'ZainpayNG Payment Verification Result: '. json_encode($zainpay_response));
        if($zainpay_response->code === '00' && $zainpay_response->description === 'successful' && round($order->get_total() * 100) == $zainpay_response->data->depositedAmount ) {
            // TODO: Check if the amount currency is the same as the order amount currency
            $order->payment_complete($txnRef);
            $order->add_order_note(__('Payment successful. Transaction Reference: ', 'woo-zainpayng') . $txnRef);
            $order->save();
            $order->update_status($this->payment_completion_status);

        } else {
            $order->update_status('failed');
            $order->add_order_note(__('Payment failed. Reason: ', 'woo-zainpayng') . $zainpay_response->description);
            $order->save();
            wp_redirect( $order->get_checkout_payment_url( true ) );
        }

        wp_redirect( $this->get_return_url( $order ) );
        exit;

    }

    public function payment_scripts() {

        if ( isset( $_GET['pay_for_order'] ) || ! is_checkout_pay_page() ) {
            return;
        }

        if ( $this->enabled === 'no' ) {
            return;
        }

        $order_key = urldecode( $_GET['key'] );
        $order_id  = absint( get_query_var( 'order-pay' ) );

        $order = wc_get_order( $order_id );

        if ( $this->id !== $order->get_payment_method() ) {
            return;
        }


        wp_enqueue_script( 'jquery' );

        wp_enqueue_script( 'zainpayng', $this->get_zainpayng_inline_js_url(), array( 'jquery' ), WC_ZAINPAYNG_VERSION, false );

        wp_enqueue_script( 'wc_zainpayng', plugins_url( 'assets/zainpayng' . '.js', WC_ZAINPAYNG_MAIN_FILE ), array( 'jquery', 'zainpayng' ), WC_ZAINPAYNG_VERSION, false );

        if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

            $email         = $order->get_billing_email();
            $amount        = $order->get_total() ;
            $txnRef        = $order_id . '_' . uniqid();
            $the_order_id  = $order->get_id();
            $the_order_key = $order->get_order_key();
            $currency      = $order->get_currency();
            $mobileNumber  = $order->get_billing_phone();


            if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

                $zainpayng_params['emailAddress']    = $email;
                $zainpayng_params['mobileNumber'] = $mobileNumber;
                $zainpayng_params['amount']   = absint( $amount );
                $zainpayng_params['txnRef']   = $txnRef;
                $zainpayng_params['currencyCode'] = $currency;
                $zainpayng_params['zainboxCode'] = $this->zainbox_code;
                $zainpayng_params['reference'] = $the_order_key;
                $zainpayng_params['logoUrl'] = $this->customer_logo_url;
                $zainpayng_params['inline_key'] = $this->inline_key;
                $zainpayng_params['callBackUrl'] = WC()->api_request_url( 'WC_Gateway_Zainpayng' );

            }


            $order->update_meta_data( '_zainpay_txn_ref', $txnRef );
            $order->save();
        }

        wp_localize_script( 'wc_zainpayng', 'wc_zainpayng_params', $zainpayng_params );
    }


    public function verify_inline_payment_option($order_id, $paymentRef) {
        $order = wc_get_order($order_id);
        $txnRef = $order->get_meta('_zainpay_txn_ref');
        $this->logger->add('zainpayng', 'Verifying Inline Payment Option for order: ' . $order_id . ' with txnRef: ' . $txnRef . ' and paymentRef: ' . $paymentRef);

        if($txnRef !== $paymentRef) {
            wc_add_notice('Payment error: Transaction reference does not match order', 'error');
            return array(
                'result' => 'failure'
            );
        }
        // verify the payment status of the transaction reference
        $zainpay_response = $this->get_zainpayng_transaction_details($paymentRef);
        if(false === $zainpay_response){
            $this->logger->add('zainpayng', 'Failed to verify transaction: ' . $txnRef);
            return array(
                'result'  => 'failure',
                'message' => 'Failed to verify transaction. Please try again later.'
            );
        }






       wp_redirect( $this->get_return_url( $order ) );
        exit;

    }

    private function get_zainpay_api_base_url() {
        return $this->testmode ? 'https://sandbox.zainpay.ng' : 'https://api.zainpay.ng/';
    }

    private function get_zainpayng_inline_js_url() {
        return $this->testmode ? 'https://dev.zainpay.ng/v1/zainpay-inline.js' : 'https://api.zainpay.ng/v1/zainpay-inline.js';
    }

    private function get_zainpayng_transaction_details($reference) {
        $api_url = $this->get_zainpay_api_base_url() . '/virtual-account/wallet/deposit/verify/v2/' . $reference;

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->public_key
            ),
            'timeout' => 30
        ));

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            return json_decode( wp_remote_retrieve_body( $response ) );
        }

        return false;
    }

}
