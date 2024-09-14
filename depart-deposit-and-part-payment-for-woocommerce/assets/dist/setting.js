jQuery(document).ready(function ($) {
	'use strict'

	$('.vi-ui.menu .item').tab({
		history: true,
		historyType: 'hash',
	})
	$('#depart-payment').dropdown()
	$('select.dropdown').dropdown()
	$('.vi-ui.message .close').on('click', function () {
		$(this).closest('.message').transition('fade')
	})
	$('.depart-save-setting').on('click', function () {
		$(this).addClass('loading')
	})
	$('.depart-email-status').on('click', function () {
		let button = $(this)
		let data = {
			nonce: vicodinParams.nonce,
			action: 'depart_change_email_status',
			email_class: button.data('email_class'),
		}

		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'POST',
			data,
			beforeSend: function () {
				button.addClass('loading')
			},
			success: function (response) {
				button.text(response.data.status)
				button.removeClass('loading')
			},

		})
	})

	$('.villatheme-get-key-button').one('click', function (e) {
		var v_button = jQuery(this)
		v_button.addClass('loading')
		var data = v_button.data()
		var item_id = data.id
		var app_url = data.href
		var main_domain = window.location.hostname
		main_domain = main_domain.toLowerCase()
		var popup_frame
		e.preventDefault()
		var download_url = v_button.attr('data-download')
		popup_frame = window.open(app_url, 'myWindow', 'width=380,height=600')
		window.addEventListener('message', function (event) {
			/*Callback when data send from child popup*/
			var obj = jQuery.parseJSON(event.data)
			var update_key = ''
			var message = obj.message
			var support_until = ''
			var check_key = ''
			if (obj[ 'data' ].length > 0) {
				for (var i = 0; i < obj[ 'data' ].length; i++) {
					if (obj[ 'data' ][ i ].id == item_id && (obj[ 'data' ][ i ].domain == main_domain || obj[ 'data' ][ i ].domain == '' || obj[ 'data' ][ i ].domain == null)) {
						if (update_key == '') {
							update_key = obj[ 'data' ][ i ].download_key
							support_until = obj[ 'data' ][ i ].support_until
						} else if (support_until < obj[ 'data' ][ i ].support_until) {
							update_key = obj[ 'data' ][ i ].download_key
							support_until = obj[ 'data' ][ i ].support_until
						}
						if (obj[ 'data' ][ i ].domain == main_domain) {
							update_key = obj[ 'data' ][ i ].download_key
							break
						}
					}
				}
				if (update_key) {
					check_key = 1
					jQuery('.villatheme-autoupdate-key-field').val(update_key)
				}
			}
			v_button.removeClass('loading')
			if (check_key) {
				jQuery('<p><strong>' + message + '</strong></p>').insertAfter('.villatheme-autoupdate-key-field')
				jQuery(v_button).closest('form').trigger('submit')
			} else {
				jQuery('<p><strong> Your key is not found. Please contact support@villatheme.com </strong></p>').insertAfter('.villatheme-autoupdate-key-field')
			}
		})
	})
})