<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'vicodin_get_due_date' ) ) {
	function vicodin_get_payment_dues( $plan, $price ) {
		$current_date = new DateTime();
		$schedule = $plan['plan_schedule'];
		$unit = $plan['unit-type'] ?? 'percentage';
		$schedule_formatted = array();

		foreach ( $schedule as $partial ) {
			$after    = empty($partial['after']) ? '0' : $partial['after'];
			$date_type = $partial['date_type'];
			$amount   = $partial['partial'];
			$fee      = $partial['fee'];

			if ( 'month' === $date_type ) {
				$current_date->modify('+' . $after . ' months');
			} elseif ( 'day' === $date_type ) {
				$current_date->modify('+' . $after . ' days');
			} elseif ( 'year' === $date_type ) {
				$current_date->modify('+' . $after . ' years');
			}

			$due_date = $current_date->getTimestamp();

			$amount = vicodin_get_due_amount( $amount, $price, $unit);
			$fee = vicodin_get_due_amount( $fee, $amount, $unit );

			$schedule_formatted[] = array(
				'date'   => $due_date,
				'fee'    => $fee,
				'amount' => $amount
			);
		}

		return $schedule_formatted;
	}
}

if ( ! function_exists( 'vicodin_get_due_amount' ) ) {
	function vicodin_get_due_amount( $amount, $price, $unit = 'percentage' ) {
		if ( 'fixed' === $unit ) {
			return (float)$amount;
		}
		return floatval($amount) * floatval($price) / 100;
	}
}

if ( ! function_exists( 'vicodin_check_wc_active' ) ) {
	function vicodin_check_wc_active( ) {
		if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins') ) ) ) {
			return true;
		}else {
			return false;
		}
	}
}


