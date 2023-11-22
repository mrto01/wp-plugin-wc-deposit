<?php

namespace VicoDIn\Inc;

use Cassandra\Date;

defined( 'ABSPATH' ) || exit;

class Deposit_Front_End {
	static $instance = null;

	public $slug;

	public $dist_url;

    public $deposit_type;

	public function __construct() {
		$this->slug = VICODIN_CONST['slug'];
		$this->dist_url = VICODIN_CONST['dist_url'];
        $this->deposit_type = VICODIN_CONST['order_type'];

		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
		if ( vicodin_check_wc_active() ) {

			add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'vicodin_get_deposit_block'), 1111 );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'vicodin_add_cart_item_data' ), 10, 3 );
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'vicodin_before_calculate_totals' ) );
			add_filter( 'woocommerce_get_item_data', array( $this, 'vicodin_get_item_data' ), 10, 2 );

			add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'vicodin_cart_totals_after_order_total' ) );
			add_filter( 'woocommerce_cart_needs_payment', array( $this, 'vicodin_cart_needs_payment' ), 10, 2 );
			add_filter( 'woocommerce_calculated_total', array( $this, 'vicodin_calculated_total' ), 10, 2 );

            add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'vicodin_checkout_create_order_line_item' ), 10, 4 );
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'vicodin_checkout_update_order_meta' ), 10, 2 );
			add_action( 'woocommerce_review_order_after_order_total', array( $this, 'vicodin_cart_totals_after_order_total' ) );
            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'vicodin_available_payment_gateways') );
            add_filter( 'woocommerce_get_checkout_payment_url', array( $this, 'vicodin_get_checkout_payment_url' ), 10, 2);


//            Add new order type
            add_action( 'woocommerce_create_order', array( $this, 'vicodin_create_order' ), 99, 2 );
            add_filter( 'woocommerce_order_class', array( $this, 'vicodin_order_class' ), 10, 3 );
            add_action( 'woocommerce_order_details_after_order_table', array( $this, 'vicodin_order_details_after_order_table' ) );



            add_action( 'woocommerce_my_account_my_orders_actions', array( $this, 'vicodin_my_account_my_orders_actions'), 10, 2 );

            add_action( 'woocommerce_payment_complete', array( $this, 'vicodin_payment_complete' ) );
            add_action( 'woocommerce_thankyou', array ( $this, 'vicodin_rewrite_order_tails_table' ), 9 );
            add_filter( 'woocommerce_get_order_item_totals', array( $this, 'vicodin_get_order_item_totals' ),10, 2 );

//          Change Order's status when suborders' status change.
            add_action( 'woocommerce_order_status_changed', array( $this, 'vicodin_order_status_changed'), 10, 4);
//            add_action( 'before_woocommerce_pay', array( $this, 'vicodin_redirect_to_checkout_link' ) );
//            add_filter( 'woocommerce_order_needs_payment', array( $this, 'vicodin_order_needs_payment' ), 10, 3 );

            add_action( 'vicodin_email_payment_schedule', array( $this, 'vicodin_display_payment_schedule' ) );

		}
	}


	public static function instance() {
		if ( null == self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    public function vicodin_order_class( $classname, $order_type, $order_id ) {
        if ( $order_type == $this->deposit_type){
            return 'VicoDIn\Inc\Partial_Order';
        }
        return $classname;
    }

	public function check_rule_match( $product, $rule ){

		$user = wp_get_current_user()->roles[0];


		if ( !empty( $rule['rule-products-inc'] ) && in_array( $product->get_id(), $rule['rule-products-inc'] ) ) {
			return true;
		}

		if ( !empty( $rule['rule-products-exc'] ) && in_array( $product->get_id(), $rule['rule-products-exc'] ) ) {
			return false;
		}
		// Price Range Check
		$product_price = $product->get_price();
		if ( $rule['rule-price-range']['price-start'] ) {
			$price_start = $rule['rule-price-range']['price-start'];
			$price_end = $rule['rule-price-range']['price-end'] === 0 ? INF : $rule['rule-price-range']['price-end'];
			if ( $product_price < $price_start || $product_price > $price_end ) {
				return false; // Exit early if price is outside the range
			}
		}

		if( !empty( $rule['rule-categories-inc'] ) && !array_intersect($product->get_category_ids(), $rule['rule-categories-inc'] ) ){
			return false;
		}

		if( !empty( $rule['rule-categories-exc'] ) && array_intersect($product->get_category_ids(), $rule['rule-categories-exc'] ) ){
			return false;
		}
		if( !empty( $rule['rule-tags-inc'] ) && !array_intersect($product->get_tag_ids(), $rule['rule-tags-inc'] ) ){
			return false;
		}
		if( !empty( $rule['rule-tags-exc'] ) && array_intersect($product->get_tag_ids(), $rule['rule-tags-exc'] ) ){
			return false;
		}
		if ( !empty( $rule['rule-users-inc'] ) && !in_array( $user, $rule['rule-users-inc'] ) ) {
			return false;
		}
		if ( !empty( $rule['rule-users-exc'] ) && in_array( $user, $rule['rule-users-exc'] ) ) {
			return false;
		}
		return true;
	}

	public function vicodin_get_deposit_block() {
		global $product;

        if ( !isset( $product ) ) {
            return;
        }

		$deposit_settings = get_option( 'vicodin_deposit_setting' );
		$product_disabled = $product->get_meta( 'vicodin_deposit_disabled' );
		if( !is_user_logged_in() ){
			return;
		}
		if( !$deposit_settings['enabled'] ) {
			return;
		}
		if ( $product_disabled == 'yes') {
			return;
		}

		$deposit_type = $product->get_meta( 'vicodin_deposit_type' );
		if ( empty($deposit_type) ) $deposit_type = 'global';

		$plans = array();
		if( $deposit_type == 'custom') {
			$plans = $product->get_meta( 'vicodin_custom_plans' );
            $price = $product->get_price();
            foreach ( $plans as $key => $plan ) {
                $unit_type = $plan['unit-type'];
                $total = $plan['total'];

                if ( $unit_type == 'fixed' && $total != $price ) {
                    unset( $plans[ $key ] );
                }elseif ( $unit_type == 'percentage' && $total != 100 ) {
	                unset( $plans[ $key ] );
                }
            }
		}else {
			$rules = get_option( 'vicodin_deposit_rule', array() );
			foreach ( $rules as $rule ) {
				if ( !$rule['rule-active'] ) {
					continue;
				}
				if ( $this->check_rule_match( $product, $rule ) ){
					$exists_plans = get_option( 'vicodin_payment_plan' ); // Get all plans in option table
					$plans = array_filter( $exists_plans, function( $plan ) use ($rule){
						if ( $plan['plan-active'] ){
							return in_array( $plan['plan-id'], $rule['payment-plans'] );
						}
						return '';
					} );
					break;
				}
			}
		}

		if( empty( $plans )) {
			return;
		}

		include __DIR__ . '/views/deposit-block.php';
	}

	public function frontend_enqueue_styles() {
		wp_enqueue_style( 'woocommerce-front-end-' . $this->slug, $this->dist_url . 'woocommerce-front-end.css' );
	}
	public function frontend_enqueue_scripts() {
		wp_enqueue_script( 'woocommerce-front-end-' . $this->slug, $this->dist_url . 'woocommerce-front-end.js', ['jquery']);
	}


	public function vicodin_add_cart_item_data( $cart_item_data, $product_id, $variation_id) {

		if( !isset( $_POST['vicodin-deposit-check'] ) ){
			return $cart_item_data;
		}

		$product = wc_get_product( $product_id );
		$db_enabled = $this->check_deposit_enabled( $product );

		if( !$db_enabled ) {
			return $cart_item_data;
		}

		if( $product->is_type('variable') ) {
			$product = wc_get_product( $variation_id );
		}

		$cart_item_data['vicodin_deposit'] = array(
			'enable'       => true,
			'plan-id'      => $_POST['vicodin-plan-select'],
			'deposit-type' => $_POST['vicodin-deposit-type'],
		);

		return $cart_item_data;
	}

	public function vicodin_get_item_data( $item_data, $cart_item  ) {
		$enabled = $this->check_deposit_enabled( $cart_item['data'] );
		if( $enabled ){

			if ( isset( $cart_item['vicodin_deposit'], $cart_item['vicodin_deposit']['deposit'] ) && $cart_item['vicodin_deposit']['enable'] ) {
				$product = $cart_item['data'];
				if ( !$product ) return $item_data;

				$deposit = $cart_item['vicodin_deposit']['deposit'];
				$future_payment = $cart_item['vicodin_deposit']['future_payment'];
				$fee = $cart_item['vicodin_deposit']['fee_total'];

				$item_data[] = array (
					'name'      => __( 'deposit amount', 'vico-deposit-and-installment' ),
					'display'   => wc_price( $deposit, array( 'decimals' => wc_get_price_decimals() ) )
				);

				$item_data[] = array(
					'name'      => __( 'future payments', 'vico-deposit-and-installment' ),
					'display'   => wc_price( $future_payment, array( 'decimals' => wc_get_price_decimals() ) )
				);

				$item_data[] = array (
					'name'      => __( 'interest', 'vico-deposit-and-installment' ),
					'display'   => wc_price( $fee, array( 'decimals' => wc_get_price_decimals() ) ),
				);

			}

		}
		return $item_data;
	}

	public function check_deposit_enabled( $product ) {

		if ( ! $product || $product->is_type( array( 'external', 'bundle', 'composite' ) ) ) {
			return false;
		}

		$vicodin_st = get_option('vicodin_deposit_setting');

		if( isset( $vicodin_st['enabled'] ) && $vicodin_st['enabled'] ){
			$disabled = $product->get_meta('vicodin_deposit_disabled');

            if ( empty( $disabled ) ) $disabled = 'no';

			if ( $disabled == 'yes' ) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	public function vicodin_before_calculate_totals() {
		if (is_admin() && !defined('DOING_AJAX')) {
			return; // Prevent running in admin and not during AJAX requests
		}
		if (WC()->cart) {
			foreach (WC()->cart->get_cart_contents() as $cart_item_key => $cart_item) {
				$this->vicodin_update_deposit_meta( $cart_item['data'], $cart_item['quantity'], $cart_item, $cart_item_key );
			}
		}
	}

	public function vicodin_update_deposit_meta( $product, $quantity, $cart_item_data, $cart_item_key ) {
		if ( $product ) {
			if( isset( $cart_item_data['bundled_by'] ) ) $cart_item_data['vicodin_deposit']['enable']  = 'no';
			$deposit_enabled = $this->check_deposit_enabled($product);

			if ( $deposit_enabled ) {
				$deposit_type = $cart_item_data['vicodin_deposit']['deposit-type'] ?? null;

				if( $deposit_type ) {

					$payment_type = 'percentage';
					$plan = null;
					$plan_id = $cart_item_data['vicodin_deposit']['plan-id'];

					switch( $deposit_type ) {
						case 'global':
							$plan = get_option('vicodin_payment_plan')[ $plan_id] ?? null;
							break;
						case 'custom':
							$plan = $product->get_meta('vicodin_custom_plans')[ $plan_id ] ?? null;
							$payment_type = $plan['unit-type'] ?? null;
							break;
					}

					if( $plan ) {

						$deposit = 0;
						$fee = 0;
                        $deposit_fee = 0;
                        $item_sub_total = $cart_item_data['data']->get_price() * $quantity;

						$tax_total = 0;

						switch( $payment_type ) {
							case 'percentage':
								$deposit = vicodin_get_due_amount( $plan['deposit'], $item_sub_total );
								$deposit_fee = $fee = vicodin_get_due_amount( $plan['deposit-fee'], $item_sub_total );
								foreach ( $plan['plan-schedule'] as $partial ){
									$partial_amount = vicodin_get_due_amount( $partial['partial'], $item_sub_total );
									$fee += vicodin_get_due_amount( $partial['fee'], $partial_amount);
								}
								$fee *= $quantity;
                                $deposit_fee *= $quantity;
								break;
							case 'fixed':
								$deposit = $plan['deposit'] * $quantity;
								$deposit_fee = $fee = floatval($plan['deposit-fee']);
								foreach ( $plan['plan-schedule'] as $partial ){
									$fee += floatval($partial['fee']);
								}
								$fee *= $quantity;
                                $deposit_fee *= $quantity;
								break;
						}

						if ( isset($cart_item_data['line_subtotal_tax'] ) ) {
							$tax_total = $cart_item_data['line_subtotal_tax'];
						}

//						Calculate tax later...

						if( $deposit < $item_sub_total ){
							$cart_item_data['vicodin_deposit']['enable'] = 1 ;
							$cart_item_data['vicodin_deposit']['deposit'] = $deposit;
							$cart_item_data['vicodin_deposit']['future_payment'] = $item_sub_total- $deposit;
							$cart_item_data['vicodin_deposit']['tax_total'] = $tax_total;
							$cart_item_data['vicodin_deposit']['total'] = $item_sub_total;
							$cart_item_data['vicodin_deposit']['fee_total'] = $fee;
							$cart_item_data['vicodin_deposit']['deposit_fee'] = $deposit_fee;
                            $cart_item_data['vicodin_deposit']['plan'] = $plan;
                            $cart_item_data['vicodin_deposit']['unit-type'] = $payment_type;
						}else{
							$cart_item_data['vicodin_deposit']['enable'] = 0 ;
						}

						WC()->cart->cart_contents[$cart_item_key]['vicodin_deposit'] =  $cart_item_data['vicodin_deposit'];
					}
				}
			}

		}
	}

	public function vicodin_cart_totals_after_order_total() {
        if ( WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) {
		?>
		<tr class="order-due">
			<th><?php esc_html_e('Deposit', 'vico-deposit-and-installment' ); ?></th>
			<td data-title="<?php esc_attr_e( 'order-due', 'vico-deposit-and-installment' ) ?>">
				<strong><?php echo wc_price( WC()->cart->vwcdi_deposit_info['deposit_amount'], array( 'decimals' => wc_get_price_decimals() ) ) ?></strong>
			</td>
		</tr>
        <tr class="order-rest">
            <th><?php esc_html_e('Future payments', 'vico-deposit-and-installment' ); ?></th>
            <td data-title="<?php esc_attr_e( 'order-rest', 'vico-deposit-and-installment' ) ?>">
            <strong><?php echo wc_price( WC()->cart->get_total('edit') - WC()->cart->vwcdi_deposit_info['deposit_amount'], array( 'decimals' => wc_get_price_decimals() ) ) ?></strong>
            </td>
        </tr>
        <tr class="order-interest">
            <th><?php esc_html_e('Total interest', 'vico-deposit-and-installment' ); ?></th>
            <td data-title="<?php esc_attr_e( 'order-interest', 'vico-deposit-and-installment' ) ?>">
            <strong><?php echo wc_price( WC()->cart->vwcdi_deposit_info['fee_total'], array( 'decimals' => wc_get_price_decimals() ) ) ?></strong>
            </td>
        </tr>
		<?php
        }
	}

	public function vicodin_cart_needs_payment ( $needs_payment, $cart ){
		$deposit_enabled = isset(WC()->cart->vwcdi_deposit_info['deposit_enabled'], WC()->cart->vwcdi_deposit_info['deposit_amount'])
		                   && WC()->cart->vwcdi_deposit_info['deposit_enabled'] === true && WC()->cart->vwcdi_deposit_info['deposit_amount'] <= 0;

		if ($deposit_enabled) {
			$needs_payment = false;
		}
		return $needs_payment;
	}

	public function vicodin_calculated_total ( $total, $cart ) {
		$items_total = $cart->get_subtotal();
		$deposit_amount = 0;
		$fee_total = 0;
        $deposit_fee = 0;

		$is_deposit_cart = false;

		$deposit_enabled = false;

		foreach( $cart->get_cart_contents() as $cart_item ){
			$enabled = $this->check_deposit_enabled( $cart_item['data'] );

            if ( $enabled && isset( $cart_item['vicodin_deposit'] , $cart_item['vicodin_deposit']['deposit']) && $cart_item['vicodin_deposit']['enable'] ) {
                $is_deposit_cart = true;
                $deposit_amount += $cart_item['vicodin_deposit']['deposit'];
                $fee_total += $cart_item['vicodin_deposit']['fee_total'];
                $deposit_fee += $cart_item['vicodin_deposit']['deposit_fee'];
            }else {
                $deposit_amount += $cart_item['line_subtotal'];
            }
		}

		if ( $is_deposit_cart && $deposit_amount < $items_total ) {
			$deposit_enabled = true;
		}

		$vicodin_st = get_option( 'vicodin_deposit_setting' );

		$coupon_handling            = $vicodin_st['coupon'] ?? 'deposit';
		$fees_handling              = $vicodin_st['fee'] ?? 'deposit';
		$taxes_handling             = $vicodin_st['tax'] ?? 'deposit';
		$shipping_handling          = $vicodin_st['shipping'] ?? 'deposit';
		$shipping_taxes_handling    = $vicodin_st['shipping_tax'] ?? 'deposit';

		$deposit_discount = 0.0;
		$deposit_fees = 0.0;
		$deposit_taxes = 0.0;
		$deposit_shipping = 0.0;
		$deposit_shipping_taxes = 0.0;


		$division = $items_total == 0 ? 1 : $items_total;
		$deposit_percentage = $deposit_amount * 100 / floatval($division);


		// remaining amounts for build schedule later
		$remaining_amounts = array();

		// coupon handling
		$discount_total = $cart->get_cart_discount_total();
		switch ( $coupon_handling ) {
			case 'deposit':
				$deposit_discount = $discount_total;
				break;
			case 'split':
				$deposit_discount = $deposit_percentage * $discount_total / 100;
				break;
		}
		$remaining_amounts['discount'] = $discount_total - $deposit_discount;

		// taxes handling

		switch ( $taxes_handling ) {
			case 'deposit':
				$deposit_taxes = $cart->tax_total;
				break;
			case 'split':
				$deposit_taxes = $deposit_percentage * $cart->tax_total / 100;
				break;
		}
		$remaining_amounts['tax'] =  $cart->tax_total - $deposit_taxes;

		// fees handling

		$fee_taxes = $cart->get_fee_tax();
		switch ($fees_handling) {
			case 'deposit' :
				$deposit_fees = floatval($cart->fee_total + $fee_taxes);
				break;

			case 'split' :
				$deposit_fees = floatval($cart->fee_total + $fee_taxes) * $deposit_percentage / 100;
				break;
		}
		$remaining_amounts['fee'] = $cart->get_fee_total() + $fee_taxes - $deposit_fees;

		// Shipping handling

		switch ($shipping_handling) {
			case 'deposit' :
				$deposit_shipping = $cart->shipping_total;
				break;

			case 'split' :
				$deposit_shipping = $cart->shipping_total * $deposit_percentage / 100;
				break;
		}
		$remaining_amounts['shipping'] = $cart->shipping_total - $deposit_shipping;

		// Shipping taxes handling.

		switch ($shipping_taxes_handling) {
			case 'deposit' :
				$deposit_shipping_taxes = $cart->shipping_tax_total;
				break;

			case 'split' :
				$deposit_shipping_taxes = $cart->shipping_tax_total * $deposit_percentage / 100;
				break;
		}
		$remaining_amounts['shipping_tax'] = $cart->shipping_tax_total - $deposit_shipping_taxes;

		$deposit_amount += $deposit_fees + $deposit_taxes + $deposit_shipping + $deposit_shipping_taxes - $deposit_discount;

		if ( $deposit_amount <= 0 || ( $total + $discount_total - $deposit_amount - $remaining_amounts['discount'] ) <= 0 ) {
			$deposit_enabled = false;
		}

		WC()->cart->vwcdi_deposit_info = array(
			'deposit_enabled'   => $deposit_enabled,
			'deposit_amount'    => $deposit_amount,
            'fee_total'         => $fee_total,
		);

		if( $deposit_enabled ) {
			$payment_schedule = $this->build_payment_schedule( $remaining_amounts, $deposit_amount, $total, $deposit_fee );
			WC()->cart->vwcdi_deposit_info['payment_schedule'] = $payment_schedule;
		}

		return $total;
	}

	public function build_payment_schedule( $remaining_amounts, $deposit, $total, $deposit_fee ){
		$current_date = new \DateTime();
		$current_date_string = $current_date->format('F j, Y');

        $next_payments = $total - $deposit;
        $origin_next_payments = $next_payments + $remaining_amounts['discount'] - $remaining_amounts['tax'] - $remaining_amounts['fee'] - $remaining_amounts['shipping'] - $remaining_amounts['shipping_tax'];

        $schedule = array();

        $plans = array();
        foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
           if ( isset( $cart_item['vicodin_deposit'] , $cart_item['vicodin_deposit']['deposit']) && $cart_item['vicodin_deposit']['enable']) {
	           $plans[] = vicodin_get_payment_dues( $cart_item['vicodin_deposit']['plan'], $cart_item['line_subtotal'] );
           }
        }

       foreach ( $plans as $plan ) {
           foreach ( $plan as $partial) {
               $date = $partial['date'];
                if ( array_key_exists( $date, $schedule ) ) {
                    $schedule[ $date ]['amount'] += $partial['amount'];
                    $schedule[ $date ]['fee'] += $partial['fee'];
                }else {
                    $schedule[ $date ] = array(
                            'id'        => '',
                            'type'      => 'partial_payment',
                            'date'      => $date,
                            'amount'    => $partial['amount'],
                            'fee'       => $partial['fee'],
                    );
                }
           }
       }

       foreach( $schedule as &$payment ) {
           $rate = $payment['amount']  / $origin_next_payments ;
           $amount = $rate * $next_payments;
           $fee = $payment['fee'];
           $payment['total'] = $amount + $fee;
       }

       $schedule[ $current_date_string ] =  array(
               'id'        => '',
               'type'      => 'deposit',
               'date'      => $current_date_string,
               'amount'    => $deposit,
               'fee'       => $deposit_fee,
               'total'     => $deposit + $deposit_fee
		);

        usort( $schedule, function ( $a, $b ) {
            return strtotime( $a['date'] ) - strtotime( $b['date'] );
        });

        return $schedule;
	}

    public function vicodin_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
	    if ($order->get_type() != $this->deposit_type ){
		    $deposit_meta = isset($values['vicodin_deposit']) ? $values['vicodin_deposit'] : false;
		    if ($deposit_meta) {
			    $item->add_meta_data('vicodin_deposit_meta', $deposit_meta, true);
		    }
	    }
    }
    public function vicodin_checkout_update_order_meta( $order_id, $data) {
        $order = wc_get_order( $order_id );

        if ( $order->get_type() == $this->deposit_type) {
            return;
        }

        if ( isset( WC()->cart->vwcdi_deposit_info, WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) && WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) {
            $deposit = WC()->cart->vwcdi_deposit_info['deposit_amount'];
            $remaining = WC()->cart->get_total('edit') - $deposit;
            $schedule = WC()->cart->vwcdi_deposit_info['payment_schedule'];
            $interest_total = WC()->cart->vwcdi_deposit_info['fee_total'];

            $order->add_meta_data( 'vicodin_deposit_payment_schedule', $schedule, true);
            $order->add_meta_data( 'vicodin_deposit_amount', $deposit, true);
            $order->add_meta_data( 'vicodin_future_payment', $remaining, true);
            $order->add_meta_data( 'vicodin_interest_total', $interest_total , true );
            $order->add_meta_data( 'vicodin_is_deposit_order', true );

            $order->save();
        }
    }

    public function vicodin_available_payment_gateways( $available_gateways ) {

	    $vicodin_st = get_option( 'vicodin_deposit_setting' );

	    $pay_slug = get_option('woocommerce_checkout_pay_endpoint', 'order-pay');

        $order_id = absint( get_query_var( $pay_slug ) );

        $order = wc_get_order( $order_id );

        $is_deposit_order =  false;

        if ( isset( WC()->cart->vwcdi_deposit_info, WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) && WC()->cart->vwcdi_deposit_info['deposit_enabled'] ){
            $is_deposit_order = true;
        }

        if ( $order && $order->get_type() == $this->deposit_type ) {
            $is_deposit_order = true;
        }

        if ( $is_deposit_order ) {
	        $exclude_payment_methods = $vicodin_st['exclude_payment_methods'] ?? array();
	        foreach ( $available_gateways as $slug => $gateway ) {
		        if ( in_array( $slug, $exclude_payment_methods ) ){
			        unset( $available_gateways[ $slug ] );
		        }
	        }
        }

        return $available_gateways;
    }

    public function vicodin_get_order_item_totals( $total_rows, $order  ) {
        $is_deposit = $order->get_meta( 'vicodin_is_deposit_order' );
        if( $is_deposit ) {
            $status = $order->get_status();
            $deposit_amount = $order->get_meta( 'vicodin_deposit_amount' );
            $remaining_amount = $order->get_meta( 'vicodin_future_payment' );
            $interest_total = floatval( $order->get_meta( 'vicodin_interest_total' ) );

	        $received_slug = get_option('woocommerce_checkout_order_received_endpoint', 'order-received' );
	        $pay_slug = get_option('woocommerce_checkout_order_pay_endpoint', 'order-pay' );

	        $is_checkout = ( get_query_var( $received_slug ) === '' && is_checkout() );
	        $is_email = did_action( 'woocommerce_email_order_details' ) > 0;
	        $is_remaining = !!get_query_var( $pay_slug ) && $status === 'partially-paid';

	        if (!$is_checkout || $is_email) {
		        $total_rows['vwcdi_deposit_amount'] = array(
			        'label' => __( 'Deposit', 'vico-deposit-and-installment' ),
			        'value' => wc_price($deposit_amount, array('currency' => $order->get_currency(), 'decimals' => wc_get_price_decimals() ) )
		        );
		        $total_rows['vwcdi_future_payment'] = array(
			        'label' => __( 'Future payments', 'vico-deposit-and-installment' ),
			        'value' => wc_price( $remaining_amount, array('currency' => $order->get_currency(), 'decimals' => wc_get_price_decimals() ) )
		        );
		        $total_rows['vwcdi_interest_total'] = array(
			        'label' => __( 'Total interest', 'vico-deposit-and-installment' ),
			        'value' => wc_price( $interest_total, array('currency' => $order->get_currency(), 'decimals' => wc_get_price_decimals() ) )
		        );
	        }

//	        if ($is_checkout && !$is_remaining && !$is_email) {
//		        if ($deposit_paid !== 'yes') {
//			        $to_pay = $deposit_amount;
//		        } elseif ($deposit_paid === 'yes' && $second_payment_paid !== 'yes') {
//			        $to_pay = $second_payment;
//		        }
//		        $total_rows['paid_today'] = array(
//			        'label' => esc_html($to_pay_text),
//			        'value' => wc_price($to_pay, array('currency' => $order->get_currency()))
//		        );
//	        }

//	        if ( $is_checkout && $is_remaining && !$is_email ) {
//		        $partial_pay_id = absint(get_query_var($pay_slug));
//		        $partial_payment = wc_get_order($partial_pay_id);
//
//		        $total_rows['paid_today'] = array(
//			        'label' => esc_html($to_pay_text),
//			        'value' => wc_price($partial_payment->get_total(), array('currency' => $order->get_currency()))
//		        );
//	        }
        }

        return $total_rows;
    }

    public function vicodin_create_order( $order_id, $checkout ) {
        if ( !isset( WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) || !WC()->cart->vwcdi_deposit_info['deposit_enabled'] ) {
            return null;
        }

        $data = $checkout->get_posted_data();

        try{

	        $cart_hash = WC()->cart->get_cart_hash();
	        $order_id = absint(WC()->session->get('order_awaiting_payment'));
	        $order = $order_id ? wc_get_order($order_id) : null;
	        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

	        if ($order && $order->has_cart_hash($cart_hash) && $order->has_status(array('pending', 'failed'))) {
		        do_action('woocommerce_resume_order', $order_id);
		        $order->remove_order_items();
	        } else {
		        $order = new \WC_Order();
	        }

	        $fields_prefix = array(
		        'shipping' => true,
		        'billing' => true,
	        );

	        $shipping_fields = array(
		        'shipping_method' => true,
		        'shipping_total' => true,
		        'shipping_tax' => true,
	        );

	        foreach ( $data as $key => $value ) {
		        if ( is_callable( array( $order, "set_{$key}" ) ) ) {
			        $order->{"set_{$key}"}($value);
		        } elseif ( isset($fields_prefix[ current( explode('_', $key ) ) ] ) ) {
			        if ( !isset($shipping_fields[$key] ) ) {
				        $order->update_meta_data('_' . $key, $value);
			        }
		        }
	        }
	        $user_agent = wc_get_user_agent();
	        $order_vat_exempt = WC()->cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no';

	        $order->hold_applied_coupons( $data['billing_email'] );
	        $order->set_created_via( 'checkout' );
	        $order->set_cart_hash( $cart_hash );
	        $order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
	        $order->set_currency( get_woocommerce_currency() );
	        $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
	        $order->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
	        $order->set_customer_user_agent( wc_get_user_agent() );
	        $order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
	        $order->set_payment_method( '' );
	        $checkout->set_data_from_cart( $order );

	        do_action('woocommerce_checkout_create_order', $order, $data);

	        $order_id = $order->save();

	        do_action('woocommerce_checkout_update_order_meta', $order_id, $data);

	        $order->read_meta_data();
	        $payment_schedule = $order->get_meta('vicodin_deposit_payment_schedule');
	        $deposit_id = null;

            if ( $payment_schedule ) {
                foreach ( $payment_schedule as $partial_key => $partial ) {

                    $partial_payment = new Partial_Order();

	                $partial_payment->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );

                    $amount = $partial['total'];

	                $name = esc_html__('Partial Payment for order %s', 'vico-deposit-and-installment');
	                $partial_payment_name = apply_filters('vicodin_deposit_partial_payment_name', sprintf( $name, $order->get_order_number() . '-' . $partial_key ), $partial, $order->get_id());

	                $item = new \WC_Order_Item_Fee();

	                $item->set_props(
		                array(
			                'total' => $amount
		                )
	                );


	                $item->set_name( $partial_payment_name );
	                $partial_payment->add_item($item);

	                $partial_payment->set_parent_id( $order->get_id() );
	                $partial_payment->add_meta_data( 'is_vat_exempt', $order_vat_exempt);
	                $partial_payment->add_meta_data( 'vicodin_partial_payment_type', $partial['type'] );
                    $partial_payment->add_meta_data( 'vicodin_partial_payment_date', $partial['date'] );
	                $partial_payment->set_currency(get_woocommerce_currency());
	                $partial_payment->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
	                $partial_payment->set_customer_ip_address(\WC_Geolocation::get_ip_address());
	                $partial_payment->set_customer_user_agent($user_agent);
	                $partial_payment->set_total($amount);
	                $partial_payment->save();
	                $payment_schedule[$partial_key]['id'] = $partial_payment->get_id();

                    $this->add_apifw_invoice_meta($partial_payment, $amount, $partial_payment_name);

	                $order_number_meta = $order->get_meta('_alg_wc_full_custom_order_number' );
	                if( $order_number_meta ){
		                $partial_payment->add_meta_data('_alg_wc_full_custom_order_number', $order_number_meta);
	                }

//	                 Added for payable payment support
	                foreach ($data as $key => $value) {
		                if (is_callable(array($order, "set_{$key}"))) {
			                   $partial_payment->{"set_{$key}"}($value);
		                } elseif (isset($fields_prefix[current(explode('_', $key))])) {
			                if (!isset($shipping_fields[$key])) {
				                $partial_payment->update_meta_data('_' . $key, $value);
			                }
		                }
	                }

                    $partial_payment->save();

                    if ( $partial['type'] === 'deposit' ) {
                        $deposit_id = $partial_payment->get_id();
	                    $partial_payment->set_payment_method($available_gateways[ $data['payment_method'] ] ?? $data['payment_method'] );
	                    $partial_payment->save();
                    }
                }
            }
            $order->update_meta_data( 'vicodin_deposit_payment_schedule', $payment_schedule );
	        $order->save();
	        return absint( $deposit_id );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'checkout-error', $e->getMessage() );
        }
    }

    public function vicodin_my_account_my_orders_actions( $actions, $order ) {
        $status = $order->get_status();
        $is_deposit = $order->get_meta('vicodin_is_deposit_order');
        if ( $status === 'installment' || $status === 'pending') {

	        $args = array(
		        'post_parent' => $order->get_id(),
		        'parent_order_id' => $order->get_id(),
		        'post_type'   => 'vwcdi_partial_order',
		        'numberposts' => -1,
		        'post_status' => 'pending',
		        'orderby'     => 'ID',
		        'order'       => 'ASC',
	        );

            $order_need_payment = wc_get_orders( $args )[0] ?? null;

            if ( $order_need_payment && $is_deposit ) {
	            $checkout_url = $order_need_payment->get_checkout_payment_url();

	            $actions['pay_partial'] = array(
		            'url'  => esc_url( $checkout_url ),
		            'name' => __('Pay', 'vico-deposit-and-installment'),
	            );
            }
        }
        return $actions;
    }

    public function vicodin_rewrite_order_tails_table( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_type() == $this->deposit_type ) {
	        remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
	        remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
	        if( apply_filters('vicodin_disable_orders_details_table', true) ){
                $order_id = $order->get_parent_id();
                include __DIR__ . '/views/partial-order-details.php';
	        }

        }
    }

    public function vicodin_order_details_after_order_table( $order ) {
	    $is_deposit_order = $order->get_meta('vicodin_is_deposit_order');
        $schedule = $order->get_meta('vicodin_deposit_payment_schedule');

        if ( $is_deposit_order && $schedule ) {
	        include __DIR__ . '/views/installment-plan-summary.php';
        }
    }
    public function vicodin_get_checkout_payment_url( $url, $order ) {
        $is_deposit = $order->get_meta('vicodin_is_deposit_order');

        if ( $is_deposit && $order->get_type() != $this->deposit_type ) {
	        $schedule = $order->get_meta('vicodin_deposit_payment_schedule');

            if (is_array($schedule) && !empty($schedule)) {
	            foreach ( $schedule as $payment ) {
		            $payment_order = wc_get_order( $payment['id'] );

		            if ( ! $payment_order ) {
			            continue;
		            }

		            if ( ! $payment_order || ! $payment_order->needs_payment() ) {
			            continue;
		            }
		            $url = $payment_order->get_checkout_payment_url();
		            $url = add_query_arg( array( 'payment' => $payment['type'], ), $url );
		            break;
	            }
            }
        }
        return $url;
    }

    public function vicodin_order_status_changed( $order_id, $old_status, $new_status, $order){

	    if ( $old_status === $new_status ) {
		    return;
	    }

        if ( $order && $order->get_type() == $this->deposit_type ) {
            $parent = wc_get_order( $order->get_parent_id() );
            $parent_id = $parent->get_id();
            $order_total = $parent->get_total();
            $suborders_total = 0;
	        $args = array(
		        'post_parent'       => $parent_id,
		        'parent_order_id'   => $parent_id,
		        'post_type'         => 'vwcdi_partial_order',
		        'numberposts'       => -1,
	        );

            $suborders = wc_get_orders( $args );

            $has_on_hold = false;

            foreach ( $suborders as $suborder ) {
                if ( $suborder->get_status() === 'on-hold') {
                    $has_on_hold = true;
                }elseif ( $suborder->get_status() === 'completed' ){
                    $suborders_total += $suborder->get_total();
                }
            }
            if ( $new_status === 'completed' ) {
                if ( ceil( $suborders_total ) >= $order_total ) {
                    $parent->set_status( 'processing' );
                    $parent->save();
                } else {
                    $parent->set_status( 'installment' );
                    $parent->save();
                }
            }else if ( $new_status != 'processing' ) {
                if ( $new_status === 'pending' && $suborders_total > 0 ) {
	                $parent->set_status( 'installment' );
	                $parent->save();
                }elseif ( $has_on_hold ){
	                $parent->set_status( 'on-hold' );
	                $parent->save();
                } else {
                    $parent->set_status( $new_status );
                    $parent->save();
                }
            }


        }
    }

    public function  vicodin_display_payment_schedule( $order ) {
        $admin = false;
	    include __DIR__ . '/views/installment-plan-summary.php';
    }
	function add_apifw_invoice_meta($partial_payment, $amount, $name){

		$data = array(
			'id' => false,
			'subtotal' => $amount,
			'subtotal_tax' => 0,
			'total' => $amount,
			'total_tax' => 0,
			'price' => $amount,
			'price_after_discount' => $amount,
			'quantity' => '',
			'weight' => '',
			'total_weight' => '',
			'weight_unit' => '',
			'tax_class' => '',
			'tax_status' => '',
			'tax_percent' => 0,
			'tax_label' => '',
			'tax_pair' => '',
			'tax_array' => '',
			'name' => $name,
			'product_id' => '',
			'variation_id' => '',
			'product_url' => '',
			'product_thumbnail_url' => '',
			'sku' => '',
			'meta' => '',
			'formatted_meta' => '',
			'raw_meta' => '',
			'category' => ''
		);

		$partial_payment->add_meta_data( 'vicodin_apifw_invoice_meta', $data );
	}
}