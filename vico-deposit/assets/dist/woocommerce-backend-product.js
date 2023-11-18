'use strict';

jQuery(document).ready(function ($) {
    var vicodin = {};
    $('.vicodin-plan-schedule').each( function (){
        loadEvent(this);
    });
    $('#vicodin_deposit_plan').select2({
        placeholder: 'Select exists plans'
    });
    $('#vicodin_deposit_type').on('change', function () {
        let type = $(this).val();
        if (type == 'global') {
            $('#vicodin_deposits_tab_data .wc-metaboxes-wrapper').addClass('hidden');
        } else {
            $('#vicodin_deposits_tab_data .wc-metaboxes-wrapper').removeClass('hidden');
        }
    })

    function loadEvent( lastTable = null ) {
        // because have many plan tables, need to differ which one is.

        if (!lastTable) {
            lastTable = $('.vicodin-plan-schedule:last');
        }

        vicodin.validatePlan = function () {
            let check = true;
            let nameInput = $('input[name="plan-name"]', lastTable);
            let nameValue = $(nameInput).val().toString().trim();
            // let totalAmount = vicodin.schedule.calPartialPayment();

            if (nameValue === '') {
                $('.error-message.name').remove();
                let text = wp.i18n.__('Plan name required!', 'vico-deposit-and-installment');
                let errorMess = $('<div class="error-message name">' + text + '</div>');
                errorMess.insertAfter($(nameInput));
                check = false;
            } else {
                $('.error-message.name').remove();
                check = true;
            }
            // if (totalAmount > 100 || totalAmount < 100) {
            //    $('.error-message.total').remove();
            //    let text = wp.i18n.__('Total should be equal 100%', 'vico-deposit-and-installment');
            //    let errorMess = $('<div class="error-message total">' + text + '</div>')
            //    errorMess.insertAfter($('.table.vicodin-schedule'));
            //    check = false;
            // } else {
            //    $('.error-message.total').remove();
            //    check = true;
            // }
            return check;
        }

        $('input[name="plan-name"]', lastTable).on('input', function () {

            $(lastTable).closest('.wc-metabox').find('.vicodin-plan-name').text($(this).val());
        })

        $('.increase-field', lastTable).on('click', function (e) {
            let tbody = this.closest('tbody');
            let currRow = this.closest('tr');
            let nextRow = $(currRow).clone(true);
            $(nextRow).find('select').val($(currRow).find('select').val());
            $(tbody).append(nextRow);
            $(this).addClass('hidden');
            $('.decrease-field', currRow).removeClass('hidden');
            if (tbody.children.length > 3) {
                $(tbody).children(':last').find('.decrease-field').removeClass('hidden');
            }
            vicodin.schedule.calPartialPayment(lastTable);
            vicodin.schedule.calDuration(lastTable);
        });

        $('.decrease-field', lastTable).on('click', function (e) {
            let tbody = this.closest('tbody');
            let currRow = this.closest('tr');
            currRow.remove();
            if (tbody.children.length <= 3) {
                $(tbody).children(':last').find('.decrease-field').addClass('hidden');
                $(tbody).children(':last').find('.increase-field').removeClass('hidden');
            } else {
                $(tbody).children(':last').find('.increase-field').removeClass('hidden');
            }
            vicodin.schedule.calPartialPayment(lastTable);
            vicodin.schedule.calDuration(lastTable);
        });

        $('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).on('input', function () {
            console.log($('input[name="deposit-amount"]'));
            vicodin.schedule.calPartialPayment(lastTable);
            vicodin.schedule.calDuration(lastTable);
        });

        $('input[name="partial-day"]', lastTable).on('input', function () {
            vicodin.schedule.calDuration(lastTable);
        });

        $('select[name="partial-date"]', lastTable).on('change', function () {
            vicodin.schedule.calDuration();
        })

        var woo_currency_symbol = $('.woo-currency-symbol', lastTable).text();

        $('#vicodin_unit_type').on('change', function () {
            let type = $(this).val();
            if (type == 'fixed') {
                $('.woo-currency-symbol', lastTable).text(woo_currency_symbol);
            } else {
                $('.woo-currency-symbol', lastTable).text('%');
            }
        }).change();

        $('.vicodin-remove-plan.delete').on('click', function () {
            $(this).closest('.wc-metabox').remove();
        });
    }
    vicodin.schedule = {
        calPartialPayment: function (lastTable) {
            let total = 0;
            $('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).each(function () {
                let partial = parseInt($(this).val());
                if (!isNaN(partial)) {
                    total += partial;
                }
            })
            $('.partial-total', lastTable).text(total);
            return total;
        },

        calDuration: function (lastTable) {
            let duration = {
                'Years': 0,
                'Months': 0,
                'Days': 0
            }

            $('input[name="partial-day"]', lastTable).each(function () {
                let numberOfDay = parseInt($(this).val());
                if (!isNaN(numberOfDay)) {
                    let dateType = $(this).closest('tr').find('select[name="partial-date"]').val();
                    if (dateType == 'day') {
                        duration.Days += numberOfDay
                    } else if (dateType == 'month') {
                        duration.Months += numberOfDay;
                    } else if (dateType == 'year') {
                        duration.Years += numberOfDay;
                    }
                }
            })

            let durationText = [];
            for (let key in duration) {
                let dayValue = duration[key]
                if (dayValue > 0) {
                    durationText.push(dayValue + ' ' + key);
                }
            }

            durationText = durationText.join(', ');
            $('.partial-duration', lastTable).text(durationText);
            return durationText;
        }
    }

    $('.vicodin-new-custom-plan').on('click', getPlanTemplate);
    $('.vicodin-save-custom-plan').on('click', saveCustomPlans);

    function loadAnimation() {
        $('.wc-metaboxes-wrapper.vicodin-loader').addClass('blockUI blockOverlay');
    }
    function clearAnimation() {
        $('.wc-metaboxes-wrapper.vicodin-loader').removeClass('blockUI blockOverlay');
    }
    function getPlanTemplate() {
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'get',
            dataType: 'html',
            data: {
                '_ajax_nonce': vicodinParams.nonce,
                'action': 'vicodin_get_new_plan_template',
            },
            beforeSend: function () {
                $('.vicodin-new-custom-plan').unbind();
                loadAnimation();
            },
            success: function (data) {
                $('#vicodin_deposits_tab_data .wc-metaboxes').append(data);
                loadEvent();
            },
            error: function (xhr, status, err) {
                console.log(err)
            },
            complete: function () {
                $('.vicodin-new-custom-plan').on('click', getPlanTemplate);
                clearAnimation();
            }
        });
    }

    function saveCustomPlans() {
        let data = getFormData();
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'post',
            dataType: 'json',
            data: {
                '_ajax_nonce': vicodinParams.nonce,
                'action': 'vicodin_save_custom_plans',
                'post_id' : $('#post_ID').val(),
                'data' : data,
                'exists_plans': JSON.stringify($('#vicodin_deposit_plan').val()),
            },
            beforeSend: loadAnimation,
            success: function (data) {
                console.log(data);
            },
            error: function(err) {
                console.log(err)
            },
            complete: clearAnimation
        });
    }

    function getFormData() {
        let data = [];
        $('.vicodin-plan-schedule').each(function () {
            let plan = {
                'plan-name' : $('input[name="plan-name"]', this).val(),
                'deposit' : $('input[name="deposit-amount"]', this).val(),
                'deposit-fee' : $('input[name="deposit-fee"]', this).val(),
                'unit-type' : $('select[name="unit-type"]', this).val(),
                'plan-schedule': []
            };
            let planForm = $(this).find('tbody').children();
            $(planForm).each(function (i) {
                if (i > 1) {
                    let partial = $('input[name="partial-payment"]', this).val();
                    let after = $('input[name="partial-day"]', this).val();
                    let dateType = $('select[name="partial-date"]', this).val();
                    let fee = $('input[name="partial-fee"]', this).val();
                    plan['plan-schedule'].push({
                        partial,
                        after,
                        'date-type': dateType,
                        fee
                    });
                }
            })
            plan['duration'] = vicodin.schedule.calDuration(this);
            plan['total'] = vicodin.schedule.calPartialPayment(this);
            data.push(plan);
        });
        return JSON.stringify(data);
    }

});