<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Deposit_Admin {
	protected static $instance;

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'load_admin_menu' ) );
		Deposit_Plan::instance();
		Deposit_Rule::instance();
		Deposit_Setting::instance();
	}

	public static function instance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function load_admin_menu() {

		add_menu_page(
			esc_html__( 'Vico Deposit', 'vico-deposit-and-installment' ),
			esc_html__( 'Vico Deposit', 'vico-deposit-and-installment' ),
			'manage_woocommerce',
			'vicodin_menu',
			null,
			VICODIN_CONST['img_url'] . '/deposit.png',
			5
		);

		add_submenu_page(
			'vicodin_menu',
			esc_html__( 'Payment Plans', 'vico-deposit-and-installment' ),
			esc_html__( 'Payment Plans', 'vico-deposit-and-installment' ),
			'manage_woocommerce',
			'vicodin_menu',
			array( 'VicoDIn\Admin\Deposit_Plan', 'page_callback' )
		);

		add_submenu_page(
			'vicodin_menu',
			esc_html__( 'Deposit Rules', 'vico-deposit-and-installment' ),
			esc_html__( 'Deposit Rules', 'vico-deposit-and-installment' ),
			'manage_woocommerce',
			'vicodin_rule',
			array( 'VicoDIn\Admin\Deposit_Rule', 'page_callback' )
		);

		add_submenu_page(
			'vicodin_menu',
			esc_html__( 'Settings', 'vico-deposit-and-installment' ),
			esc_html__( 'Settings', 'vico-deposit-and-installment' ),
			'manage_woocommerce',
			'vicodin_setting',
			array( 'VicoDIn\Admin\Deposit_Setting', 'page_callback' )
		);

	}

}