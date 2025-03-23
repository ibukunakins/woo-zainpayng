jQuery( function( $ ) {

    $( '#wc_zainpayng_form' ).remove();

    // wcZainPayInlinePaymentHandler();

    jQuery( 'button#zainpayng_payment_button' ).click( function() {
        console.log('Button Clicked!');
        // $('#zainpayng_payment_form form#order_review').submit();
        return wcZainPayInlinePaymentHandler();
    } );

    jQuery( '#zainpayng_payment_form form#order_review' ).submit( function() {
        console.log('Form Submitted!');
        return true;
    })

    function handlePaymentCallback(response) {

        switch(response.status.toLowerCase()) {
            case "success":
                // Handle successful payment (e.g., show success message)
                let $paymentForm = $( '#zainpayng_payment_form form#order_review' );
                $paymentForm.append( '<input type="hidden" class="zainpayng_txnref" name="zainpayng_txnref" value="' + response.reference + '"/>' );
                console.log('Payment successful for txnRef: ' + response.reference);
                console.log($paymentForm.attr('action'));
                console.log($paymentForm.attr('method'));
                $paymentForm.submit();
                alert('Payment successful. Please wait while we process your order.');
                return;
            case "failed":
                // Handle failed payment (e.g., show failure message)
                $( '#wc_zainpayng_form' ).show();
                break;
            case "cancelled":
                alert('Payment Cancelled. Please try again to complete your order.');
                $( '#wc_zainpayng_form' ).show();
                break;
        }
    }


    function wcZainPayInlinePaymentHandler() {
        console.log('Payment Handler!');

        let $form = $( '#zainpayng_payment_form form#order_review' )
        zainpayng_txnref = $form.find( 'input.zainpayng_txnref' )
        zainpayng_txnref.val( wc_zainpayng_params.txnRef );


        let amount = Number( wc_zainpayng_params.amount );

        let paymentData = {
            emailAddress: wc_zainpayng_params.emailAddress,
            mobileNumber: wc_zainpayng_params.mobileNumber,
            amount: amount,
            txnRef: wc_zainpayng_params.txnRef,
            currencyCode: wc_zainpayng_params.currencyCode,
            zainboxCode: wc_zainpayng_params.zainboxCode,
            callBackUrl: wc_zainpayng_params.callBackUrl,
            logoUrl: wc_zainpayng_params.logoUrl,
        };
        const zainpayInlineKey = wc_zainpayng_params.inline_key;

        // Initialize Zainpay Inline payment
        zainpayInitPayment(paymentData, handlePaymentCallback, zainpayInlineKey);
    }

} );