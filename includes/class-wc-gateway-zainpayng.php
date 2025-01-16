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

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Retrieve settings
        $this->title                        = $this->get_option('title');
        $this->description                  = $this->get_option('description');
        $this->enabled                      = $this->get_option('enabled');
        $this->testmode                     = $this->get_option('testmode') === 'yes' ? true : false;
        $this->payment_completion_status    = $this->get_option( 'payment_completion_status' );
        $this->test_secret_key              = $this->get_option('test_secret_key');
        $this->test_public_key              = $this->get_option('test_public_key');
        $this->live_secret_key              = $this->get_option('live_secret_key');
        $this->live_public_key              = $this->get_option('live_public_key');
        $this->zainbox_code                 = $this->get_option('zainbox_code');
        $this->customer_logo_url            = has_custom_logo() ? wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] :
                                                WC_HTTPS::force_https_url( plugin_dir_url(__FILE__) . '../assets/zainpayng-logo.png') ;

        $this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
        $this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

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
//        add_action( 'woocommerce_api_wc_zainpayng_webhook', array( $this, 'process_webhooks' ) );

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
                'title'     => 'Enable/Disable',
                'type'      => 'checkbox',
                'label'     => 'Enable ZainpayNG Payment option on the checkout page',
                'default'   => 'no',
                'desc_tip'  => true,
            ),
            'title'                 => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the payment method title the user sees during checkout.',
                'default'     => 'Debit/Credit Card (ZainpayNG)',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the payment method description the user sees during checkout.',
                'default'     => 'Pay securely using ZainpayNG.',
            ),
            'testmode'                         => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Test mode enables you to test payments before going live. <br />Ensure the LIVE MODE is enabled on your ZainpayNG account before you uncheck this',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'zainbox_code'                  => array(
                'title'       => 'Zainbox Code',
                'type'        => 'text',
                'description' => 'Enter your Zainbox code here',
                'default'     => '',
            ),
            'test_secret_key'                  => array(
                'title'       => 'Test Secret Key',
                'type'        => 'password',
                'description' => 'Enter your Test Secret Key here', 
                'default'     => '',
            ),
            'test_public_key'                  => array(
                'title'       => 'Test Public Key',
                'type'        => 'text',
                'description' => 'Enter your Test Public Key here.',
                'default'     => '',
            ),
            'live_secret_key'                  => array(
                'title'       => 'Live Secret Key',
                'type'        => 'password',
                'description' => 'Enter your Live Secret Key here.', 
                'default'     => '',
            ),
            'live_public_key'                  => array(
                'title'       => 'Live Public Key', 
                'type'        => 'text',
                'description' => 'Enter your Live Public Key here.',
                'default'     => '',
            ),
            'payment_completion_status' => array(
                'title'       => 'Payment Completion Status',
                'type'        => 'select',
                'description' => 'The Status of the order when payment is successful.',
                'default'     => 'wc-processing',
                'desc_tip'    => true,
                'options'     => array(
                    'wc-processing' => 'Processing',
                    'wc-completed'  => 'Completed',
                    'wc-on-hold'    => 'On Hold',
                ),
            ),

        );
    }


    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use() {

        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_zainpayng_supported_currencies', array( 'NGN', 'USD') ) ) ) {

            $this->msg = sprintf( 'ZainpayNG does not support your store currency. Kindly set it to either NGN (&#8358) or USD (&#36;) <a href="%s">here</a>', admin_url( 'admin.php?page=wc-settings&tab=general' )) ;

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
            echo '<div class="error"><p>' . sprintf('Please enter your ZainpayNG merchant and Zainbox code details <a href="%s">here</a> to be able to use the ZainpayNG WooCommerce Payment Plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zainpayng' )) . '</p></div>';
            return;
        }

    }

    /**
     * Process payment and redirect to ZainpayNG
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Zainpay API Request
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

        echo '<div id="wc-zainpayng-form">';

        echo '<p>' .'Thank you for your order, please click the button below to pay with ZainPay.' . '</p>';

        echo '<div id="zainpay_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Gateway_Zainpayng' ) . '"></form><button class="button" id="zainpayng-payment-button">' . 'Pay Now' . '</button>';

        echo '  <a class="button cancel" id="zainpayng-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . 'Cancel order &amp; restore cart' . '</a></div>';

        echo '</div>';

    }
    /**
     * Verify Paystack payment.
     */
    public function verify_zainpayng_transaction() {
        $this->logger->add('zainpayng', 'ZainpayNG Payment Verification' . $_GET['txnRef']);

        if ( isset( $_REQUEST['txnRef'] ) ) {
            $txnRef = sanitize_text_field( $_REQUEST['txnRef'] );
        } else {
            $txnRef = false;
        }
        if( ! $txnRef ) {
            $this->logger->add('zainpayng', 'Transaction Reference not found');
            return;
        }

        @ob_clean();

        $zainpay_response = $this->get_zainpayng_transaction_details($txnRef);

        if(false === $zainpay_response){
            $this->logger->add('zainpayng', 'Failed to verify transaction');
            return;
        }
        $order_details = explode('_', $zainpay_response->data->txnRef);
        $order_id = $order_details[0];
        $order = wc_get_order($order_id);
        $this->logger->add('zainpayng', 'ZainpayNG Payment Verification Result: '. json_encode($zainpay_response));
        if($zainpay_response->code === '00' && $zainpay_response->description === 'successful' && round($order->get_total() * 100) == $zainpay_response->data->depositedAmount ) {
            // TODO: Check if the amount currency is the same as the order amount currency
            $order->payment_complete($txnRef);
            $order->add_order_note('Payment successful. Transaction Reference: ' . $txnRef);
            $order->save();
            $order->update_status($this->payment_completion_status);

        } else {
            $order->update_status('failed');
            $order->add_order_note('Payment failed. Reason: ' . $zainpay_response->description);
            $order->save();
        }

        wp_redirect( $this->get_return_url( $order ) );
        exit;

    }

    /**
     * TODO: Process Webhook.
     */
//    public function process_webhooks() {
//
//        $request_body = file_get_contents('php://input');
//        $request_data = json_decode($request_body, true);
//
//        if (isset($request_data['event'])) {
//            $event = $request_data['event'];
//
//            if ($event === 'payment.success') {
//                $txn_ref = $request_data['data']['txn_ref'];
//                $order_id = $request_data['data']['order_id'];
//                $order = wc_get_order($order_id);
//
//                if ($order) {
//                    $order->payment_complete($txn_ref);
//                    $order->add_order_note('Payment successful. Transaction Reference: ' . $txn_ref);
//                    $order->save();
//
//                    if ($this->is_autocomplete_order_enabled($order)) {
//                        $order->update_status('completed');
//                    }
//                }
//            }
//        }
//
//        wp_send_json(array('status' => 'success'));
//    }

    private function get_zainpay_api_base_url() {
        return $this->testmode ? 'https://sandbox.zainpay.ng' : 'https://api.zainpay.ng/';
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
