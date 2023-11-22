'use strict';

jQuery(document).ready(function ($) {

    let vicodin_rule = {
        startup: function () {
            if (location.hash === '') {
                location.hash = '#/';
            }
            this.handleAjax();
            this.watchLocation();
        },
        watchLocation: function () {
            window.onhashchange = vicodin_rule.handleAjax;
        },
        handleAjax: function () {
            let currentHash = location.hash;
            switch (true) {
                case '#/' === currentHash:
                    vicodin_rule.getRuleList();
                    break;
                case '#/new-rule' === currentHash:
                    vicodin_rule.getRule();
                    break;
                case /^#\/rule\/\d+$/.test(currentHash) || currentHash === '#/rule/default' :
                    let id = currentHash.split('/').pop();
                    vicodin_rule.getRule(id);
                    break;
            }
        },
        getRuleList: function () {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'get',
                dataType: 'html',
                data: {
                    '_ajax_nonce': vicodinParams.nonce,
                    'action': 'vicodin_get_rule_list',
                },
                beforeSend: vicodin_rule.loadAnimation,
                success: function (data) {
                    vicodin_rule.loadDocument(data);
                    vicodin_rule.addRuleListEvents();
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {

                }
            });
        },
        sortRuleList: function (list) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'text',
                data: {
                    '_ajax_nonce': vicodinParams.nonce,
                    'action': 'vicodin_sort_rule_list',
                    'data': list
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading');
                },
                success: function (data) {
                    $('.vi-ui.table').removeClass('form loading');
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {

                }
            });
        },
        addRuleListEvents: function () {
            $('#vicodin-rule-sortable').sortable({
                cursor: 'move',
                update: function (event, ui) {
                    let list = [];
                    $(this).children().each(function () {
                        let rule_id = $(this).data('rule_id');
                        list.push(rule_id);
                    });
                    vicodin_rule.sortRuleList(JSON.stringify(list));
                }
            });
            $('.button.vicodin-new-rule').on('click', function () {
                $(this).attr('href', '#/new-rule');
            });
            $('.vicodin-delete-rule').on('click', function () {
                let warningMess = wp.i18n.__('Would you want to remove this rule?', 'vico-deposit-and-installment');
                if (confirm(warningMess)) {
                    let id = $(this).data('id');
                    vicodin_rule.deleteRule(id);

                    $(this).closest('tr').remove();
                }
            });
            $('.vicodin-rule-enable').on('change', function () {
                let data = {
                    'rule-id': $(this).data('id'),
                    'rule-active': $(this).is(':checked')
                }
                vicodin_rule.updateRule(JSON.stringify(data));
            });
        },
        getRule: function (id = null) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'get',
                dataType: 'html',
                data: {
                    '_ajax_nonce': vicodinParams.nonce,
                    'action': 'vicodin_get_rule',
                    'rule-id': id
                },
                beforeSend: vicodin_rule.loadAnimation,
                success: function (data) {
                    vicodin_rule.loadDocument(data);
                    vicodin_rule.addRuleEvents();
                    if (id === 'default') {
                        $('.fields').hide();
                    }
                },
                error: function (err) {
                    console.log(err)
                },

            });
        },
        addRuleEvents: function () {

            $('select.dropdown').dropdown({
                'clearable': true,
            });

            $('.vicodin-taxonomy').on('change', function () {
                let select1 = $('select', this);
                let ext = $(select1).attr('name').slice(-3);
                let select2 = $('select[name$="' + ext + '"]', '.vicodin-taxonomy').not(select1);
                let group = (ext === 'inc') ? 'exc' : 'inc';
                if ($(select1).val().length > 0 || $(select2).val().length > 0) {
                    let field = $('.vicodin-taxonomy').has('select[name$="' + group + '"]').not(this);
                    $(field).closest('.field').addClass('disabled');
                } else {
                    let field = $('.vicodin-taxonomy').has('select[name$="' + group + '"]').not(this);
                    $(field).closest('.field').removeClass('disabled');
                }
            }).change();

            $('.vicodin-taxonomy-one').on('change', function () {
                let select = $('select', this);
                let name = $(select).attr('name').slice(0, -3);
                let ext = ($(select).attr('name').slice(-3) === 'inc') ? 'exc' : 'inc';
                if ($(select).val().length > 0) {
                    $('.field').has('#' + name + ext).addClass('disabled');
                } else {
                    $('.field').has('#' + name + ext).removeClass('disabled');
                }
            }).change();

            $('.button.increase-field').on('click', function () {
                let currRange = $(this.closest('tr'));
                $(currRange).find('.button.decrease-field').removeClass('hidden');

                let nextRange = $(currRange).clone(true);
                $(nextRange).find('input').val('');
                $(currRange).find('.button.increase-field').addClass('hidden');

                $('.vicodin-price-range tbody').append(nextRange);

            });

            $('.button.decrease-field').on('click', function () {
                $(this).closest('tr').remove();

                let table = $('.vicodin-price-range tbody');

                $(table).children(':last').find('.button.increase-field').removeClass('hidden');

                if (table.children().length < 2) {
                    $(table).children(':last').find('.button.decrease-field').addClass('hidden');
                }
            })

            $('#vicodin-save-rule').on('click', function () {
                if (vicodin_rule.validateForm()) {
                    vicodin_rule.saveRule();
                }
            });


        },
        saveRule: function () {
            let formData = vicodin_rule.getFormData();
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_save_rule',
                    'data': formData
                },
                beforeSend: function () {
                    $('#vicodin-save-rule').addClass('loading').unbind();
                },
                success: function (data) {
                    $('input[name="rule-id"]').val(data);
                    $('.vicodin-action-bar h2').text(wp.i18n.__('Edit rule', 'vico-deposit-and-installment'));
                },
                error: function (err) {
                    console.log(err)
                },
                complete: function () {
                    $('#vicodin-save-rule').removeClass('loading').on('click', vicodin_rule.saveRule);
                }
            });
        },
        updateRule: function (data) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_update_rule',
                    'data': data
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading');
                },
                success: function () {
                    $('.vi-ui.table').removeClass('form loading');
                },
                error: function (xhr, status, err) {
                    console.log(err)
                }
            });
        },
        deleteRule: function (id) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'html',
                data: {
                    '_ajax_nonce': vicodinParams.nonce,
                    'action': 'vicodin_delete_rule',
                    'rule-id': id
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading');
                },
                success: function (data) {
                    $('.vi-ui.table').removeClass('form loading');
                },
                error: function (err) {
                    console.log(err)
                }
            });
        },
        validateForm: function () {
            let check = true;
            let nameInput = $('input[name="rule-name"]');
            let nameValue = $(nameInput).val().toString().trim();

            if (nameValue === '') {
                $('.error-message.name').remove();
                let text = wp.i18n.__('Rule name required!', 'vico-deposit-and-installment');
                let errorMess = $('<div class="error-message name">' + text + '</div>');
                errorMess.insertAfter($(nameInput));
                check = false;
            } else {
                $('.error-message.name').remove();
                check = true;
            }
            return check;
        },
        getFormData: function () {
            let data = {
                'rule-id': $('input[name="rule-id"]').val(),
                'rule-name': $('input[name="rule-name"]').val(),
                'rule-active': $('input[name="rule-active"]').is(':checked'),
                'rule-categories-inc': $('select[name="rule-categories-inc"]').val() || [],
                'rule-categories-exc': $('select[name="rule-categories-exc"]').val() || [],
                'rule-tags-inc': $('select[name="rule-tags-inc"]').val() || [],
                'rule-tags-exc': $('select[name="rule-tags-exc"]').val() || [],
                'rule-users-inc': $('select[name="rule-users-inc"]').val() || [],
                'rule-users-exc': $('select[name="rule-users-exc"]').val() || [],
                'rule-products-inc': $('select[name="rule-products-inc"]').val() || [],
                'rule-products-exc': $('select[name="rule-product-exc"]').val() || [],
                'rule-price-range': {
                    'price-start': $('input[name="price-start"', '.vicodin-price-range').val(),
                    'price-end': $('input[name="price-end"]', '.vicodin-price-range').val(),
                },
                'payment-plans': $('select[name="payment-plans"]').val() || [],
                'rule-apply-for': '',
                'rule-plan-names': ''
            }

            let applyFor = [];
            $('select[name^="rule"]').each(function () {
                if ($(this).val().length !== 0) {
                    applyFor.push($(this).data('text'));
                }
            });

            let plansSelected = $('select[name="payment-plans"]').find('option:selected')
            let planNames = $.map(plansSelected, function (option) {
                return $(option).data('text');
            });

            data['rule-plan-names'] = planNames.join(', ');
            data['rule-apply-for'] = $.unique(applyFor).join(', ');
            return JSON.stringify(data);
        },
        loadAnimation: function () {
            $('.wrapper.vico-deposit').html('<div class="vi-ui active centered loader"></div>');
        },
        loadDocument: function (data) {
            $('.vico-deposit.wrapper').html(data);
        }
    }

    vicodin_rule.startup();
})