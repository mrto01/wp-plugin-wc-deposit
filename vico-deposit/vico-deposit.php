<?php

/**
 * Plugin Name: Vico deposit and installment for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/vico-deposit-and-installment/
 * Description: Empower customers with flexible payment options. Allow partial payments for products or services. Boost conversions and customer satisfaction.
 * Version: 1.0.1
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * Text Domain: vico-deposit-and-installment
 * Domain Path: /languages
 * Copyright 2023 - 2024 VillaTheme.com. All rights reserved.
 * Requires at least: 5.0
 * Tested up to: 6.2
 * Requires PHP: 7.0
 **/

namespace VicoDIn;

use VicoDIn\Admin\Deposit_Admin;
use VicoDIn\Admin\Enqueue;
use VicoDIn\Inc\Deposit_Backend;
use VicoDIn\Inc\Deposit_Front_End;
use VicoDIn\Inc\Deposit_Order_Type;
use VicoDIn\Inc\Deposit_Samples;
use WP_Error;

defined( 'ABSPATH' ) || exit;

define( 'VICODIN_CONST', [
	'version'     => '1.0.1',
	'plugin_name' => 'Vico deposit and installment',
	'slug'        => 'vicodin',
	'assets_slug' => 'vicodin-',
	'file'        => __FILE__,
	'basename'    => plugin_basename( __FILE__ ),
	'plugin_dir'  => plugin_dir_path( __FILE__ ),
	'dist_url'    => plugins_url( 'assets/dist/', __FILE__ ),
	'img_url'     => plugins_url( 'assets/img/', __FILE__ ),
	'libs_url'    => plugins_url( 'assets/libs/', __FILE__ ),
	'order_type'  => 'vwcdi_partial_order'
] );

require_once VICODIN_CONST['plugin_dir'] . '/autoload.php';

if ( ! class_exists( 'WP_Vico_Deposit' ) ) {

	class WP_Vico_Deposit {
		protected $checker;

		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'plugin_loaded' ), 20 );
			add_action( 'admin_notices',
				array( $this, 'plugin_require_notices' ) );
			register_activation_hook( __FILE__,
				array( $this, 'vicodin_activate' ) );
		}

		public function plugin_loaded() {

			$this->checker = new WP_Error();
			global $wp_version;
			$php_require = '7.0';
			$wp_require  = '5.0';

			if ( version_compare( phpversion(), $php_require, '<' ) ) {
				$this->checker->add( '', sprintf( '%s %s',
					esc_html__( 'require PHP version at least',
						'vico-deposit-and-installment' ), $php_require ) );
			}

			if ( version_compare( $wp_version, $wp_require, '<' ) ) {
				$this->checker->add( '', sprintf( '%s %s',
					esc_html__( 'require WordPress version at least',
						'vico-deposit-and-installment' ), $wp_require ) );
			}

			if ( $this->checker->has_errors() ) {
				return;
			}

			$this->init();
		}

		public function init() {
			if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
				require_once VICODIN_CONST['plugin_dir'] . '/inc/support/support.php';
			}

			$environment = new \VillaTheme_Require_Environment( [
				'plugin_name'     => 'Vico deposit and installment for WooCommerce',
				'php_version'     => '7.0',
				'wp_version'      => '5.0',
				'wc_version'      => '5.0',
				'require_plugins' => [
					[
						'slug' => 'woocommerce',
						'name' => 'WooCommerce',
					],
				]
			] );

			if ( $environment->has_error() ) {
				return;
			}
			add_filter( 'plugin_action_links_' . VICODIN_CONST['basename'],
				array( $this, 'setting_link' ) );
			$this->load_text_domain();
			$this->load_classes();
		}

		public function vicodin_activate() {
			Deposit_Samples::init();
		}

		public function setting_link( $links ) {
			return array_merge( $links,
				[
					sprintf( "<a href='%1s' >%2s</a>",
						esc_url( admin_url( 'edit.php?post_type=wp_vicodin' ) ),
						esc_html__( 'Settings',
							'vico-deposit-and-installment' ) )
				]
			);
		}

		public function load_text_domain() {
			load_plugin_textdomain( 'vico-deposit-and-installment', false,
				VICODIN_CONST['basename'] . '/languages' );
		}

		public function plugin_require_notices() {
			if ( ! $this->checker instanceof WP_Error
			     || ! $this->checker->has_errors()
			) {
				return;
			}

			$messages = $this->checker->get_error_messages();
			foreach ( $messages as $message ) {
				echo sprintf( "<div id='message' class='error'><p>%s %s</p></div>",
					esc_html( VICODIN_CONST_CONST['plugin_name'] ),
					wp_kses_post( $message ) );
			}
		}
		public function support() {
			if ( class_exists( 'VillaTheme_Support' ) ) {
				new \VillaTheme_Support(
					array(
						'support'    => 'https://wordpress.org/support/plugin/',
						'docs'       => 'http://docs.villatheme.com/',
						'review'     => 'https://wordpress.org/support/plugin/',
						'pro_url'    => '',
						'css'        => VICODIN_CONST['dist_url'],
						'image'      => VICODIN_CONST['img_url'],
						'slug'       => 'vico-deposit-and-installment',
						'menu_slug'  => 'vicodin_menu',
						'version'    => VICODIN_CONST['version'],
						'survey_url' => 'https://script.google.com/macros/s/AKfycbxCadAI0khct5tqhGMvp1kGqVOtHH05iwOqrbPyJcjGWiQiKv-64FL7-VpWbO0bPUU7/exec'
					)
				);
			}
		}

		public function load_classes() {
			require_once VICODIN_CONST['plugin_dir'] . 'inc/functions.php';
			Enqueue::instance();
			Deposit_Order_Type::instance();
			Deposit_Backend::instance();
			Deposit_Front_End::instance();
			Deposit_Admin::instance();
			$this->support();
		}

	}

	new WP_Vico_Deposit();
}



