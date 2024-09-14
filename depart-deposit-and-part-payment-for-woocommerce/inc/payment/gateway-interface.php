<?php

namespace VicoDIn\Inc\Payment;
defined( 'ABSPATH' ) || exit;

interface Gateway_Interface {
	public function is_available();
	public function process_part_payment( $suborder, $payment_token );
}
