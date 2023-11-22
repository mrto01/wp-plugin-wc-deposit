'use strict';

if (typeof vicodin === 'undefined') {
    var vicodin = {};
}

jQuery(document).ready(function ($) {
    vicodin.startup = function () {
        if (location.hash === '') {
            location.hash = '#/';
        }
        vicodin.handleAjax();
        vicodin.observeContent();
        vicodin.watchLocation();
    }

    vicodin.watchLocation = function () {
        window.onhashchange = vicodin.handleAjax;
    }

    vicodin.loadAnimation = function () {

        $('.wrapper.vico-deposit').html('<div class="vi-ui active centered loader"></div>');
    }

    vicodin.loadDocument = function (data) {
        $('.vico-deposit.wrapper').html(data);
    }

    vicodin.observeContent = function () {
        let vicodinMain = $('.wrapper.vico-deposit');
        const observer = new MutationObserver(function (mutationList, observer) {
            $('#vicodin-new-plan').on('click', function (e) {
                e.preventDefault();
                window.location.hash = '#/plan-new';
            });
            $('#vicodin-home-plan').on('click', function (e) {
                e.preventDefault();
                window.location.hash = '#/'
            });
            $('.increase-field').on('click', function (e) {
                let tbody = this.closest('tbody');
                let currRow = this.closest('tr');
                let nextRow = $(currRow).clone(true);
                $(nextRow).find('select').val($(currRow).find('select').val());
                $(tbody).append(nextRow);
                $(this).addClass('hidden');
                $('.decrease-field', currRow).removeClass('hidden');
                if (tbody.children.length > 2) {
                    $(tbody).children(':last').find('.decrease-field').removeClass('hidden');
                }
                vicodin.schedule.calPartialPayment();
                vicodin.schedule.calDuration();
            });
            $('.decrease-field').on('click', function (e) {
                let tbody = this.closest('tbody');
                let currRow = this.closest('tr');
                currRow.remove();
                if (tbody.children.length <= 2) {
                    $(tbody).children(':last').find('.decrease-field').addClass('hidden');
                    $(tbody).children(':last').find('.increase-field').removeClass('hidden');
                } else {
                    $(tbody).children(':last').find('.increase-field').removeClass('hidden');
                }
                vicodin.schedule.calPartialPayment();
                vicodin.schedule.calDuration();
            });
            $('input', '.partial-payment').on('input', function () {
                vicodin.schedule.calPartialPayment();
                vicodin.schedule.calDuration();
            });
            $('input', '.partial-day').on('input', function () {
                vicodin.schedule.calDuration();
            });
            $('select', '.partial-day').on('change', function () {
                vicodin.schedule.calDuration();
            })
            $('.vicodin-delete-plan').on('click', vicodinDeletePlanHandler);
            $('#vicodin-save-plan').on('click', vicodinSavePlanHandler);
            $('.vicodin-plan-enable').on('change', function () {
                let data = {
                    'plan-id': $(this).data('id'),
                    'plan-active': $(this).is(':checked')
                }
                vicodin.updatePlan(JSON.stringify(data));
            })

        });

        observer.observe(vicodinMain[0], {childList: true});
    }

    vicodin.updatePlan = function (data) {
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'post',
            dataType: 'json',
            data: {
                'nonce': vicodinParams.nonce,
                'action': 'vicodin_update_plan',
                'data': data
            },
            beforeSend: function () {
                $('.vi-ui.table').addClass('form loading');
            },
            error: function (xhr, status, err) {
                console.log(err)
            },
            complete: function () {
                $('.vi-ui.table').removeClass('form loading');
            }
        });
    }

    function vicodinSavePlanHandler() {
        if (vicodin.validatePlan()) {
            vicodin.savePlan();
        }
    }

    function vicodinDeletePlanHandler() {
        let warningMess = wp.i18n.__('Would you want to remove this plan?', 'vico-deposit-and-installment');
        if (confirm(warningMess)) {
            let id = $(this).data('id');
            vicodin.deletePlan(id);
            $(this).closest('tr').remove();
        }
    }

    vicodin.schedule = {
        calPartialPayment: function () {
            let total = 0;
            $('input[name="partial-payment"]').each(function () {
                let partial = parseInt($(this).val());
                if (!isNaN(partial)) {
                    total += partial;
                }
            })
            $('#partial-total').text(total);
            return total;
        },

        calDuration: function () {
            let duration = {
                'Years': 0,
                'Months': 0,
                'Days': 0
            }
            $('input[name="partial-day"]').each(function () {
                let numberOfDay = parseInt($(this).val());
                if (!isNaN(numberOfDay)) {
                    let dateType = $(this).next().val();
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
            $('#partial-duration').text(durationText);
            return durationText;
        }
    }

    vicodin.handleAjax = function () {
        let currentHash = location.hash;
        currentHash = currentHash.slice(1);
        switch (true) {
            case '/' === currentHash:
                vicodin.getHomePage();
                break;
            case '/plan-new' === currentHash:
                vicodin.getPlanPage();
                break;
            case /^\/plan\/\d+$/.test(currentHash):
                let id = currentHash.match(/\d+/)[0];
                vicodin.getPlanPage(id)
                break;
        }
    }

    vicodin.getHomePage = function () {
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'get',
            data: {
                '_ajax_nonce': vicodinParams.nonce,
                'action': 'vicodin_get_plan_list'
            },
            dataType: 'html',
            beforeSend: vicodin.loadAnimation,
            success: vicodin.loadDocument
        });
    }

    vicodin.getPlanPage = function (id = null) {
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'get',
            dataType: 'html',
            data: {
                '_ajax_nonce': vicodinParams.nonce,
                'action': 'vicodin_get_plan',
                'plan-id': id
            },
            beforeSend: vicodin.loadAnimation,
            success: vicodin.loadDocument,
            error: function (error) {
                $('.wrapper.vico-deposit').text(error.text());
            }
        })
    }

    vicodin.validatePlan = function () {
        let check = true;
        let nameInput = $('input[name="plan-name"]');
        let nameValue = $(nameInput).val().toString().trim();
        let totalAmount = vicodin.schedule.calPartialPayment();

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
        if (totalAmount > 100 || totalAmount < 100) {
            $('.error-message.total').remove();
            let text = wp.i18n.__('Total should be equal 100%', 'vico-deposit-and-installment');
            let errorMess = $('<div class="error-message total">' + text + '</div>')
            errorMess.insertAfter($('.table.vicodin-schedule'));
            check = false;
        } else {
            $('.error-message.total').remove();
            check = true;
        }
        return check;
    }

    vicodin.getFormData = function () {
        let data = {
            'plan-name': $('input[name="plan-name"]').val(),
            'plan-active': $('input[name="plan-active"]').is(':checked'),
            'plan-description': $('textarea[name="plan-description"]').val(),
            'plan-id': $('input[name="plan-id"]').val()
        }
        let schedule = [];
        let scheduleRows = $('.table.vicodin-schedule tbody').find('tr');
        scheduleRows.each(function (index) {
            if (index !== 0) {
                let partial = $('input[name="partial-payment"]', this).val();
                let after = $('input[name="partial-day"]', this).val();
                let dateType = $('select[name="partial-date"]', this).val();
                let fee = $('input[name="partial-fee"]', this).val();
                schedule.push({
                    partial,
                    after,
                    'date-type': dateType,
                    fee
                });
            } else {
                data['deposit'] = $('input[name="partial-payment"]', this).val();
                data['deposit-fee'] = $('input[name="partial-fee"]', this).val();
                data['total'] = 100;
            }
        });
        data['plan-schedule'] = schedule;
        data['duration'] = vicodin.schedule.calDuration();
        return JSON.stringify(data);
    }

    vicodin.savePlan = function () {
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'post',
            dataType: 'json',
            data: {
                'nonce': vicodinParams.nonce,
                'action': 'vicodin_save_plan',
                data: vicodin.getFormData()
            },
            beforeSend: function () {

                $('#vicodin-save-plan').addClass('loading').unbind();
            },
            success: function (data) {
                $('input[name="plan-id"]').val(data);
                $('.vicodin-action-bar h2').text(wp.i18n.__('Edit plan', 'vico-deposit-and-installment'));
            },
            error: function (error) {
                console.log(error);
            },
            complete: function () {
                $('#vicodin-save-plan').removeClass('loading').bind('click', vicodinSavePlanHandler);

            }
        })
    }

    vicodin.deletePlan = function (id) {
        let data = {
            'action': 'vicodin_delete_plan',
            '_ajax_nonce': vicodinParams.nonce,
            'plan-id': id
        }
        $.ajax({
            url: vicodinParams.ajaxUrl,
            type: 'post',
            dataType: 'html',
            data: data,
            beforeSend: function () {
                $('.vi-ui.table').addClass('form loading');
            },
            success: function () {
                $('.vi-ui.table').removeClass('form loading');
            }
        })
    }


    vicodin.startup();
})