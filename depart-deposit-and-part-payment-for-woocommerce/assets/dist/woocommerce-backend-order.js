jQuery(document).ready(function ($) {
	'use strict'

	var depart_wc_order = {
		init: function () {
			$('.page-title-action:contains("prevent_create_suborder")').remove()
			this.watch_totals()
			this.handle_send_reinder_email()
			$('#woocommerce-order-items').on('click', 'button.recalculate-deposit-action', this.show_calculate_deposit_modal)
		},

		show_calculate_deposit_modal: function () {

			let btn = $(this)

			$('#woocommerce-order-items').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			})

			let data = {
				action: 'wdp_get_recalculate_deposit_modal',
				order_id: $(this).data('order_id'),
				nonce: vicodinParams.nonce,
			}

			$.ajax({
				url: vicodinParams.ajaxUrl,
				data: data,
				type: 'POST',

				success: function (response) {
					$('#woocommerce-order-items').unblock()
					if (response.success) {
						delete window.wp.template.cache[ 'wdp-modal-recalculate-deposit' ]
						$('#tmpl-wdp-modal-recalculate-deposit').html(response.data.html)
						let modal = btn.WCBackboneModal({
							template: 'wdp-modal-recalculate-deposit',
						})
						depart_wc_order.add_calculate_deposit_modal_event()
					} else {
						alert(response.data)
					}

				},
			})

			return false

		},

		add_calculate_deposit_modal_event: function (modal) {
			$('.wdp_deposits_payment_plan').select2({
				placeholder: wp.i18n.__('Select a plan', 'depart-deposit-and-part-payment-for-woocommerce'),
			})
			let toggle_deposit = function (enabled, row) {
				let disabled = !$(enabled).is(':checked')
				$('.wdp_deposits_deposit_type', row).prop('disabled', disabled)
				$('.wdp_deposits_payment_plan', row).prop('disabled', disabled)
			}

			let save_deposit_data = function () {

				let message_warning = wp.i18n.__('Save deposit data? This will remove all payments that have been paid and create a brand new instalment plan. (Uncheck all to remove deposit data).', 'depart-deposit-and-part-payment-for-woocommerce')
				if (confirm(message_warning) != true) {
					return
				}
				let formData = $('#wdp-modal-recalculate-form').serializeArray().reduce(function (obj, item) {
					obj[ `wdp_data[${item.name}]` ] = item.value
					return obj
				}, {})

				let data = {
					action: 'wdp_save_deposit_data',
					order_id: $('#post_ID').val(),
					nonce: vicodinParams.nonce, ...formData,
				}

				$.ajax({
					url: vicodinParams.ajaxUrl,
					data: data,
					type: 'POST',
					beforeSend: function () {
						// $('.modal-close').trigger('click')
						$('#woocommerce-order-items').block({
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6,
							},
						})
					},
					success: function (response) {
						$('#woocommerce-order-items').unblock()
						if (response.data) {
							alert(response.data)
						} else {
							location.reload()
						}
					},
				})
			}

			$('.wdp_calculator_modal_row').each((index, row) => {
				$('.wdp_enable_deposit', row).on('change', function (e) {
					toggle_deposit(e.target, row)
				})

				$('.wdp_deposits_deposit_type', row).on('change', function () {
					let select = $(this).val().toString()
					let plan_list = []
					if ('global' === select) {
						plan_list = $('#wdp-modal-recalculate-form').data('global-plans')
					} else if ('custom' === select) {
						plan_list = $(row).data('custom-plans')
					}

					$('.wdp_deposits_payment_plan', row).empty()
					$.each(plan_list, function (key, value) {
						$('.wdp_deposits_payment_plan', row).append($('<option>', {
							value: value.plan_id,
							text: value.plan_name,
						}))
					})
				})
			})

			$('.wdp_save_deposit_data').on('click', save_deposit_data)
		},

		watch_totals: function () {
			let order_info = $('#woocommerce-order-items .inside')
			let watcher = new MutationObserver(function () {
				let meta_box_wrapper = $('#depart_deposit_partial_payments .inside')
				depart_wc_order.get_installment_summary(meta_box_wrapper)
			})
			if ($(order_info).length > 0) {
				watcher.observe(order_info[ 0 ], { childList: true })
			}
		},

		get_installment_summary: function (meta_box_wrapper) {
			$.ajax({
				url: vicodinParams.ajaxUrl,
				type: 'post',
				dataType: 'json',
				data: {
					'nonce': vicodinParams.nonce,
					'action': 'depart_reload_payment_meta_box',
					'order_id': $('#post_ID').val(),
				},
				beforeSend: function () {
					$(meta_box_wrapper).addClass('blockUI blockOverlay')
				},
				success: function (response) {
					$(meta_box_wrapper).html(response.data.html)
				},
				error: function (xhr, status, err) {
					console.log(err)
				},
				complete: function () {
					$(meta_box_wrapper).removeClass('blockUI blockOverlay')
				},
			})
		},

		handle_send_reinder_email: function () {
			$('.depart-send-reminder-email-button').on('click', function (e) {
				let warning_mess = wp.i18n.__('Are you sure you want to send email?', 'depart-deposit-and-part-payment-for-woocommerce')
				if (!confirm(warning_mess)) {
					return
				}
				let $this = $(this)
				let order_id = $this.data('id')
				$this.closest('td').addClass('blockUI blockOverlay')
				$.ajax({
					url: vicodinParams.ajaxUrl,
					method: 'post',
					dataType: 'json',
					data: {
						'nonce': vicodinParams.nonce,
						'action': 'depart_send_reminder_email',
						'order_id': order_id,
					},
					success: function (response) {
						if (response.success) {
							$this.prev().removeClass('dashicons-no')
							$this.prev().addClass('dashicons-yes')
							depart_wc_order.notice(response.data)
						} else {
							depart_wc_order.notice(response.data, 'error')
						}
					},
					complete: function (data) {
						$this.closest('td').removeClass('blockUI blockOverlay')
					},
				})
			})
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

	depart_wc_order.init()
})