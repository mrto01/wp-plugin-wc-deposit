<?php

namespace VicoDIn\Inc\Payment;

use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Gateway;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Utilities\Money_Utility;

defined( 'ABSPATH' ) || exit;

class Square_Gateway implements Gateway_Interface {

	static $instance = null;

	public $wc_square;

	public $api;

	public function support_methods() {
		return array(
			Plugin::GATEWAY_ID
		);
	}

	public function is_available() {
		$available_gateways = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
		if ( array_intersect( $available_gateways, $this->support_methods() ) ) {
			return true;
		}

		return false;
	}

	public function __construct() {
		$this->wc_square = wc_square()->get_gateway( Plugin::GATEWAY_ID );
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function process_part_payment( $suborder, $payment_token ) {
		// Add square payment info to suborder
		$suborder->payment_total      = number_format( $suborder->get_total(), 2, '.', '' );
		$suborder->square_customer_id = '';
		$suborder->payment            = new \stdClass();
		$suborder->payment->type      = str_replace( '-', '_', $this->wc_square->get_payment_type() );

		if ( false !== ( $customer_id = $this->wc_square->get_customer_id( $suborder->get_user_id(), array( 'order' => $suborder ) ) ) ) {
			$suborder->square_customer_id = $customer_id;
		}
		/* translators: Placeholders: %1$s - site title, %2$s - order number */
		$suborder->description = sprintf( esc_html__( '%1$s - Order %2$s', 'depart-deposit-and-part-payment-for-woocommerce' ), wp_specialchars_decode( Square_Helper::get_site_name(), ENT_QUOTES ), $suborder->get_order_number() );

		$suborder                          = $this->get_order_with_unique_transaction_ref( $suborder );
		$suborder->payment->token          = $payment_token->get_token();
		$suborder->payment->account_number = $payment_token->get_last4();
		$suborder->payment->last_four      = $payment_token->get_last4();

		if ( 'CC' === $payment_token->get_type() ) {

			// credit card specific attributes
			$suborder->payment->card_type = $payment_token->get_card_type();
			$suborder->payment->exp_month = $payment_token->get_expiry_month();
			$suborder->payment->exp_year  = $payment_token->get_expiry_year();
		}
		// standardize expiration date year to 2 digits
		if ( ! empty( $suborder->payment->exp_year ) && 4 === strlen( $suborder->payment->exp_year ) ) {
			$suborder->payment->exp_year = substr( $suborder->payment->exp_year, 2 );
		}

		// create square order
		try {
			$location_id               = $this->wc_square->get_plugin()->get_settings_handler()->get_location_id();
			$response                  = $this->get_api()->create_order( $location_id, $suborder );
			$suborder->square_order_id = $response->getId();

			// adjust order by difference between WooCommerce and Square order totals
			$wc_total     = Money_Utility::amount_to_cents( $suborder->get_total() );
			$square_total = $response->getTotalMoney()->getAmount();
			$delta_total  = $wc_total - $square_total;
			if ( abs( $delta_total ) > 0 ) {
				$response = $this->get_api()->adjust_order( $location_id, $suborder, $response->getVersion(), $delta_total );

				// since a downward adjustment causes (downward) tax recomputation, perform an additional (untaxed) upward adjustment if necessary
				$square_total = $response->getTotalMoney()->getAmount();
				$delta_total  = $wc_total - $square_total;

				if ( $delta_total > 0 ) {
					$response = $this->get_api()->adjust_order( $location_id, $suborder, $response->getVersion(), $delta_total );
				}
			}

			// reset the payment total to the total calculated by Square to prevent errors
			$suborder->payment_total = Square_Helper::number_format( Money_Utility::cents_to_float( $response->getTotalMoney()->getAmount() ) );

		} catch ( \Exception $e ) {
			// log the error, but continue with payment
			if ( $this->wc_square->debug_log() ) {
				$this->wc_square->get_plugin()->log( $e->getMessage(), $this->wc_square->get_id() );
			}
		}

		// do square transaction
		$response = $this->get_api()->credit_card_charge( $suborder );
		if ( $response->has_errors() ) {
			$logger = wc_get_logger();
			$logger->debug(
				print_r( $response->get_errors(), true ),
				array(
					'source' => 'depart-deposit-and-part-payment-for-woocommerce-' . $payment_token->get_gateway_id()
				)
			);
			throw new \Exception( esc_html__('Do square transaction failed!', 'depart-deposit-and-part-payment-for-woocommerce') );
		}
		$suborder->payment_complete();
	}

	public function get_order_with_unique_transaction_ref( $order ) {

		$order_id = Order_Compatibility::get_prop( $order, 'id' );

		// generate a unique retry count
		if ( is_numeric( $this->wc_square->get_order_meta( $order_id, 'retry_count' ) ) ) {
			$retry_count = $this->wc_square->get_order_meta( $order_id, 'retry_count' );

			$retry_count ++;
		} else {
			$retry_count = 0;
		}

		// keep track of the retry count
		$this->wc_square->update_order_meta( $order, 'retry_count', $retry_count );

		// generate a unique transaction ref based on the order number and retry count, for gateways that require a unique identifier for every transaction request
		$order->unique_transaction_ref = ltrim( $order->get_order_number(), esc_html_x( '#', 'hash before order number', 'depart-deposit-and-part-payment-for-woocommerce' ) ) . ( $retry_count > 0 ? '-' . $retry_count : '' );

		return $order;
	}

	public function get_api() {
		$settings  = $this->wc_square->get_plugin()->get_settings_handler();
		$this->api = new Gateway\API( $settings->get_access_token(), $settings->get_location_id(), $settings->is_sandbox() );

		return $this->api;
	}
}