jQuery(document).ready(function($) {
    if (typeof bitrequestData !== 'undefined') {
        var orderId = $('#bitrequest-payment-container').data('order-id') || bitrequestData.order_id;
        console.log('BitRequest: Order ID:', orderId);
        
        var payment = "nano"; 
        var uoa = bitrequestData.order_currency.toLowerCase();
        var amount = bitrequestData.order_total;
        var address = bitrequestData.nano_address;
        var d = btoa(JSON.stringify({
            "t": "Order #" + orderId,
            "n": "WooCommerce Order",
            "c": 0,
            "pid": orderId
        }));

        var request_url = "https://bitrequest.github.io/?payment=" + payment + "&uoa=" + uoa + "&amount=" + amount + "&address=" + address + "&d=" + d;

        var $paymentButton = $('<a>', {
            href: request_url,
            class: 'br_checkout',
            text: 'Click here to try again',
            style: 'display: none;'
        });

        $('#bitrequest-payment-container').append($paymentButton);

        $paymentButton.trigger('click');

        window.result_callback = function(post_data) {
            console.log('Payment result:', post_data);
            if (post_data.txdata && post_data.txdata.status === 'paid') {
                $.ajax({
                    url: bitrequestData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bitrequest_confirm_payment',
                        order_id: orderId,
                        nonce: bitrequestData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            console.error('Payment confirmation failed:', response.data);
                            alert('Payment confirmation failed. Please contact support.');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX error:', textStatus, errorThrown);
                        alert('An error occurred. Please contact support.');
                    }
                });
            } else if (post_data.status === 'cancelled') {
                window.location.href = wc_checkout_params.cart_url;
            }
        };
    } else {
        console.error('BitRequest: bitrequestData is undefined');
    }
});