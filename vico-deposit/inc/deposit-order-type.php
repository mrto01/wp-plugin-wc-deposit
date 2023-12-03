<?php

namespace VicoDIn\Inc;

defined( 'ABSPATH' ) || exit;

class Deposit_Order_Type {

	static $instance = null;

	protected  $order_type;

	protected  $screen_id;

	public function __construct() {
		$this->set_suborder_options();
		add_action( 'init', array( $this, 'register_order_type' ) );
		add_action( 'init', array( $this, 'register_order_status' ) );
		if ( vicodin_check_wc_active() ) {
			add_action( 'load-' . $this->screen_id, array( $this, 'vicodin_handle_load_page_action' ), 9 );
			add_filter('wc_order_statuses', array( $this, 'add_custom_order_status_to_order_statuses' ) );
			add_filter( 'woocommerce_' . $this->order_type . '_list_table_columns', array( $this, 'vicodin_partial_order_columns' ));
			add_filter( 'woocommerce_' . $this->order_type . '_list_table_order_css_classes', array( $this, 'vicodin_partial_order_row'), 10, 2 );
			add_action( 'manage_' . $this->screen_id . '_custom_column', array( $this, 'vicodin_render_column'), 10, 2 );
			add_filter( 'woocommerce_' . $this->order_type . '_list_table_sortable_columns', array( $this, 'vicodin_table_sortable_columns' ) );
			add_filter( 'woocommerce_' . $this->order_type . '_list_table_prepare_items_query_args', array( $this, 'vicodin_prepare_items_query_args') );
		}
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function set_suborder_options() {
		$this->order_type = VICODIN_CONST['order_type'];
		$this->screen_id = 'woocommerce_page_wc-orders--' . $this->order_type;
	}
	function register_order_type(){

		$post_type = VICODIN_CONST['order_type'];
		if ( ! function_exists( 'wc_register_order_type' ) ) {
			return;
		}
		wc_register_order_type(
			$post_type,
			array(
				'labels'                           => array(
					'name'               => __( 'Suborders', 'vico-deposit-and-installment' ),
					'singular_name'      => _x( 'Suborder', 'shop_order post type singular name', 'vico-deposit-and-installment' ),
					'edit'               => __( 'Edit', 'vico-deposit-and-installment' ),
					'edit_item'          => __( 'Edit suborder', 'vico-deposit-and-installment' ),
					'view_item'          => __( 'View suborder', 'vico-deposit-and-installment' ),
					'search_items'       => __( 'Search suborders', 'vico-deposit-and-installment' ),
					'not_found'          => __( 'No suborders found', 'vico-deposit-and-installment' ),
					'not_found_in_trash' => __( 'No suborders found in trash', 'vico-deposit-and-installment' ),
					'parent'             => __( 'Orders', 'vico-deposit-and-installment' ),
					'menu_name'          => _x( 'Suborders', 'Admin menu name', 'vico-deposit-and-installment' ),
					'filter_items_list'  => __( 'Filter suborders', 'vico-deposit-and-installment' ),
					'add_new'            => __( 'prevent_create_suborder', 'vico-deposit-and-installment')
				),
				'public'                           => false,
				'show_ui'                          => true,
				'capability_type'                  => 'shop_order',
				'capabilities'                     => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'                     => true,
				'publicly_queryable'               => false,
				'exclude_from_search'              => true,
				'show_in_menu'                     => 'woocommerce',
				'hierarchical'                     => false,
				'show_in_nav_menus'                => false,
				'rewrite'                          => false,
				'query_var'                        => false,
				'supports'                         => array('title', 'comments', 'custom-fields'),
				'has_archive'                      => false,
				'exclude_from_orders_screen'       => true,
				'add_order_meta_boxes'             => true,
				'exclude_from_order_count'         => true,
				'exclude_from_order_views'         => true,
				'exclude_from_order_webhooks'      => true,
				'exclude_from_order_reports'       => true,
				'exclude_from_order_sales_reports' => true,
			)
		);




	}

	function register_order_status() {
		if ( ! function_exists( 'register_post_status' ) ) {
			return;
		}
		register_post_status('wc-installment', array(
			'label'                     => _x('Installment', 'Order status', 'vico-deposit-and-installment' ),
			'public'                    => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search'       => false,
			'label_count'               => _n_noop(
				'Installment <span class="count">(%s)</span>',
				'Installment <span class="count">(%s)</span>',
				'vico-deposit-and-installment' )
		));

	}

	function add_custom_order_status_to_order_statuses($order_statuses) {
		$new_order_statuses = array();

		foreach ( $order_statuses as $key => $label ) {
			$new_order_statuses[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-installment'] = __('Installment Plan', 'vico-deposit-and-installment');
			}
		}

		return $new_order_statuses;

	}

	public function  vicodin_handle_load_page_action() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'new' === $action ) {
			wp_die( esc_html__( 'Creating a new suborder is not supported.', 'vico-deposit-and-installment' ) );
		}else if ( 'edit' === $action ) {
			add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'vicodin_remove_suborder_action_buttons' ) );
		}
	}

	public function vicodin_partial_order_columns( $columns ) {
		$columns = array(
			'cb'                  => '<input type="checkbox" />',
			'order_number'        => esc_html__( 'Order', 'vico-deposit-and-installment' ),
			'parent_order_number' => esc_html__( 'Parent order', 'vico-deposit-and-installment' ),
			'order_due_date'      => esc_html__( 'Due date', 'vico-deposit-and-installment' ),
			'order_status'        => esc_html__( 'Status', 'vico-deposit-and-installment' ),
			'order_total'         => esc_html__( 'Total', 'vico-deposit-and-installment' ),
			'wc_actions'          => esc_html__( 'Actions', 'vico-deposit-and-installment' ),
		);
		return $columns;
	}

	public function vicodin_table_sortable_columns( $columns) {
		$columns['parent_order_number'] = 'ID';
		$columns['order_due_date']      = 'meta_value';

		return $columns;
	}

	public function vicodin_prepare_items_query_args( $order_query_args ) {
		if ( isset( $order_query_args['orderby'] ) && 'meta_value' === $order_query_args['orderby'] ) {
			$order_query_args['meta_key']     = '_vicodin_partial_payment_date';
			$order_query_args['post_status']  = array( 'wc-pending', 'wc-processing',  'wc-on-hold');
		}
		return $order_query_args;
	}
	public function vicodin_partial_order_row( $row, $order ) {
		$row = array(
			'order-' . $order->get_id(),
			'type-' . $order->get_type(),
			'status-' . $order->get_status(),
		);
		return $row;
	}

	public function vicodin_render_column( $column_id, $order) {
		if ( ! $order ) {
			return;
		}

		if ( is_callable( array( $this, 'render_' . $column_id . '_column' ) ) ) {
			call_user_func( array( $this, 'render_' . $column_id . '_column' ), $order );
		}
	}

	public function render_parent_order_number_column( Partial_Order $order ) {
		$parent_order = wc_get_order( $order->get_parent_id() );
		if ( $order->get_status() === 'trash' ) {
			echo '<strong>#' . esc_attr( $order->get_parent_id() ) . '</strong>';
		} else {
			echo '<a href="#" class="order-preview" data-order-id="' . absint( $parent_order->get_id() ) . '" title="' . esc_attr( __( 'Preview', 'woocommerce' ) ) . '">' . esc_html( __( 'Preview', 'woocommerce' ) ) . '</a>';
			echo '<a href="' . esc_url( $parent_order->get_edit_order_url() ) . '" class="order-view"><strong>#' . esc_attr( $parent_order->get_order_number() ) . '</strong></a>';
		}
	}

	public function render_order_due_date_column( Partial_Order $order ) {
		$order_due_date = $order->get_meta( '_vicodin_partial_payment_date' );
		$order_timestamp = $order_due_date ?? '';
		if ( ! $order_timestamp ) {
			echo '&ndash;';
			return;
		}

		// Check if the order was created within the last 24 hours, and not in the future.
		if ( strtotime( '+1 day', time() ) > $order_timestamp && time() <= $order_timestamp ) {
			$show_date = sprintf(
				_x( 'in %s', '%s = human-readable time difference', 'vico-deposit-and-installment' ),
				human_time_diff( time(), $order_timestamp )
			);
		} elseif ( date('Ymd', time() ) === date( 'Ymd', $order_timestamp ) ) {
			$show_date = __('today', 'vico-deposit-and-installment');
		}else {
			$show_date = date_i18n( wc_date_format(), $order_timestamp );
		}
		printf(
			'<time datetime="%1$s" title="%2$s">%3$s</time>',
			esc_attr( $order_timestamp ),
			esc_html( date_i18n( wc_date_format(), strtotime( $order_timestamp ) ) ),
			esc_html( $show_date )
		);
	}

//	Remove action button in suborder edit page

	public function vicodin_remove_suborder_action_buttons( $order ) {
		wp_enqueue_script( 'remove_action_buttons', VICODIN_CONST['dist_url'] . 'vicodin-inline-script.js', ['jquery'], VICODIN_CONST['version']);
		$inline_scripts = "jQuery(document).ready(function($) { $('.wc-order-bulk-actions').remove(); });";
		wp_add_inline_script('remove_action_buttons', $inline_scripts);
	}

}