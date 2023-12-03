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
                type: 'post',
                dataType: 'html',
                data: {
                    'nonce': vicodinParams.nonce,
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
                    'nonce': vicodinParams.nonce,
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
                    'rule_id': $(this).data('id'),
                    'rule_active': $(this).is(':checked')
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
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_get_rule',
                    'rule_id': id
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
        isSearching: true,
        selectedOptions: [],
        searchProducts: function ( product_name, select ) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_search_products',
                    'product_name': product_name
                },
                beforeSend: function() {
                    $(select).parent().addClass('loading');
                    vicodin_rule.isSearching = false;
                },
                success: function( response ) {
                    let products = response.data;

                    $(select).children().each( function () {
                        if ( !vicodin_rule.selectedOptions.includes( $(this).val().toString() ) ) {
                            $(this).remove();
                        }
                    } );
                    for ( let {id,name} of products ) {
                        if ( !vicodin_rule.selectedOptions.includes( id.toString() ) ) {
                            let option = $('<option>', {
                                value: id,
                                text: name,
                            })
                            $(select).append(option)
                        }
                    }
                },
                error: function(xhr, status, err) {
                    console.log(err)
                },
                complete: function() {
                    $(select).parent().removeClass('loading');
                    vicodin_rule.isSearching = true;
                }
            });
        },
        addRuleEvents: function () {

            $('select.dropdown').dropdown({
                'clearable': true,
            });
            
            $('#rule_products_inc,#rule_products_exc').dropdown({
                onShow: function() {
                    let select = this;
                    if (vicodin_rule.selectedOptions.length === 0 ){
                        vicodin_rule.selectedOptions = $(select).val();
                    }
                    $(this).parent().find('input.search').on('input', function () {
                        let input = this;
                        let key = $(input).val().trim();
                        setTimeout(function() {
                            if ( key === $(input).val().trim() && key.length >= 3 ) {
                                vicodin_rule.searchProducts( key,select )
                            }
                        },1200)
                    })
                },
                onChange: function (value, text) {
                    vicodin_rule.selectedOptions = value;
                },
            });

            // $('.vicodin-taxonomy').on('change', function () {
            //     let select1 = $('select', this);
            //     let ext = $(select1).attr('name').slice(-3);
            //     let select2 = $('select[name$="' + ext + '"]', '.vicodin-taxonomy').not(select1);
            //     let group = (ext === 'inc') ? 'exc' : 'inc';
            //     if ($(select1).val().length > 0 || $(select2).val().length > 0) {
            //         let field = $('.vicodin-taxonomy').has('select[name$="' + group + '"]').not(this);
            //         $(field).closest('.field').addClass('disabled');
            //     } else {
            //         let field = $('.vicodin-taxonomy').has('select[name$="' + group + '"]').not(this);
            //         $(field).closest('.field').removeClass('disabled');
            //     }
            // }).change();
            //
            // $('.vicodin-taxonomy-one').on('change', function () {
            //     let select = $('select', this);
            //     let name = $(select).attr('name').slice(0, -3);
            //     let ext = ($(select).attr('name').slice(-3) === 'inc') ? 'exc' : 'inc';
            //     if ($(select).val().length > 0) {
            //         $('.field').has('#' + name + ext).addClass('disabled');
            //     } else {
            //         $('.field').has('#' + name + ext).removeClass('disabled');
            //     }
            // }).change();

            // $('.button.increase-field').on('click', function () {
            //     let currRange = $(this.closest('tr'));
            //     $(currRange).find('.button.decrease-field').removeClass('hidden');
            //
            //     let nextRange = $(currRange).clone(true);
            //     $(nextRange).find('input').val('');
            //     $(currRange).find('.button.increase-field').addClass('hidden');
            //
            //     $('.vicodin-price-range tbody').append(nextRange);
            //
            // });

            // $('.button.decrease-field').on('click', function () {
            //     $(this).closest('tr').remove();
            //
            //     let table = $('.vicodin-price-range tbody');
            //
            //     $(table).children(':last').find('.button.increase-field').removeClass('hidden');
            //
            //     if (table.children().length < 2) {
            //         $(table).children(':last').find('.button.decrease-field').addClass('hidden');
            //     }
            // })
            $('input[name="price_start"],input[name="price_end"]','.vicodin-price-range').on('change', function() {
                let value = Math.abs( parseFloat( $(this).val() ) );
                if ( isNaN(value)) {
                    $(this).val('');
                }else {
                    $(this).val(value);
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
                success: function ( response ) {
                    $('input[name="rule_id"]').val( response.data );
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
                    'nonce': vicodinParams.nonce,
                    'action': 'vicodin_delete_rule',
                    'rule_id': id
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
            let nameInput = $('input[name="rule_name"]');
            let nameValue = $(nameInput).val().toString().trim();

            if (nameValue === '') {
                $('.error-message.name').remove();
                let text = wp.i18n.__('Rule name required!', 'vico-deposit-and-installment');
                let errorMess = $('<div class="error-message">' + text + '</div>');
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
                'rule_id': $('input[name="rule_id"]').val(),
                'rule_name': $('input[name="rule_name"]').val(),
                'rule_active': $('input[name="rule_active"]').is(':checked'),
                'rule_categories_inc': $('select[name="rule_categories_inc"]').val() || [],
                'rule_categories_exc': $('select[name="rule_categories_exc"]').val() || [],
                'rule_tags_inc': $('select[name="rule_tags_inc"]').val() || [],
                'rule_tags_exc': $('select[name="rule_tags_exc"]').val() || [],
                'rule_users_inc': $('select[name="rule_users_inc"]').val() || [],
                'rule_users_exc': $('select[name="rule_users_exc"]').val() || [],
                'rule_products_inc': $('select[name="rule_products_inc"]').val() || [],
                'rule_products_exc': $('select[name="rule_products_exc"]').val() || [],
                'rule_price_range': {
                    'price_start': $('input[name="price_start"', '.vicodin-price-range').val(),
                    'price_end': $('input[name="price_end"]', '.vicodin-price-range').val(),
                },
                'payment_plans': $('select[name="payment_plans"]').val() || [],
                'rule_apply_for': '',
                'rule_plan_names': ''
            }

            let applyFor = [];
            $('select[name^="rule"]').each(function () {
                if ($(this).val().length !== 0) {
                    applyFor.push($(this).data('text'));
                }
            });

            let plansSelected = $('select[name="payment_plans"]').find('option:selected')

            if ( applyFor.length === 0 ) {
                applyFor.push('All');
            }

            let planNames = $.map(plansSelected, function (option) {
                return $(option).data('text');
            });

            data['rule_plan_names'] = planNames.join(', ');
            data['rule_apply_for'] = $.unique(applyFor).join(', ');
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