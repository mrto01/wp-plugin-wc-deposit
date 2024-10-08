jQuery(document).ready(function ($) {
	'use strict'

	let currentPlanID
	let forceDeposit = $('.depart-deposit-wrapper').data('force_deposit')

	function loadEvent () {
		let modal = $('#depart-deposit-modal')
		let btn = $('.depart-deposit-options')
		var span = $('.close', modal)
		let blockDropdown = $('#depart-deposit-dropdown')
		let atcText = $('.single_add_to_cart_button').text()
		$('input[name="depart-client-auto-payment"]').
			on('change', function () {
				let selected = $(this)
				let method = 'remove'
				let order_id = selected.data('order')
				if (selected.prop('checked')) {
					method = 'add'
					// Uncheck other checkboxes with the same name
				}
				setOrderAutoPayment(selected, method, order_id)
			})

		$(btn).on('click', function () {
			$(btn).toggleClass('depart-active')
			if (modal.length > 0) {
				modal.fadeIn(450)
				$('.depart-modal-content').animate({
					opacity: 1,
					top: '18%',
				}, 300)
			} else if (blockDropdown.length > 0) {
				blockDropdown.slideToggle()
			}
		})

		span.on('click', function () {
			$('.depart-modal-content').animate({
				opacity: 0,
				top: 0,
			}, 200, function () {
				modal.fadeOut(200)
			})
			$(btn).removeClass('depart-active')

			if ($('input[name="depart-plan-select"]:checked').length <= 0) {
				$('#depart-deposit-check').prop('checked', false)
			}
		})

		$(window).on('click', function (e) {
			let target = e.target

			/* Close deposit modal */
			if ($(target).is('#depart-deposit-modal')) {
				span.trigger('click')
			}

			/* Change plan from cart item on cart and checkout page */
			if (target.closest('.wc-block-components-product-details__depart-plan')) {
				let plan_select_button = target.closest('.wc-block-components-product-details__depart-plan')
				// Check in cart page
				let current_cart_item = $(target).closest('td')

				let cart_item_key = ''

				/* If plan_select_button has cart item key*/
				if ($(plan_select_button).data('cart_item_key')) {
					cart_item_key = $(plan_select_button).data('cart_item_key')
				} else {
					if (current_cart_item.length > 0) {
						cart_item_key = $(current_cart_item).find('[class*="depart-cart-item-key"]:contains("depart-cart-item-key+")').text().split('+')
					} else {
						// Check in mini cart
						current_cart_item = $(target).closest('li')
						cart_item_key = $(current_cart_item).children().find(':contains("depart-cart-item-key+")').text().split('+')
						if (cart_item_key.length === 1) {
							current_cart_item = $(target).closest('ul')
							cart_item_key = $(current_cart_item).children().find(':contains("depart-cart-item-key+")').text().split('+')
						}
					}
					cart_item_key = cart_item_key[ 1 ]
				}
				getDepositBlockFromCartItem(target, cart_item_key)
			}

			/* Select plan when the plan clicked */
			if ($(target).is('.depart-select') && !$(target).is('.depart-active')) {
				$(target).addClass('depart-active').prop('disabled', true)
				$('.depart-select').
					not(target).
					removeClass('depart-active').
					prop('disabled', false)
				setTimeout(function () {
					span.trigger('click')
				}, 300)
			}

			/* Show detail plan when clicked */
			if (target.closest('.depart-plan-box')) {
				$('.depart-plan-box.depart-active').removeClass('depart-active')
				let currentPlanBox = target.closest('.depart-plan-box')
				$(currentPlanBox).addClass('depart-active')
			}
		})

		$('#depart-deposit-check').on('change', function () {
			let dpText = ''
			let planSelected = $('input[name="depart-plan-select"]:checked')
			if (this.checked) {
				dpText = vicodinParams.i18n.deposit

				if (planSelected.length <= 0) {
					btn.trigger('click')
				}

			} else {
				dpText = atcText
			}

			if (planSelected.length > 0) {
				$('.single_add_to_cart_button').text(dpText)
			}
		})

		// Handle when switching plan
		$('input[name="depart-plan-select"]').on('change', function () {
			if ($(this).is(':checked')) {
				let planName = $(this).data('plan_name')
				$('#depart-current-plan').text(planName)
				$('#depart-deposit-check').
					prop('checked', true).
					trigger('change')
			}
			if (forceDeposit === 'yes') {
				$('button.single_add_to_cart_button').removeClass('disabled').prop('disabled', false)
			}
		})

		// Disable add to cart button if force deposit is enable
		if (forceDeposit === 'yes') {
			$('button.single_add_to_cart_button').addClass('disabled').prop('disabled', true)
		}

	}

	let cancelCartItemPlanChange = function (e) {
		if ($(e.target).is('.close') || $(e.target).is('#depart-deposit-modal') || e.data.force) {
			let modal = e.data.modal
			$('.depart-modal-content').animate({
				opacity: 0,
				top: 0,
			}, 200, function () {
				modal.fadeOut(200)
			})
			setTimeout(function () {
				$(modal).remove()
			}, 1000)

		}
	}

	let changePlanFromCartItem = function (e) {
		if ($(e.target).is('.depart-select') && $(e.target).is('.depart-active')) {
			return
		}
		let cart_item_key = e.data.key.trim()
		let plan_box = e.target.closest('.depart-plan-box')
		let plan_id = $('input[name="depart-plan-select"]', plan_box).val()
		let deposit_type = $('input[name="depart-deposit-type"]').val()
		let data = {
			'nonce': vicodinParams.nonce,
			'action': 'depart_change_plan_from_cart_item',
			'cart_item_key': cart_item_key,
			'plan_id': plan_id,
			'deposit_type': deposit_type,
		}

		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'post',
			data,
			beforeSend: function () {
				$(e.target).closest('.depart-modal-content').addClass('depart-loading')
			},
			success: function (response) {
				e.data.force = true

				/* Update entire cart after change plan */
				$('.woocommerce-cart-form :input[name="update_cart"]').prop('disabled', false).trigger('click')

				if (typeof wc !== 'undefined' && wc.hasOwnProperty('blocksCheckout')) {
					wc.blocksCheckout.extensionCartUpdate({
						namespace: 'depart_update_entire_cart',
						data: {},
					})
				} else if ($('.vi-wcaio-sidebar-cart-bt-update').length > 0) {
					// Cart all in one
					$('.vi-wcaio-sidebar-cart-bt-update').trigger('click')

				} else if ($('form.checkout').length > 0) {
					// Classic checkout
					$('form.checkout').trigger('update')

				} else if ($('.woocommerce-mini-cart').length > 0) {
					// Mini cart
					$(document.body).trigger('added_to_cart')

				}
				cancelCartItemPlanChange(e)
			},
			error: function (xhr, status, error) {
				console.log(error)
			},
			complete: function () {
				$(e.target).closest('.depart-modal-content').removeClass('depart-loading')
				$(e.target).closest('.wp-block-woocommerce-checkout-order-summary-block').removeClass('depart-loading')
			},
		})
	}

	let getDepositBlockFromCartItem = function (target, cart_item_key) {
		let data = {
			'nonce': vicodinParams.nonce,
			'action': 'depart_get_deposit_block_from_cart_item',
			cart_item_key,
			'lang': vicodinParams.lang,
		}

		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			data,
			beforeSend: function () {
				$(target).closest('table').addClass('depart-loading')
				$(target).closest('.wp-block-woocommerce-checkout-order-summary-block').addClass('depart-loading')
				$(target).closest('.woocommerce-mini-cart').addClass('depart-loading')
				$(target).closest('.vi-wcaio-sidebar-cart-pd-wrap').addClass('depart-loading')
			},
			success: function (response) {
				let exist_modal = $('.depart-deposit-modal-cart-item')
				if (exist_modal.length > 0) {
					$(exist_modal).remove()
				}
				$(document.body).append(response.data)
				let new_modal = $('.depart-deposit-modal-cart-item')

				new_modal.fadeIn(450)
				$('.depart-modal-content').animate({
					opacity: 1,
					top: '18%',
				}, 300)

				$(new_modal).on('click', '.close', { modal: new_modal }, cancelCartItemPlanChange)
				$(new_modal).on('click', '.depart-select.depart-active', {
					modal: new_modal,
					force: true
				}, cancelCartItemPlanChange)
				$(new_modal).on('click', '.depart-select', {
					key: cart_item_key,
					modal: new_modal,
				}, changePlanFromCartItem)
				$(document.body).on('click', '.depart-deposit-modal-cart-item', { modal: new_modal }, cancelCartItemPlanChange)
			},
			error: function (xhr, status, error) {
				console.log(error)
			},
			complete: function () {
				$(target).closest('table').removeClass('depart-loading')
				$(target).closest('.wp-block-woocommerce-checkout-order-summary-block').removeClass('depart-loading')
				$(target).closest('.woocommerce-mini-cart').removeClass('depart-loading')
				$(target).closest('.vi-wcaio-sidebar-cart-pd-wrap').removeClass('depart-loading')
			},
		})

	}

	let getDepositBlock = function (data) {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_get_deposit_variation_block', ...data,
			},
			beforeSend: function () {
				$('.depart-plan-boxes').addClass('depart-loading')
				$('input[name="depart-plan-select"]').val('')
			},
			success: function (response) {
				let form = $('.depart-modal-content')
				let html = $('.depart-plan-boxes')
				if (html.length !== 0) {
					$(html).remove()
				}
				html = $(response.data)
				if (form.length !== 0) {
					$(form).append(html)
					$('input[name="depart-plan-select"]').on('change', function () {
						if ($(this).is(':checked')) {
							let planName = $(this).data('plan_name')
							currentPlanID = $(this).val()
							$('#depart-current-plan').text(planName)
							$('#depart-deposit-check').prop('checked', true).trigger('change')
						}

						if (forceDeposit === 'yes') {
							$('button.single_add_to_cart_button').removeClass('disabled').prop('disabled', false)
						}
					})
					$('#depart-plan-' + currentPlanID).prop('checked', true)
				}
			},
			error: function (xhr, status, error) {
				console.log(error)
			},
			complete: function () {
				$('.depart-plan-boxes').removeClass('depart-loading')
			},
		})
	}

	let setOrderAutoPayment = function (button, method, order_id) {
		$.ajax({
			url: vicodinParams.ajaxUrl,
			type: 'get',
			data: {
				'nonce': vicodinParams.nonce,
				'action': 'depart_set_order_auto_payment',
				'payment_token_id': button.val(),
				'method': method,
				'order_id': order_id,
			},
			beforeSend: function () {
				$('.depart-auto-payment-table').addClass('depart-loading')
			},
			success: function (response) {
				if (response.success) {
					$('input[name="' + button.attr('name') + '"]').
						not(button).
						prop('checked', false)
				} else {
					button.prop('checked', !button.prop('checked'))
				}
			},
			error: function (xhr, status, error) {
				button.prop('checked', false)
			},
			complete: function () {
				$('.depart-auto-payment-table').removeClass('depart-loading')
			},
		})
	}

	$('input[name="variation_id"]').on('change', function () {
		let product_id = parseInt($('input[name="product_id"]').val())
		let variation_id = parseInt($(this).val())
		let qty = $('form.cart input[name="quantity"]').val()
		if (isNaN(variation_id) || isNaN(product_id)) {
			return
		}
		getDepositBlock({
			product_id,
			variation_id,
			quantity: qty,
		})
	})

	/* Compatible with viredis */
	if (typeof (viredis_single) != 'undefined' && viredis_single.pd_dynamic_price) {
		let dynamic_price
		$(document).on('change', 'form.cart input[name="quantity"]', function () {

			if (dynamic_price) {
				clearTimeout(dynamic_price)
			}

			let form = jQuery(this).closest('form')

			let product_id, variation_id, qty = jQuery(this).val()

			if (form_quantity === qty) {
				return false
			}
			form_quantity = qty
			if (form.find('[name=variation_id]').length) {
				variation_id = parseInt(form.find('input[name=variation_id]').val())
			}
			if (form.find('[name=product_id]').length) {
				product_id = form.find('input[name=product_id]').val()

			}
			if (!product_id) {
				product_id = form.find('[name="add-to-cart"]').val()
			}

			product_id = parseInt(product_id)

			if (!product_id) {
				return false
			}

			if (!qty || qty === '0') {
				return false
			}

			if (typeof variation_id != 'undefined' && !variation_id) {
				return false
			}

			let data = {
				product_id: product_id,
				variation_id: !!variation_id ? variation_id : product_id,
				quantity: qty,
			}
			dynamic_price = setTimeout(function (data) {
				getDepositBlock(data)
			}, 500, data)
		})

		let form_quantity = 0
		$('form.cart input[name="quantity"]').trigger('change')
	}

	/* Compatible with vicaio */
	let cartItemsWrap = $('.vi-wcaio-sidebar-cart-products-wrap')
	if (cartItemsWrap.length) {
		let cart_item_observer = new MutationObserver(function (mutationList, observer) {
			let cart_items_meta = $('.wc-block-components-product-details__depart-plan')
			cart_items_meta.each(function () {
				$(this.previousSibling).remove()
			})
		})
		cart_item_observer.observe(cartItemsWrap[ 0 ], {
			childList: true,
			subtree: true,
		})
	}

	loadEvent()
})
