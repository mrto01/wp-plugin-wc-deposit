'use strict';

jQuery(document).ready(function ($) {
    (function () {
        let modal = $('#vicodin-deposit-modal');
        let btn = $('.vicodin-deposit-options');
        let span = $('.close', modal);
        let atcText = $('button[name="add-to-cart"]').text();

        btn.click(function () {
            modal.show();
        });

        span.click(function () {
            modal.hide();
        });

        $(window).on('click', function (e) {
            if ($(e.target).is('#vicodin-deposit-modal')) {
                modal.hide();
            }
        });

        $('#vicodin-deposit-check').on('change', function () {
            let dpText = '';
            if (this.checked) {
                dpText = atcText + ' ' + '(deposit)';
            } else {
                dpText = atcText;
            }
            $('button[name="add-to-cart"]').text(dpText);
        });

        $('input[name="vicodin-plan-select"]').on('change', function () {
            let planName = $(this).data('plan_name');
            $('.vicodin-deposit-options span').text(planName);
        });

        $('input[name="vicodin-plan-select"]').first().prop('checked', true);

    })();
})