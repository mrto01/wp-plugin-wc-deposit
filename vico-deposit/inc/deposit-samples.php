<?php

namespace VicoDIn\Inc;

defined( 'ABSPATH' ) || exit;

class Deposit_Samples {
	protected static $instance = null;

	private function __construct() {
		$this->default_plan();
		$this->default_rule();
		$this->default_setting();
		$this->vicodin_id();
	}

	public static function init() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function default_plan() {
		$plans = get_option( 'vicodin_payment_plan', array() );
		if ( empty( $plans ) ) {
			$plan = [
				'plan-id'          => 1,
				'plan-name'        => 'Sample plan',
				'plan-active'      => true,
				'plan-description' => 'sample description',
				'deposit'          => 50,
				'deposit-fee'      => '',
				'plan-schedule'    => array(
					[
						'partial'   => 50,
						'after'     => 7,
						'date-type' => 'day',
						'fee'       => ''
					]
				),
				'duration'         => '7 Days',
				'total-percent'    => 100
			];
			add_option( 'vicodin_payment_plan', array( '1' => $plan ) );
		}
	}

	public function default_rule() {
		$rules = get_option( 'vicodin_deposit_rule', array() );

		if ( empty( $rules ) ) {
			$rule = [
				'rule-id'             => 'default',
				'rule-name'           => 'Default rule',
				'rule-active'         => true,
				'rule-categories-inc' => array( '' ),
				'rule-categories-exc' => array( '' ),
				'rule-tags-inc'       => array( '' ),
				'rule-tags-exc'       => array( '' ),
				'rule-users-inc'      => array( '' ),
				'rule-users-exc'      => array( '' ),
				'rule-products-inc'   => array( '' ),
				'rule-products-exc'   => array( '' ),
				'rule-price-range'    => array(
					'price-start' => 0,
					'price-end'   => 0,
				),
				'payment-plans'       => array( '1' ),
				'rule-apply-for'      => 'All',
				'rule-plan-names'     => 'Sample plan'
			];
			add_option( 'vicodin_deposit_rule', array( 'default' => $rule ) );
		}

	}

	public function default_setting() {
		$vicodin_settings = get_option( 'vicodin_deposit_setting', array() );
		if ( empty( $vicodin_settings ) ) {
			$args = [
				'enabled'                  => '0',
				'auto_charge'             => '0',
				'exclude_payment_methods' => array(),
				'coupon'                  => 'deposit',
				'tax'                     => 'deposit',
				'fee'                     => 'deposit',
				'shipping'                => 'deposit',
				'shipping_tax'            => 'deposit',
			];
			add_option( 'vicodin_deposit_setting', $args );
		}
	}

	public function vicodin_id() {
		$vicodin_id = get_option('vicodin_id');
		if ( !$vicodin_id ) {
			add_option( 'vicodin_id', '2' );
		}
	}

}