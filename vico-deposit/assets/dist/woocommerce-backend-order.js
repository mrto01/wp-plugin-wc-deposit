'use strict';

jQuery(document).ready(function ($) {
    var vicodin_wc_order = {
        init: function () {
            $('.page-title-action:contains("prevent_create_suborder")').remove();
            this.watch_totals();
        },

        watch_totals: function () {
            let order_info = $('#woocommerce-order-items .inside');
            let watcher = new MutationObserver(function () {
                let meta_box_wrapper = $('#vicodin_deposit_partial_payments .inside');
                vicodin_wc_order.get_installment_summary(meta_box_wrapper);
            });
            if ( $(order_info).length > 0 ) {
                watcher.observe(order_info[0], {childList: true});
            }
        },

        get_installment_summary: function (meta_box_wrapper) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_reload_payment_meta_box',
                    'order_id': $('#post_ID').val()
                },
                beforeSend: function () {
                    $(meta_box_wrapper).addClass('blockUI blockOverlay');
                },
                success: function ( response ) {
                    $(meta_box_wrapper).html( response.data.html );
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {
                    $(meta_box_wrapper).removeClass('blockUI blockOverlay');
                }
            });
        }
    };

    vicodin_wc_order.init();
});