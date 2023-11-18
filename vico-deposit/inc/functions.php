<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'vicodin_get_due_date' ) ) {
	function vicodin_get_payment_dues( $plan, $price ) {
		$current_date = new DateTime();
		$schedule = $plan['plan-schedule'];
		$unit = $plan['unit-type'] ?? 'percentage';
		$schedule_formatted = array();

		foreach( $schedule as $partial) {
			$after = empty($partial['after']) ? '0' : $partial['after'];
			$dateType = $partial['date-type'];
			$amount = $partial['partial'];
			$fee = $partial['fee'];

			if ($dateType === 'month') {
				$current_date->modify('+' . $after . ' months');
			} elseif ($dateType === 'day') {
				$current_date->modify('+' . $after . ' days');
			} elseif ($dateType === 'year') {
				$current_date->modify('+' . $after . ' years');
			}

			$due_date = $current_date->format('F j, Y');

			$amount = vicodin_get_due_amount( $amount, $price, $unit);
			$fee = vicodin_get_due_amount( $fee, $amount, $unit );

			$schedule_formatted[] = array(
				'date' => $due_date,
				'fee' => $fee,
				'amount' => $amount
			);
		}

		return $schedule_formatted;
	}
}

if ( ! function_exists( 'vicodin_get_due_amount' ) ) {
	function vicodin_get_due_amount( $amount, $price, $unit = 'percentage' ) {
		if ( $unit === 'fixed' ) {
			return (float)$amount;
		}
		return floatval($amount) * floatval($price) / 100;
	}
}