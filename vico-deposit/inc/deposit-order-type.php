<?php

namespace VicoDIn\Inc;

defined( 'ABSPATH' ) || exit;

class Deposit_Order_Type {

	static $instance = null;

	public function __construct() {
		add_action( 'init', array( $this, 'register_order_type' ) );
		add_action( 'init', array( $this, 'register_order_status' ) );
		if ( vicodin_check_wc_active() ) {
			add_filter('wc_order_statuses', array( $this, 'add_custom_order_status_to_order_statuses' ) );
		}
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	function register_order_type(){

		$post_type = VICODIN_CONST['order_type'];
		if ( !function_exists( 'wc_register_order_type' ) ) {
			return;
		}
		wc_register_order_type(
			$post_type,
			array(
				'labels' => array(
					'name' => esc_html__('Suborders', 'vico-deposit-and-installment'),
					'singular_name' => esc_html__('Suborder', 'vico-deposit-and-installment'),
					'edit_item' => _x('Edit payment', 'custom post type setting', 'vico-deposit-and-installment'),
					'search_items' => esc_html__('Search orders', 'vico-deposit-and-installment'),
					'parent' => _x('Order', 'custom post type setting', 'vico-deposit-and-installment'),
					'menu_name' => esc_html__('Suborders', 'vico-deposit-and-installment'),
				),
				'public' => false,
				'show_ui' => true,
				'capability_type' => 'shop_order',
				'capabilities' => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap' => true,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'show_in_menu' =>  true,
				'hierarchical' => false,
				'show_in_nav_menus' => false,
				'rewrite' => false,
				'query_var' => false,
				'supports' => array('title', 'comments', 'custom-fields'),
				'has_archive' => false,
				'exclude_from_orders_screen' => true,
				'add_order_meta_boxes' => true,
				'exclude_from_order_count' => true,
				'exclude_from_order_views' => true,
				'exclude_from_order_webhooks' => true,
				'exclude_from_order_reports' => true,
				'exclude_from_order_sales_reports' => true,
				'class_name' => 'VicoDIn\Inc\Partial_Order',
			)

		);




	}

	function register_order_status() {
		if ( !function_exists( 'register_post_status' ) ){
			return;
		}
		register_post_status('wc-installment', array(
			'label' => _x('Installment', 'Order status', 'vico-deposit-and-installment' ),
			'public' => true,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search' => false,
			'label_count' => _n_noop('Installment <span class="count">(%s)</span>',
				'Installment <span class="count">(%s)</span>', 'vico-deposit-and-installment' )
		));

	}

	function add_custom_order_status_to_order_statuses($order_statuses) {
		$new_order_statuses = array();

		foreach ($order_statuses as $key => $label) {
			$new_order_statuses[$key] = $label;
			if ('wc-processing' === $key) {
				$new_order_statuses['wc-installment'] = __('Installment Plan', 'woocommerce');
			}
		}

		return $new_order_statuses;

	}

}