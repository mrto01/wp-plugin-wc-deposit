'use strict';

jQuery(document).ready(function ($) {
    var vicodin = {};
    vicodin.schedule = {
        calcPartialPayment: function (lastTable) {
            let total = 0;
            let unitType = $(lastTable).find('#vicodin_unit_type').val();
            $('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).each(function () {
                let partial = parseInt($(this).val());
                if (!isNaN(partial)) {
                    total += partial;
                }
            })
            $('.partial-total', lastTable).text(total);
            if( unitType == 'percentage' && total > 100 || unitType == 'percentage' && total < 100) {
                let error = $('<p>',{
                    text: 'Total should be equal 100%',
                    class: 'vicodin-error-message'
                })
                if ( $(lastTable).find('.vicodin-error-message').length == 0 ) {
                    $(lastTable).append(error);
                }

            }else {
                $(lastTable).find('.vicodin-error-message').remove();
            }
            return total;
        },

        calcDuration: function (lastTable) {
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
    vicodin.validateNumber = function ( input ) {
        let value = Math.abs( parseInt( $(input).val() ) );
        if ( isNaN(value)) {
            $(input).val('');
        }else {
            $(input).val(value);
        }
    }
    $('#product-type').on('change', function () {
        let type = $(this).val();

        if ( type === 'grouped' || type === 'external') {
            $('.vicodin_deposit_tab').hide();
        }else {
            $('.vicodin_deposit_tab').show();
        }
    } );

    $('.vicodin-plan_schedule').each( function (){
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
            lastTable = $('.vicodin-plan_schedule:last');
        }

        $('input[name="plan_name"]', lastTable).on('input', function () {

            $(lastTable).closest('.wc-metabox').find('.vicodin-plan_name').text($(this).val());
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
            vicodin.schedule.calcPartialPayment(lastTable);
            vicodin.schedule.calcDuration(lastTable);
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
            vicodin.schedule.calcPartialPayment(lastTable);
            vicodin.schedule.calcDuration(lastTable);
        });

        $('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).on('change', function () {
            vicodin.schedule.calcPartialPayment(lastTable);
            vicodin.validateNumber(this);
            vicodin.schedule.calcDuration(lastTable);
        });

        $('input[name="partial-day"]', lastTable).on('change', function () {
            vicodin.validateNumber(this);
            vicodin.schedule.calcDuration(lastTable);
        });

        $('select[name="partial-date"]', lastTable).on('change', function () {
            vicodin.schedule.calcDuration(lastTable);
        })
        $('input[name="partial-fee"], input[name="deposit_fee"]', lastTable).on('change', function () {
            vicodin.validateNumber(this);
        })

        var woo_currency_symbol = $('.woo-currency-symbol', lastTable).text();

        $(lastTable).find('#vicodin_unit_type').on('change', function () {
            let type = $(this).val();
            if (type == 'fixed') {
                $('.woo-currency-symbol', lastTable).text(woo_currency_symbol);
            } else {
                $('.woo-currency-symbol', lastTable).text('%');
            }
            vicodin.schedule.calcPartialPayment(lastTable);
        }).change();

        $('.vicodin-remove-plan.delete').on('click', function ( e ) {
            e.preventDefault();
            $(this).closest('.wc-metabox').remove();

        });
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
                'nonce': vicodinParams.nonce,
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
                'nonce': vicodinParams.nonce,
                'action': 'vicodin_save_custom_plans',
                'post_id' : $('#post_ID').val(),
                'data' : data,
                'exists_plans': JSON.stringify($('#vicodin_deposit_plan').val()),
            },
            beforeSend: loadAnimation,
            success: function (response) {
                console.log(response.data);
            },
            error: function(err) {
                console.log(err)
            },
            complete: clearAnimation
        });
    }

    function getFormData() {
        let data = [];
        let fee_total = 0;
        $('.vicodin-plan_schedule').each(function () {
            let plan = {
                'plan_name' : $('input[name="plan_name"]', this).val(),
                'deposit' : $('input[name="deposit-amount"]', this).val(),
                'deposit_fee' : $('input[name="deposit_fee"]', this).val(),
                'unit-type' : $('select[name="unit-type"]', this).val(),
                'plan_schedule': []
            };
            let planForm = $(this).find('tbody').children();
            $(planForm).each(function (i) {
                if (i > 1) {
                    let partial = $('input[name="partial-payment"]', this).val();
                    let after = $('input[name="partial-day"]', this).val();
                    if ( after == 0 ) {
                        after = 1;
                    }
                    let dateType = $('select[name="partial-date"]', this).val();
                    let fee = $('input[name="partial-fee"]', this).val();
                    fee_total += isNaN( parseInt( fee ) )  ? 0 : parseInt(fee) ;
                    plan['plan_schedule'].push({
                        partial,
                        after,
                        'date_type': dateType,
                        fee
                    });
                }
            })
            plan['fee_total'] = fee_total;
            plan['total'] = vicodin.schedule.calcPartialPayment(this);
            // Auto fill after input value if it is empty or less than 0
            let table = this;
            $('input[name="partial-day"]', this).each( function (){
                if ( $(this).val == '' || $(this).val() <= 0 ){
                    $(this).val(1);
                }
            });
            plan['duration'] = vicodin.schedule.calcDuration(this);
            data.push(plan);
        });
        return JSON.stringify( data );
    }

});