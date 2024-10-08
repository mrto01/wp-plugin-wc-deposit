jQuery(document).ready(function ($) {
	'use strict'

	$('.depart-metaboxes').sortable()
	let depositType = 'percentage'
	var vicodin = {}

	vicodin.schedule = {
		calcPartialPayment: function (lastTable) {
			let total = 0
			let unitType = $(lastTable).find('#depart_unit_type').val()
			$('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).each(function () {
				let partial = parseFloat($(this).val())
				if (!isNaN(partial)) {
					total += partial
				}
			})
			$('.partial-total', lastTable).text(total)
			if (unitType == 'percentage' && total > 100 || unitType == 'percentage' && total < 100) {
				let error = $('<p>', {
					text: 'Total should be equal 100%',
					class: 'depart-error-message',
				})
				if ($(lastTable).find('.depart-error-message').length == 0) {
					$(lastTable).append(error)
				}

			} else {
				$(lastTable).find('.depart-error-message').remove()
			}
			return total
		},

		calcDuration: function (lastTable) {
			let duration = {
				'Year': 0,
				'Month': 0,
				'Day': 0,
			}
			$('input[name="partial-day"]', lastTable).each(function () {
				let numberOfDay = parseInt($(this).val())
				if (!isNaN(numberOfDay)) {
					let dateType = $(this).closest('tr').find('select[name="partial-date"]').val()
					if (dateType == 'day') {
						duration.Day += numberOfDay
					} else if (dateType == 'month') {
						duration.Month += numberOfDay
					} else if (dateType == 'year') {
						duration.Year += numberOfDay
					}
				}
			})

			let durationText = []
			for (let key in duration) {
				let dayValue = duration[ key ]
				if (dayValue > 0) {
					let suffix = dayValue > 1 ? 's' : ''
					durationText.push(dayValue + ' ' + key + suffix)
				}
			}

			durationText = durationText.join(', ')
			$('.partial-duration', lastTable).text(durationText)
			return durationText
		},
	}

	vicodin.validateNumber = function (input) {
		let value = Math.abs(parseFloat($(input).val()))
		if (isNaN(value)) {
			$(input).val('')
		} else {
			$(input).val(value)
		}
	}

	$('#product-type').on('change', function () {
		let type = $(this).val()
		let valid_type = ['simple','variable','booking']
		if ( !valid_type.includes(type) ) {
			$('.depart_deposit_tab').hide()
		} else {
			$('.depart_deposit_tab').show()
		}
	}).trigger('change')

	$('.depart-plan-schedule').each(function () {
		loadEvent(this)
	})

	$('#depart_deposit_plan').select2({
		placeholder: wp.i18n.__('Existing', 'depart-deposit-and-part-payment-for-woocommerce'),
	})

	$('#depart_deposit_type').on('change', function () {
		let type = $(this).val()
		if (type == 'global') {
			$('#depart_deposits_tab_data .wc-metaboxes-wrapper').addClass('hidden')
		} else {
			$('#depart_deposits_tab_data .wc-metaboxes-wrapper').removeClass('hidden')
		}
	})

	function loadEvent (lastTable = null) {
		// because have many plan tables, need to differ which one is.
		if (!lastTable) {
			lastTable = $('.depart-plan-schedule:last')
		}
		$(document.body).trigger('init_tooltips')

		$('input[name="plan_name"]', lastTable).on('input', function () {

			$(lastTable).closest('.wc-metabox').find('.depart-plan_name').text($(this).val())
		})

		$('.increase-field', lastTable).on('click', function (e) {
			let tbody = this.closest('tbody')
			let currRow = this.closest('tr')
			let nextRow = $(currRow).clone(true)
			$(nextRow).find('select').val($(currRow).find('select').val())
			$(tbody).append(nextRow)
			$(this).addClass('hidden')
			$('.decrease-field', currRow).removeClass('hidden')
			if (tbody.children.length > 3) {
				$(tbody).children(':last').find('.decrease-field').removeClass('hidden')
			}
			vicodin.schedule.calcPartialPayment(lastTable)
			vicodin.schedule.calcDuration(lastTable)
		})

		$('.decrease-field', lastTable).on('click', function (e) {
			let tbody = this.closest('tbody')
			let currRow = this.closest('tr')
			currRow.remove()
			if (tbody.children.length <= 3) {
				$(tbody).children(':last').find('.decrease-field').addClass('hidden')
				$(tbody).children(':last').find('.increase-field').removeClass('hidden')
			} else {
				$(tbody).children(':last').find('.increase-field').removeClass('hidden')
			}
			vicodin.schedule.calcPartialPayment(lastTable)
			vicodin.schedule.calcDuration(lastTable)
		})

		$('input[name="partial-payment"],input[name="deposit-amount"]', lastTable).on('change', function () {
			vicodin.schedule.calcPartialPayment(lastTable)
			vicodin.validateNumber(this)
			vicodin.schedule.calcDuration(lastTable)
		})

		$('input[name="partial-day"]', lastTable).on('change', function () {
			vicodin.validateNumber(this)
			vicodin.schedule.calcDuration(lastTable)
		})

		$('select[name="partial-date"]', lastTable).on('change', function () {
			vicodin.schedule.calcDuration(lastTable)
		})
		$('input[name="partial-fee"], input[name="deposit-fee"]', lastTable).on('change', function () {
			vicodin.validateNumber(this)
		})

		var woo_currency_symbol = $('.woo-currency-symbol', lastTable).text()

		$(lastTable).find('#depart_unit_type').on('change', function () {
			let type = $(this).val()
			depositType = type
			if (type == 'fixed') {
				$('.woo-currency-symbol', lastTable).text(woo_currency_symbol)
			} else {
				$('.woo-currency-symbol', lastTable).text('%')
			}
			vicodin.schedule.calcPartialPayment(lastTable)
		}).trigger('change')

		$('.depart-remove-plan.delete').on('click', function (e) {
			e.preventDefault()
			let message_warning = wp.i18n.__('Are you sure you want to delete this plan?', 'depart-deposit-and-part-payment-for-woocommerce')
			if (confirm(message_warning) != true) {
				return
			}
			$(this).closest('.wc-metabox').remove()
		})
	}

	$('.depart-new-custom-plan').on('click', getPlanTemplate)
	$('.depart-save-custom-plan').on('click', saveCustomPlans)

	function loadAnimation () {
		$('.wc-metaboxes-wrapper.depart-loader').addClass('blockUI blockOverlay')
	}

	function clearAnimation () {
		$('.wc-metaboxes-wrapper.depart-loader').removeClass('blockUI blockOverlay')
	}

	function getPlanTemplate () {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			dataType: 'html',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_get_new_plan_template',
			},
			beforeSend: function () {
				$('.depart-new-custom-plan').unbind()
				loadAnimation()
			},
			success: function (data) {
				$('#depart_deposits_tab_data .wc-metaboxes').append(data)
				loadEvent()
			},
			error: function (xhr, status, err) {
				console.log(err)
			},
			complete: function () {
				$('.depart-new-custom-plan').on('click', getPlanTemplate)
				clearAnimation()
			},
		})
	}

	function saveCustomPlans () {
		let data = getFormData()
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'post',
			dataType: 'json',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_save_custom_plans',
				'post_id': $('#post_ID').val(),
				'data': data,
				'exists_plans': JSON.stringify($('#depart_deposit_plan').val()),
				'depart_deposit_disabled': $('#depart_deposit_disabled').is(':checked') ? 'yes' : 'no',
				'depart_deposit_type': $('#depart_deposit_type').val(),
				'depart_force_deposit': $('#depart_force_deposit').is(':checked') ? 'yes' : 'no',
			},
			beforeSend: loadAnimation,
			success: function (response) {
				console.log(response.data)
			},
			error: function (err) {
				console.log(err)
			},
			complete: clearAnimation,
		})
	}

	function getFormData () {
		let data = []
		let fee_total = 0
		$('.depart-plan-schedule').each(function () {
			let plan = {
				'plan_name': $('input[name="plan_name"]', this).val(),
				'deposit': $('input[name="deposit-amount"]', this).val(),
				'deposit_fee': $('input[name="deposit-fee"]', this).val(),
				'unit-type': $('select[name="unit-type"]', this).val(),
				'plan_type': 'custom',
				'plan_schedule': [],
			}
			let planForm = $(this).find('tbody').children()
			$(planForm).each(function (i) {
				if (i > 1) {
					let partial = $('input[name="partial-payment"]', this).val()
					let after = parseInt($('input[name="partial-day"]', this).val())
					if (after == 0) {
						after = 1
					}
					let dateType = $('select[name="partial-date"]', this).val()
					let fee = $('input[name="partial-fee"]', this).val()
					fee_total += isNaN(parseInt(fee)) ? 0 : parseInt(fee)
					plan[ 'plan_schedule' ].push({
						partial,
						after,
						'date_type': dateType,
						fee,
					})
				}
			})
			plan[ 'fee_total' ] = fee_total
			plan[ 'total' ] = vicodin.schedule.calcPartialPayment(this)
			// Auto fill after input value if it is empty or less than 0
			let table = this
			$('input[name="partial-day"]', this).each(function () {
				if ($(this).val == '' || $(this).val() <= 0) {
					$(this).val(1)
				}
			})
			plan[ 'duration' ] = vicodin.schedule.calcDuration(this)
			data.push(plan)
		})
		return JSON.stringify(data)
	}

})
