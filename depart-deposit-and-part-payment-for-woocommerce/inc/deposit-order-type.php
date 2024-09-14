<?php

namespace VicoDIn\Inc;

class Deposit_Order_Type {
    
    static $instance = null;
    
    protected $order_type;
    
    protected $screen_id;
    
    public function __construct() {
        $this->set_suborder_options();
        add_action( 'init', array( $this, 'register_order_type' ) );
        add_action( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_statuses' ) );
        add_action( 'current_screen', array( $this, 'depart_setup_screen' ) );
        add_action( 'load-' . $this->screen_id, array( $this, 'depart_handle_load_page_action' ), 9 );
        add_filter( 'woocommerce_admin_order_should_render_refunds', array( $this, 'depart_handle_render_refunds' ), 10, 2 );
        add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_status_to_order_statuses' ) );
        add_filter( 'woocommerce_' . $this->order_type . '_list_table_columns', array( $this, 'depart_partial_order_columns' ) );
        add_filter( 'woocommerce_' . $this->order_type . '_list_table_order_css_classes', array( $this, 'depart_partial_order_row' ), 10, 2 );
        add_action( 'manage_' . $this->screen_id . '_custom_column', array( $this, 'depart_render_column' ), 10, 2 );
        add_filter( 'woocommerce_' . $this->order_type . '_list_table_sortable_columns', array( $this, 'depart_table_sortable_columns' ) );
        add_filter( 'woocommerce_' . $this->order_type . '_list_table_prepare_items_query_args', array( $this, 'depart_prepare_items_query_args' ) );
        add_filter( 'bulk_actions-' . $this->screen_id, array( $this, 'depart_restrict_bulk_actions' ) );
        add_filter( 'bulk_actions-edit-' . $this->screen_id, array( $this, 'depart_restrict_bulk_actions' ) );
        add_filter( 'woocommerce_' . $this->order_type . '_list_table_should_render_blank_state', array( $this, 'depart_list_table_should_render_blank_state') );
    }
    
    public static function instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function depart_list_table_should_render_blank_state() {
        return false;
    }
    
    public function set_suborder_options() {
        $this->order_type = DEPART_CONST['order_type'];
        if ( depart_check_woocommerce_cot() ) {
            $this->screen_id = 'woocommerce_page_wc-orders--' . $this->order_type;
        } else {
            $this->screen_id = $this->order_type;
        }
    }
    
    function register_order_type() {
        $post_type = DEPART_CONST['order_type'];
        if ( ! function_exists( 'wc_register_order_type' ) ) {
            return;
        }
        wc_register_order_type( $post_type, array(
            'labels'                           => array(
                'name'               => __( 'Suborders', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'singular_name'      => _x( 'Suborder', 'shop_order post type singular name', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'edit'               => __( 'Edit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'edit_item'          => __( 'Edit suborder', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'view_item'          => __( 'View suborder', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'search_items'       => __( 'Search suborders', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'not_found'          => __( 'No suborders found', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'not_found_in_trash' => __( 'No suborders found in trash', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'parent'             => __( 'Orders', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'menu_name'          => _x( 'Suborders', 'Admin menu name', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'filter_items_list'  => __( 'Filter suborders', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'add_new'            => __( 'prevent_create_suborder', 'depart-deposit-and-part-payment-for-woocommerce' ),
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
            'supports'                         => array( 'title', 'comments', 'custom-fields' ),
            'has_archive'                      => false,
            'exclude_from_orders_screen'       => true,
            'add_order_meta_boxes'             => true,
            'exclude_from_order_count'         => true,
            'exclude_from_order_views'         => true,
            'exclude_from_order_webhooks'      => true,
            'exclude_from_order_reports'       => true,
            'exclude_from_order_sales_reports' => true,
        ) );
    }
    
    function register_order_statuses( $post_statuses ) {
        $post_statuses['wc-installment'] = array(
            'label'                     => _x( 'Installment', 'Order status', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'exclude_from_search'       => false,
        );
        
        $post_statuses['wc-overdue'] = array(
            'label'                     => _x( 'Overdue', 'Order status', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'exclude_from_search'       => false,
        );
        
        return $post_statuses;
    }
    
    function add_custom_order_status_to_order_statuses( $order_statuses ) {
        $new_order_statuses = array();
        
        foreach ( $order_statuses as $key => $label ) {
            $new_order_statuses[ $key ] = $label;
            if ( 'wc-processing' === $key ) {
                $new_order_statuses['wc-installment'] = __( 'Installment Plan', 'depart-deposit-and-part-payment-for-woocommerce' );
                $new_order_statuses['wc-overdue']     = __( 'Overdue', 'depart-deposit-and-part-payment-for-woocommerce' );
            }
        }
        
        return $new_order_statuses;
    }
    
    public function depart_handle_load_page_action() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'new' === $action ) {
            wp_die( esc_html__( 'Creating a new suborder is not supported.', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
    }
    
    public function depart_setup_screen() {
        global $depart_list_table;
        
        $screen_id = false;
        
        if ( function_exists( 'get_current_screen' ) ) {
            $screen    = get_current_screen();
            $screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
        }
        
        switch ( $screen_id ) {
            case 'edit-' . $this->screen_id:
                $depart_list_table = new List_Table_Suborder();
                break;
        }
        
        // Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
        remove_action( 'current_screen', array( $this, 'setup_screen' ) );
        remove_action( 'check_ajax_referer', array( $this, 'setup_screen' ) );
    }
    
    public function depart_partial_order_columns( $columns ) {
        $columns = array(
            'cb'                  => '<input type="checkbox" />',
            'order_number'        => esc_html__( 'Order', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'parent_order_number' => esc_html__( 'Parent order', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'order_due_date'      => esc_html__( 'Due date', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'order_status'        => esc_html__( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'order_total'         => esc_html__( 'Total', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'wc_actions'          => esc_html__( 'Actions', 'depart-deposit-and-part-payment-for-woocommerce' ),
        );
        
        return $columns;
    }
    
    public function depart_table_sortable_columns( $columns ) {
        $columns['parent_order_number'] = 'ID';
        $columns['order_due_date']      = 'meta_value';
        
        return $columns;
    }
    
    public function depart_prepare_items_query_args( $order_query_args ) {
        if ( isset( $order_query_args['orderby'] ) && 'meta_value' === $order_query_args['orderby'] ) {
            $order_query_args['meta_key'] = '_depart_partial_payment_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        }
        
        return $order_query_args;
    }
    
    public function depart_partial_order_row( $row, $order ) {
        $row = array(
            'order-' . $order->get_id(),
            'type-' . $order->get_type(),
            'status-' . $order->get_status(),
        );
        
        return $row;
    }
    
    public function depart_render_column( $column_id, $order ) {
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
            printf( '<strong>#' . esc_attr( $order->get_parent_id() ) . '</strong>' );
        } else {
            printf( '<a href="#" class="order-preview" data-order-id="' . absint( $parent_order->get_id() ) . '" title="' . esc_attr( __( 'Preview', 'depart-deposit-and-part-payment-for-woocommerce' ) ) . '">' . esc_html( __( 'Preview', 'depart-deposit-and-part-payment-for-woocommerce' ) ) . '</a>' );
            printf( '<a href="' . esc_url( $parent_order->get_edit_order_url() ) . '" class="order-view"><strong>#' . esc_attr( $parent_order->get_order_number() ) . '</strong></a>' );
        }
    }
    
    public function render_order_due_date_column( Partial_Order $order ) {
        $order_due_date  = $order->get_meta( '_depart_partial_payment_date' );
        $order_timestamp = $order_due_date ?? '';
        if ( ! $order_timestamp ) {
            echo '&ndash;';
            
            return;
        }
        
        // Check if the order due date is within the last 24 hours .
        if ( strtotime( '+1 day', time() ) > $order_timestamp && time() <= $order_timestamp ) {
            /* translators: %s: Order due date*/
            $show_date = sprintf( _x( 'in %s', '%s = human-readable time difference', 'depart-deposit-and-part-payment-for-woocommerce' ), human_time_diff( time(), $order_timestamp ) );
        } elseif ( gmdate( 'Ymd', time() ) === gmdate( 'Ymd', $order_timestamp ) ) {
            $show_date = __( 'today', 'depart-deposit-and-part-payment-for-woocommerce' );
        } else {
            $show_date = date_i18n( wc_date_format(), $order_timestamp );
        }
        printf( '<time datetime="%1$s" title="%2$s">%3$s</time>', esc_attr( $order_timestamp ), esc_html( date_i18n( wc_date_format(), strtotime( $order_timestamp ) ) ), esc_html( $show_date ) );
    }
    
    public function depart_handle_render_refunds( $order_id, $order ) {
        $order = wc_get_order( $order );
        
        if ( $order && $order->get_type() === $this->order_type ) {
            return false;
        }
        $render_refunds = 0 < $order->get_total() - $order->get_total_refunded() || 0 < absint( $order->get_item_count() - $order->get_item_count_refunded() );
        
        return $render_refunds;
    }
    
    public function depart_restrict_bulk_actions( $actions ) {
        unset( $actions['trash'] );
        
        return $actions;
    }
    
}