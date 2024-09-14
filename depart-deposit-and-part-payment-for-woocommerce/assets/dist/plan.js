jQuery(document).ready(function ($) {
	'use strict'

	var vicodin = {}

	vicodin.startup = function () {
		if (location.hash === '') {
			location.hash = '#/'
		}
		vicodin.handleAjax()
		vicodin.observeContent()
		vicodin.watchLocation()
	}

	vicodin.watchLocation = function () {
		window.onhashchange = vicodin.handleAjax
	}

	vicodin.loadAnimation = function () {
		$('.wrapper.vico-deposit').html('<div class="vi-ui active centered loader"></div>')
	}

	vicodin.loadDocument = function (data) {
		$('.vico-deposit.wrapper').html(data)
	}

	vicodin.loadDropdown = function () {
		$('.vi-ui.dropdown').dropdown({
			onChange: function (value, text, $choice) {
				vicodin.schedule.calcDuration()
			},
		})
	}

	vicodin.validateNumber = function (input) {
		let value = Math.abs(parseInt($(input).val()))
		if (isNaN(value)) {
			$(input).val('')
		} else {
			$(input).val(value)
		}
	}

	vicodin.loadEvent = function () {

		$('#depart-new-plan').on('click', function (e) {
			e.preventDefault()
			window.location.hash = '#/plan-new'
		})
		$('#depart-home-plan').on('click', function (e) {
			e.preventDefault()
			window.location.hash = '#/'
		})
		$('.increase-field').on('click', function (e) {
			let tbody = this.closest('tbody')
			let currRow = this.closest('tr')
			let nextRow = vicodin.partialRowHtml.clone(true)
			$(nextRow).find('select').val($(currRow).find('select').val())
			$(nextRow).find('input[name="partial-payment"]').val(100 - vicodin.schedule.calcPartialPayment())
			$(tbody).append(nextRow)
			$(this).addClass('hidden')
			$('.decrease-field', currRow).removeClass('hidden')
			if (tbody.children.length > 2) {
				$(tbody).children(':last').find('.decrease-field').removeClass('hidden')
			}
			vicodin.schedule.calcPartialPayment()
			vicodin.loadDropdown()
			vicodin.schedule.calcDuration()
		})
		$('.decrease-field').on('click', function (e) {
			let tbody = this.closest('tbody')
			let currRow = this.closest('tr')
			currRow.remove()
			if (tbody.children.length <= 2) {
				$(tbody).children(':last').find('.decrease-field').addClass('hidden')
				$(tbody).children(':last').find('.increase-field').removeClass('hidden')
			} else {
				$(tbody).children(':last').find('.increase-field').removeClass('hidden')
			}
			vicodin.schedule.calcPartialPayment()
			vicodin.schedule.calcDuration()
		})
		$('input', '.partial-payment').on('change', function () {
			vicodin.validateNumber(this)
			vicodin.schedule.calcPartialPayment()
			vicodin.schedule.calcDuration()
		})
		$('input', '.partial-day').on('change', function () {
			vicodin.validateNumber(this)
			if ($(this).val == '' || $(this).val() <= 0) {
				$(this).val(1)
			}
			vicodin.schedule.calcDuration()
		})
		$('input', '.partial-fee').on('change', function () {
			vicodin.validateNumber(this)
		})
		$('select', '.partial-day').on('change', function () {
			vicodin.schedule.calcDuration()
		})
		$('.depart-delete-plan').on('click', vicodinDeletePlanHandler)
		$('#depart-save-plan').on('click', vicodinSavePlanHandler)
		$('.depart-plan-enable').on('change', function () {
			let data = {
				'plan_id': $(this).data('id'),
				'plan_active': $(this).is(':checked'),
			}
			vicodin.updatePlan(JSON.stringify(data))
		})
		vicodin.partialRowHtml = $('.depart-schedule tbody tr:last-child').clone(true)
		vicodin.loadDropdown()
	}
	vicodin.observeContent = function () {
		let vicodinMain = $('.wrapper.vico-deposit')
		const observer = new MutationObserver(vicodin.loadEvent)
		observer.observe(vicodinMain[ 0 ], { childList: true })
	}

	vicodin.updatePlan = function (data) {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'post',
			dataType: 'json',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_update_plan',
				'data': data,
			},
			beforeSend: function () {
				$('.vi-ui.table').addClass('form loading')
			},
			error: function (xhr, status, err) {
				console.log(err)
			},
			complete: function () {
				$('.vi-ui.table').removeClass('form loading')
			},
		})
	}

	function vicodinSavePlanHandler () {
		if (vicodin.validatePlan()) {
			vicodin.savePlan()
		}
	}

	function vicodinDeletePlanHandler () {
		let warningMess = wp.i18n.__('Would you want to remove this plan?', 'depart-deposit-and-part-payment-for-woocommerce');
		let errorMess = wp.i18n.__('Warning: You are not allowed to delete the last plan. This will disrupt the deposit feature!', 'depart-deposit-and-part-payment-for-woocommerce')
		let count_plans = $('.vi-ui.vwcdp-table tbody').children().length;
		if ( count_plans <= 1 ) {
			alert(errorMess)
		}else {
			if (confirm(warningMess)) {
				let id = $(this).data('id')
				vicodin.deletePlan(id, $(this).closest('tr') )
			}
		}
	}

	vicodin.schedule = {
		calcPartialPayment: function () {
			let total = 0
			$('input[name="partial-payment"]').each(function () {
				let partial = parseInt($(this).val())
				if (!isNaN(partial)) {
					total += partial
				}
			})
			$('#partial-total').text(total)
			return total
		},

		calcDuration: function () {
			let duration = {
				'Year': 0,
				'Month': 0,
				'Day': 0,
			}
			$('input[name="partial-day"]').each(function () {
				let numberOfDay = parseInt($(this).val())
				if (!isNaN(numberOfDay)) {
					let dateType = $(this).next().find('select').val()
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
			$('#partial-duration').text(durationText)
			return durationText
		},
	}

	vicodin.handleAjax = function () {
		let currentHash = location.hash
		currentHash = currentHash.slice(1)
		switch (true) {
			case '/' === currentHash:
				vicodin.getHomePage()
				break
			case '/plan-new' === currentHash:
				vicodin.getPlanPage()
				break
			case /^\/plan\/\d+$/.test(currentHash):
				let id = currentHash.match(/\d+/)[ 0 ]
				vicodin.getPlanPage(id)
				break
		}
	}

	vicodin.getHomePage = function () {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_get_plan_list',
			},
			dataType: 'html',
			beforeSend: vicodin.loadAnimation,
			success: vicodin.loadDocument,
		})
	}

	vicodin.getPlanPage = function (id = null) {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			dataType: 'html',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_get_plan',
				'plan_id': id,
			},
			beforeSend: vicodin.loadAnimation,
			success: vicodin.loadDocument,
			error: function (xhr, status, error) {
				$('.wrapper.vico-deposit').text(error.text())
			},
		})

	}

	vicodin.validatePlan = function () {
		let check = true
		let nameInput = $('input[name="plan_name"]')
		let nameValue = $(nameInput).val().toString().trim()
		let totalAmount = vicodin.schedule.calcPartialPayment()

		if (nameValue === '') {
			$('.error-message.name').remove()
			let text = wp.i18n.__('Plan name required!', 'depart-deposit-and-part-payment-for-woocommerce')
			let errorMess = $('<div class="error-message name">' + text + '</div>')
			errorMess.insertAfter($(nameInput))
			check = false
		} else {
			$('.error-message.name').remove()
		}
		if (totalAmount > 100 || totalAmount < 100) {
			$('.error-message.total').remove()
			let text = wp.i18n.__('Total should be equal 100%', 'depart-deposit-and-part-payment-for-woocommerce')
			let errorMess = $('<div class="error-message total">' + text + '</div>')
			errorMess.insertAfter($('.table.depart-schedule'))
			check = false
		} else {
			$('.error-message.total').remove()
		}
		$('input', '.partial-day').each(function () {
			if ($(this).val == '' || $(this).val() <= 0) {
				$(this).val(1)
			}
			vicodin.schedule.calcDuration()
		})
		return check
	}

	vicodin.getFormData = function () {
		// Handle if multiple language is enabled
		let plan_names = {}
		$('input[name*="plan_name"]').each(function () {
			let key = $(this).attr('name')
			let value = $(this).val()

			plan_names[ key ] = value
		})

		let data = {
			...plan_names,
			'plan_active': $('input[name="plan_active"]').is(':checked'),
			'plan_description': $('textarea[name="plan_description"]').val(),
			'plan_id': $('input[name="plan_id"]').val(),
		}
		let schedule = []
		let fee_total = 0
		let scheduleRows = $('.table.depart-schedule tbody').find('tr')
		scheduleRows.each(function (index) {
			let partial = $('input[name="partial-payment"]', this).val()
			let fee = $('input[name="partial-fee"]', this).val()
			if (partial > 0) {
				if (index !== 0) {
					let after = $('input[name="partial-day"]', this).val()
					let dateType = $('select[name="partial-date"]', this).val()
					fee_total += isNaN(parseInt(fee)) ? 0 : parseInt(fee)
					schedule.push({
						partial,
						after,
						'date_type': dateType,
						fee,
					})
				} else {
					data[ 'deposit' ] = partial
					data[ 'deposit_fee' ] = fee
					data[ 'total' ] = 100
				}
			} else {
				$(this).remove()
			}
		})
		data[ 'plan_schedule' ] = schedule
		data[ 'duration' ] = vicodin.schedule.calcDuration()
		data[ 'fee_total' ] = fee_total
		return JSON.stringify(data)
	}

	vicodin.savePlan = function () {

		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'post',
			dataType: 'json',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_save_plan',
				data: vicodin.getFormData(),
			},
			beforeSend: function () {

				$('#depart-save-plan').addClass('loading').unbind()
			},
			success: function (response) {
				if ( response.success ) {
					vicodin.notice( response.data.message )
					$('input[name="plan_id"]').val(response.data.plan_id)
					let hash = '#/plan/' + response.data.plan_id
					history.replaceState(null, null, hash)
					$('.depart-action-bar h2').text(wp.i18n.__('Edit plan', 'depart-deposit-and-part-payment-for-woocommerce'))
				}else {
					vicodin.notice( response.data.message, 'error' )
				}

			},
			error: function (error) {
				console.log(error)
			},
			complete: function () {
				$('#depart-save-plan').removeClass('loading').bind('click', vicodinSavePlanHandler)
			},
		})
	}

	vicodin.deletePlan = function (id, row) {
		let data = {
			'action': 'depart_delete_plan',
			'nonce': vicodinParams.nonce,
			'plan_id': id,
		}
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'post',
			dataType: 'json',
			data: data,
			beforeSend: function () {
				$('.vi-ui.table').addClass('form loading')
			},
			success: function (response) {
				if (response.success) {
					vicodin.notice(response.data)
					row.remove()
				} else {
					vicodin.notice(response.data, 'error')
				}
			},
			complete: function () {
				$('.vi-ui.table').removeClass('form loading')
			},
		})
	}

	vicodin.notice = function (message = '', status = '', time_out = 3) {
		let options = {
			message,
			status,
			time_out,
		}
		$(document.body).trigger('villatheme_show_message', options)
	}

	vicodin.startup()
})