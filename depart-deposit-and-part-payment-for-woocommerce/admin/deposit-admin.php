<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Deposit_Admin {
	protected static $instance;

	private function __construct() {
        add_action( 'admin_init', array( $this, 'admin_init') );
		add_action( 'admin_menu', array( $this, 'load_admin_menu' ) );
		Deposit_Plan::instance();
		Deposit_Rule::instance();
		Deposit_Setting::instance();
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
    

    /*Check Auto update*/
    public function admin_init() {
    $key = Deposit_Setting::get_field( 'key', '' );
        /*Check update*/
        if ( class_exists( 'VillaTheme_Plugin_Check_Update' ) ) {
            new \VillaTheme_Plugin_Check_Update ( DEPART_CONST['version'], // current version
                'https://villatheme.com/wp-json/downloads/v3',  // update path
                'depart-deposit-and-part-payment-for-woocommerce/depart-deposit-and-part-payment-for-woocommerce.php', // plugin file slug
                'depart-deposit-and-part-payment-for-woocommerce', '', $key );
            $setting_url = admin_url( 'admin.php?page=depart_setting' );
            new \VillaTheme_Plugin_Updater( 'depart-deposit-and-part-payment-for-woocommerce/depart-deposit-and-part-payment-for-woocommerce.php', 'depart-deposit-and-part-payment-for-woocommerce', $setting_url );
        }
    }

	public function load_admin_menu() {

		add_menu_page(
			esc_html__( 'DEPART', 'depart-deposit-and-part-payment-for-woocommerce' ),
			esc_html__( 'DEPART', 'depart-deposit-and-part-payment-for-woocommerce' ),
			'manage_woocommerce',
			'depart_menu',
			null,
			DEPART_CONST['img_url'] . '/depart.png',
			55
		);

		add_submenu_page(
			'depart_menu',
			esc_html__( 'Payment Plans', 'depart-deposit-and-part-payment-for-woocommerce' ),
			esc_html__( 'Payment Plans', 'depart-deposit-and-part-payment-for-woocommerce' ),
			'manage_woocommerce',
			'depart_menu',
			array( 'VicoDIn\Admin\Deposit_Plan', 'page_callback' )
		);

		add_submenu_page(
			'depart_menu',
			esc_html__( 'Deposit Rules', 'depart-deposit-and-part-payment-for-woocommerce' ),
			esc_html__( 'Deposit Rules', 'depart-deposit-and-part-payment-for-woocommerce' ),
			'manage_woocommerce',
			'depart_rule',
			array( 'VicoDIn\Admin\Deposit_Rule', 'page_callback' )
		);

		add_submenu_page(
			'depart_menu',
			esc_html__( 'Settings', 'depart-deposit-and-part-payment-for-woocommerce' ),
			esc_html__( 'Settings', 'depart-deposit-and-part-payment-for-woocommerce' ),
			'manage_woocommerce',
			'depart_setting',
			array( 'VicoDIn\Admin\Deposit_Setting', 'page_callback' )
		);

	}

}