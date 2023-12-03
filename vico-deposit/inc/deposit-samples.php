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
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function default_plan() {
		$plans = get_option( 'vicodin_payment_plan', array() );
		if ( empty( $plans ) ) {
			$plan = [
				'plan_id'          => 1,
				'plan_name'        => 'Sample plan',
				'plan_active'      => true,
				'plan_description' => 'sample description',
				'deposit'          => 50,
				'deposit_fee'      => 0,
				'plan_schedule'    => array(
					[
						'partial'   => 50,
						'after'     => 7,
						'date_type' => 'day',
						'fee'       => ''
					]
				),
				'duration'         => '7 Days',
				'total'            => 100,
				'fee_total'        => 0
			];
			add_option( 'vicodin_payment_plan', array( '1' => $plan ) );
		}
	}

	public function default_rule() {
		$rules = get_option( 'vicodin_deposit_rule', array() );

		if ( empty( $rules ) ) {
			$rule = [
				'rule_id'             => 'default',
				'rule_name'           => 'Default rule',
				'rule_active'         => true,
				'rule_categories_inc' => array(),
				'rule_categories_exc' => array(),
				'rule_tags_inc'       => array(),
				'rule_tags_exc'       => array(),
				'rule_users_inc'      => array(),
				'rule_users_exc'      => array(),
				'rule_products_inc'   => array(),
				'rule_products_exc'   => array(),
				'rule_price_range'    => array(
					'price_start' => 0,
					'price_end'   => 0,
				),
				'payment_plans'       => array( '1' ),
				'rule_apply_for'      => 'All',
				'rule_plan_names'     => 'Sample plan'
			];
			add_option( 'vicodin_deposit_rule', array( 'default' => $rule ) );
		}

	}

	public function default_setting() {
		$vicodin_settings = get_option( 'vicodin_deposit_setting', array() );
		if ( empty( $vicodin_settings ) ) {
			$args = [
				'enabled'                 => '0',
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
		if ( ! $vicodin_id ) {
			add_option( 'vicodin_id', '2' );
		}
	}

}