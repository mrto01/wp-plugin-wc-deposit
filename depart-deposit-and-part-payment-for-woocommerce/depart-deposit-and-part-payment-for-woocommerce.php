<?php

/**
 * Plugin Name: DEPART - Deposit and Part payment for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/woo-deposit-installment/
 * Description: Empower customers with flexible payment options. Allow partial payments for products or services. Boost conversions and customer satisfaction.
 * Version: 1.0.0
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * Text Domain: depart-deposit-and-part-payment-for-woocommerce
 * Domain Path: /languages
 * Copyright 2023 - 2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * Requires PHP: 7.0
 **/

namespace VicoDIn;

use VicoDIn\Admin\Deposit_Admin;
use VicoDIn\Admin\Enqueue;
use VicoDIn\Inc\Compatible\Viwec\VIWEC_Template;
use VicoDIn\Inc\Data;
use VicoDIn\Inc\Deposit_Backend;
use VicoDIn\Inc\Deposit_Block;
use VicoDIn\Inc\Deposit_Front_End;
use VicoDIn\Inc\Deposit_Order_Type;
use VicoDIn\Inc\Schedule_Checker;
use WP_Error;

defined( 'ABSPATH' ) || exit;

define( 'DEPART_CONST', [
    'version'     => '1.0.0',
    'plugin_name' => 'DEPART - Deposit and Part payment for WooCommerce',
    'slug'        => 'vicodin',
    'assets_slug' => 'depart-',
    'file'        => __FILE__,
    'basename'    => plugin_basename( __FILE__ ),
    'plugin_dir'  => untrailingslashit( plugin_dir_path( __FILE__ ) ),
    'dist_url'    => plugins_url( 'assets/dist/', __FILE__ ),
    'img_url'     => plugins_url( 'assets/img/', __FILE__ ),
    'libs_url'    => plugins_url( 'assets/libs/', __FILE__ ),
    'order_type'  => 'depart_partial_order',
] );

require_once DEPART_CONST['plugin_dir'] . '/autoload.php';

if ( ! class_exists( 'DEPART' ) ) {
    class DEPART {
        
        protected $checker;
        
        public function __construct() {
            add_action( 'plugins_loaded', array( $this, 'check_environment' ) );
            add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
            register_activation_hook( __FILE__, array( $this, 'depart_activate' ) );
            register_deactivation_hook( __FILE__, array( $this, 'depart_deactivate' ) );
        }
        
        public function before_woocommerce_init() {
            //compatible with 'High-Performance order storage (COT)'
            if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
            }
        }
        
        public function check_environment() {
            if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
                require_once DEPART_CONST['plugin_dir'] . '/inc/support/support.php';
            }
            
            $environment = new \VillaTheme_Require_Environment( [
                'plugin_name'     => 'Vico deposit and installment for WooCommerce',
                'php_version'     => '7.0',
                'wp_version'      => '5.0',
                'wc_version'      => '7.0',
                'require_plugins' => [
                    [
                        'slug' => 'woocommerce',
                        'name' => 'WooCommerce',
                    ],
                ],
            ] );
            if ( $environment->has_error() ) {
                return;
            }
            
            add_action( 'admin_notices', array( $this, 'plugin_require_notices' ) );
            add_filter( 'plugin_action_links_' . DEPART_CONST['basename'], array( $this, 'setting_link' ) );
            require_once DEPART_CONST['plugin_dir'] . '/inc/support/support.php';
            $this->load_text_domain();
            $this->load_classes();
        }
        
        public function depart_activate() {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                Schedule_Checker::schedule_order_check_event();
            }
        }
        
        public function depart_deactivate() {
            Schedule_Checker::remove_schedule();
        }
        
        public function setting_link( $links ) {
            return array_merge( $links, [
                sprintf( "<a href='%1s' >%2s</a>", esc_url( admin_url( 'admin.php?page=depart_setting' ) ), esc_html__( 'Settings', 'depart-deposit-and-part-payment-for-woocommerce' ) ),
            ] );
        }
        
        public function load_text_domain() {
            load_plugin_textdomain( 'depart-deposit-and-part-payment-for-woocommerce', false, DEPART_CONST['basename'] . '/languages' );
        }
        
        public function plugin_require_notices() {
            if ( ! $this->checker instanceof WP_Error || ! $this->checker->has_errors() ) {
                return;
            }
            
            $messages = $this->checker->get_error_messages();
            foreach ( $messages as $message ) {
                echo sprintf( "<div id='message' class='error'><p>%s %s</p></div>", esc_html( DEPART_CONST['plugin_name'] ), wp_kses_post( $message ) );
            }
        }
        
        public function support() {
            if ( class_exists( 'VillaTheme_Support_Pro' ) ) {
                new \VillaTheme_Support_Pro( array(
                    'support'    => 'https://wordpress.org/support/plugin/',
                    'docs'       => 'http://docs.villatheme.com/',
                    'review'     => 'https://wordpress.org/support/plugin/',
                    'css'        => DEPART_CONST['dist_url'],
                    'image'      => DEPART_CONST['img_url'],
                    'slug'       => 'depart-deposit-and-part-payment-for-woocommerce',
                    'menu_slug'  => 'depart_menu',
                    'version'    => DEPART_CONST['version'],
                    'survey_url' => 'https://script.google.com/macros/s/AKfycbxCadAI0khct5tqhGMvp1kGqVOtHH05iwOqrbPyJcjGWiQiKv-64FL7-VpWbO0bPUU7/exec',
                ) );
            }
        }
        
        public function load_classes() {
            require_once DEPART_CONST['plugin_dir'] . '/inc/functions.php';
            require_once DEPART_CONST['plugin_dir'] . '/inc/support/check_update.php';
            require_once DEPART_CONST['plugin_dir'] . '/inc/support/update.php';
            Data::load();
            Enqueue::instance();
            Deposit_Order_Type::instance();
            Deposit_Backend::instance();
            Deposit_Front_End::instance();
            Schedule_Checker::instance();
            Deposit_Admin::instance();
            Deposit_Block::instance();
            VIWEC_Template::instance();
            $this->support();
        }
        
    }
    
    new DEPART();
}



