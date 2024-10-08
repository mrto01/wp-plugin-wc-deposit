jQuery(document).ready(function ($) {
    'use strict'

    let depart_rule = {
        startup: function () {
            if (location.hash === '') {
                location.hash = '#/'
            }
            this.handleAjax()
            this.watchLocation()
        },
        watchLocation: function () {
            window.onhashchange = depart_rule.handleAjax
        },
        handleAjax: function () {
            let currentHash = location.hash
            switch (true) {
                case '#/' === currentHash:
                    depart_rule.getRuleList()
                    break
                case '#/new-rule' === currentHash:
                    depart_rule.getRule()
                    break
                case /^#\/rule\/\d+$/.test(currentHash) || currentHash === '#/rule/default' :
                    let id = currentHash.split('/').pop()
                    depart_rule.getRule(id)
                    break
            }
        },
        getRuleList: function () {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'get',
                dataType: 'html',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_get_rule_list',
                },
                beforeSend: depart_rule.loadAnimation,
                success: function (data) {
                    depart_rule.loadDocument(data)
                    depart_rule.addRuleListEvents()
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {

                },
            })
        },
        sortRuleList: function (list) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'text',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_sort_rule_list',
                    'data': list,
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading')
                },
                success: function (data) {
                    $('.vi-ui.table').removeClass('form loading')
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {

                },
            })
        },
        addRuleListEvents: function () {
            $('#depart-rule-sortable').sortable({
                cursor: 'move',
                update: function (event, ui) {
                    let list = []
                    $(this).children().each(function (index) {
                        $(this).find('td:first-child').text(String(++index).padStart(2, '0'))
                        let rule_id = $(this).data('rule_id')
                        list.push(rule_id)
                    })
                    depart_rule.sortRuleList(JSON.stringify(list))
                },
            })

            $('.button.depart-new-rule').on('click', function () {
                $(this).attr('href', '#/new-rule')
            })

            $('.depart-delete-rule').on('click', function () {
                let warningMess = wp.i18n.__('Would you want to remove this rule?', 'depart-deposit-and-part-payment-for-woocommerce')
                let errorMess = wp.i18n.__('Warning: You are not allowed to delete the last rule. This will disrupt the deposit feature!', 'depart-deposit-and-part-payment-for-woocommerce')
                let count_plans = $('.vi-ui.vwcdp-table tbody').children().length;
                if ( count_plans <= 1) {
                    alert(errorMess)
                }else {
                    if (confirm(warningMess)) {
                        let id = $(this).data('id')
                        depart_rule.deleteRule(id, $(this).closest('tr'))
                    }
                }
            })
            $('.depart-rule-enable').on('change', function () {
                let data = {
                    'rule_id': $(this).data('id'),
                    'rule_active': $(this).is(':checked'),
                }
                depart_rule.updateRule(JSON.stringify(data))
            })
        },
        getRule: function (id = null) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'get',
                dataType: 'html',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_get_rule',
                    'rule_id': id,
                },
                beforeSend: depart_rule.loadAnimation,
                success: function (data) {
                    depart_rule.loadDocument(data)
                    depart_rule.addRuleEvents()
                    if (id === 'default') {
                        $('.fields').hide()
                    }
                },
                error: function (err) {
                    console.log(err)
                },

            })
        },
        isSearching: true,
        selectedOptions: [],
        searchProducts: function (product_name, select) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_search_products',
                    'product_name': product_name,
                },
                beforeSend: function () {
                    $(select).parent().addClass('loading')
                    depart_rule.isSearching = false
                },
                success: function (response) {
                    let products = response.data

                    $(select).children().each(function () {
                        if (!depart_rule.selectedOptions.includes($(this).val().toString())) {
                            $(this).remove()
                        }
                    })
                    for (let {
                        id,
                        name
                    } of products) {
                        if (!depart_rule.selectedOptions.includes(id.toString())) {
                            let option = $('<option>', {
                                value: id,
                                text: name,
                            })
                            $(select).append(option)
                        }
                    }
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
                complete: function () {
                    $(select).parent().removeClass('loading')
                    depart_rule.isSearching = true
                },
            })
        },
        addRuleEvents: function () {

            $('select.dropdown').dropdown({
                'clearable': true,
            })

            $('#rule_products_inc,#rule_products_exc').dropdown({
                onShow: function () {
                    let select = this
                    if (depart_rule.selectedOptions.length === 0) {
                        depart_rule.selectedOptions = $(select).val()
                    }
                    $(this).parent().find('input.search').on('input', function () {
                        let input = this
                        let key = $(input).val().trim()
                        setTimeout(function () {
                            if (key === $(input).val().trim() && key.length >= 3) {
                                depart_rule.searchProducts(key, select)
                            }
                        }, 1200)
                    })
                },
                onChange: function (value, text) {
                    depart_rule.selectedOptions = value
                },
            })

            $('input[name="price_start"],input[name="price_end"]', '.depart-price-range').on('change', function () {
                let value = Math.abs(parseFloat($(this).val()))
                if (isNaN(value)) {
                    $(this).val('')
                } else {
                    $(this).val(value)
                }
            })
            $('#depart-save-rule').on('click', function () {
                if (depart_rule.validateForm()) {
                    depart_rule.saveRule()
                }
            })

        },
        saveRule: function () {
            let formData = depart_rule.getFormData()
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_save_rule',
                    'data': formData,
                },
                beforeSend: function () {
                    $('#depart-save-rule').addClass('loading').unbind()
                },
                success: function (response) {
                    if (response.success) {
                        depart_rule.notice(response.data.message)
                        $('input[name="rule_id"]').val(response.data.rule_id)
                        let hash = '#/rule/' + response.data.rule_id
                        history.replaceState(null, null, hash)
                        $('.depart-action-bar h2').text(wp.i18n.__('Edit rule', 'depart-deposit-and-part-payment-for-woocommerce'))
                    } else {
                        depart_rule.notice(response.data.message, 'error')
                    }
                },
                error: function (err) {
                    console.log(err)
                },
                complete: function () {
                    $('#depart-save-rule').removeClass('loading').on('click',function () {
                        if (depart_rule.validateForm()) {
                            depart_rule.saveRule()
                        }
                    })
                },
            })
        },
        updateRule: function (data) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_update_rule',
                    'data': data,
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading')
                },
                success: function () {
                    $('.vi-ui.table').removeClass('form loading')
                },
                error: function (xhr, status, err) {
                    console.log(err)
                },
            })
        },
        deleteRule: function (id, row) {
            $.ajax({
                url: vicodinParams.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    'nonce': vicodinParams.nonce,
                    'action': 'depart_delete_rule',
                    'rule_id': id,
                },
                beforeSend: function () {
                    $('.vi-ui.table').addClass('form loading')
                },
                success: function (response) {
                    if (response.success) {
                        depart_rule.notice(response.data)
                        row.remove()
                    } else {
                        depart_rule.notice(response.data, 'error')
                    }
                },
                error: function (err) {
                    console.log(err)
                },
                complete: function () {
                    $('.vi-ui.table').removeClass('form loading')
                },
            })
        },
        validateForm: function () {
            let check = true
            let nameInput = $('input[name="rule_name"]')
            let nameValue = $(nameInput).val().toString().trim()
            let countPlan = $('#payment_plans').val().length;
            if ( nameValue === '' ) {
                $('.error-message.name').remove()
                let text = wp.i18n.__('Rule name required!', 'depart-deposit-and-part-payment-for-woocommerce')
                let errorMess = $('<div class="error-message name">' + text + '</div>')
                errorMess.insertAfter($(nameInput))
                check = false
            } else {
                $('.error-message.name').remove()
            }
            if ( countPlan === 0 ) {
                $('.error-message.payment-plan').remove()
                let text = wp.i18n.__('Payment plans required!', 'depart-deposit-and-part-payment-for-woocommerce')
                let errorMess = $('<div class="error-message payment-plan">' + text + '</div>')
                errorMess.insertAfter($('#payment_plans').parent())
                check = false
            } else {
                $('.error-message.payment-plan').remove()
            }
            return check
        },
        getFormData: function () {
            let data = {
                'rule_id': $('input[name="rule_id"]').val(),
                'rule_name': $('input[name="rule_name"]').val(),
                'rule_active': $('input[name="rule_active"]').is(':checked'),
                'rule_categories_inc': $('select[name="rule_categories_inc"]').val() || [],
                'rule_categories_exc': $('select[name="rule_categories_exc"]').val() || [],
                'rule_users_inc': $('select[name="rule_users_inc"]').val() || [],
                'rule_users_exc': $('select[name="rule_users_exc"]').val() || [],
                'rule_products_inc': $('select[name="rule_products_inc"]').val() || [],
                'rule_products_exc': $('select[name="rule_products_exc"]').val() || [],
                'rule_price_range': {
                    'price_start': $('input[name="price_start"', '.depart-price-range').val(),
                    'price_end': $('input[name="price_end"]', '.depart-price-range').val(),
                },
                'payment_plans': $('select[name="payment_plans"]').val() || [],
                'rule_apply_for': '',
                'rule_plan_names': '',
            }

            let applyFor = []
            $('select[name^="rule"]').each(function () {
                if ($(this).val().length !== 0) {
                    applyFor.push($(this).data('text'))
                }
            })

            let plansSelected = $('select[name="payment_plans"]').find('option:selected')

            if (applyFor.length === 0) {
                applyFor.push('All')
            }

            let planNames = $.map(plansSelected, function (option) {
                return $(option).data('text')
            })

            data[ 'rule_plan_names' ] = planNames.join(', ')
            data[ 'rule_apply_for' ] = $.unique(applyFor).join(', ')
            return JSON.stringify(data)
        },

        loadAnimation: function () {
            $('.wrapper.vico-deposit').html('<div class="vi-ui active centered loader"></div>')
        },

        loadDocument: function (data) {
            $('.vico-deposit.wrapper').html(data)
        },

        notice: function (message = '', status = '', time_out = 3) {
            let options = {
                message,
                status,
                time_out,
            }
            $(document.body).trigger('villatheme_show_message', options)
        },
    }

    depart_rule.startup()
})