<?php

namespace VicoDIn\Inc;

use VicoDIn\Inc\Emails\Email_Admin_Full_Payment;
use VicoDIn\Inc\Emails\Email_Admin_Partial_paid;
use VicoDIn\Inc\Emails\Email_Deposit_Paid;
use VicoDIn\Inc\Emails\Email_Full_Payment;
use VicoDIn\Inc\Emails\Email_Partial_paid;
use VicoDIn\Inc\Emails\Email_Payment_Reminder;

defined( 'ABSPATH' ) || exit;

class Deposit_Backend {
    
    private $data_store;
    
    public $slug;
    
    public $dist_url;
    
    public $deposit_type;
    
    public $template_url;
    
    protected static $instance = null;
    
    public $delete_enable = false;
    
    public function __construct() {
        $this->data_store   = Data::load();
        $this->slug         = DEPART_CONST['slug'];
        $this->dist_url     = DEPART_CONST['dist_url'];
        $this->deposit_type = DEPART_CONST['order_type'];
        $this->template_url = DEPART_CONST['plugin_dir'] . '/templates/';
        add_filter( 'woocommerce_email_classes', [ $this, 'depart_email_classes' ] );
        
        // In product page
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'woocommerce_deposit_tab' ] );
        add_filter( 'woocommerce_product_data_panels', [ $this, 'woocommerce_deposit_tab_content' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'depart_process_product_meta' ] );
        
        // In order lists page
        add_filter( 'woocommerce_get_formatted_order_total', [ $this, 'depart_add_paid_amount' ], 10, 2 );
        add_action( 'woocommerce_order_list_table_extra_tablenav', [ $this, 'depart_add_deposit_order_filter' ] );
        add_action( 'manage_posts_extra_tablenav', [ $this, 'depart_add_deposit_order_filter' ] );
        add_filter( 'request', [ $this, 'depart_request_query' ] );
        add_filter( 'woocommerce_order_list_table_prepare_items_query_args', [ $this, 'depart_request_query' ] );
        
        // Add email reminder column in Orders page
        if ( $this->data_store->get_setting( 'show_email_column' ) ) {
            add_filter( 'manage_shop_order_posts_columns', [ $this, 'depart_define_shop_order_custom_columns' ], 99 );
            add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'depart_define_shop_order_custom_columns' ], 99 );
            add_action( 'manage_shop_order_posts_custom_column', [ $this, 'depart_render_shop_order_custom_column' ], 10, 2 );
            add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'depart_render_shop_order_custom_column' ], 10, 2 );
        }
        
        // Add email reminder column in Suborder page
        add_filter( 'manage_' . $this->deposit_type . '_posts_columns', [ $this, 'depart_define_shop_order_custom_columns' ], 99 );
        add_filter( 'woocommerce_' . $this->deposit_type . '_list_table_columns', [ $this, 'depart_define_shop_order_custom_columns' ], 99 );
        add_action( 'manage_' . $this->deposit_type . '_posts_custom_column', [ $this, 'depart_render_shop_order_custom_column' ], 10, 2 );
        add_action( 'woocommerce_' . $this->deposit_type . '_list_table_custom_column', [ $this, 'depart_render_shop_order_custom_column' ], 10, 2 );
        add_action( 'wp_ajax_depart_send_reminder_email', [ $this, 'depart_send_reminder_email' ] );
        
        //In order edit page
        add_filter( 'admin_body_class', [ $this, 'depart_admin_body_class' ] );
        add_action( 'add_meta_boxes', [ $this, 'depart_partial_payments_metabox' ], 31, 2 );
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'depart_process_shop_order_meta' ] );
        add_action( 'wp_ajax_depart_get_new_plan_template', [ $this, 'get_new_plan_template' ] );
        add_action( 'wp_ajax_depart_save_custom_plans', [ $this, 'save_custom_plans' ] );
        add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'depart_admin_order_totals_after_total' ] );
        add_action( 'woocommerce_order_after_calculate_totals', [ $this, 'depart_recalculate_totals' ], 11, 2 );
        add_action( 'wp_ajax_depart_reload_payment_meta_box', [ $this, 'depart_reload_payment_meta_box' ] );
        add_filter( 'woocommerce_after_order_itemmeta', [ $this, 'depart_deposit_order_metadata' ], 10, 3 );
        add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'depart_add_recalculate_deposit_button' ] );
        add_action( 'wp_ajax_wdp_get_recalculate_deposit_modal', [ $this, 'get_recalculate_deposit_modal' ] );
        add_action( 'wp_ajax_wdp_save_deposit_data', [ $this, 'save_deposit_data' ] );
        
        // Handle suborders' statuses
        add_action( 'woocommerce_before_delete_order', [ $this, 'depart_before_delete_partial_orders' ], 9, 2 );
        add_action( 'before_delete_post', [ $this, 'depart_before_delete_partial_orders' ], 9, 2 );
        add_action( 'woocommerce_trash_order', [ $this, 'depart_trash_partial_orders' ] );
        add_action( 'wp_trash_post', [ $this, 'depart_trash_partial_orders' ] );
        add_action( 'woocommerce_untrash_order', [ $this, 'depart_untrash_partial_orders' ], 10, 2 );
        add_action( 'untrash_post', [ $this, 'depart_untrash_partial_orders' ], 10, 2 );
        add_action( 'woocommerce_before_trash_order', [ $this, 'depart_prevent_user_trash_partial_orders' ], 10, 2 );
        add_filter( 'pre_trash_post', [ $this, 'depart_prevent_user_trash_partial_orders' ], 10, 2 );
    }
    
    public static function instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function depart_email_classes( $emails ) {
        $emails['depart_email_deposit_payment_received'] = new Email_Deposit_Paid();
        $emails['depart_email_partial_payment_received'] = new Email_Partial_paid();
        $emails['depart_email_partial_payment']          = new Email_Admin_Partial_paid();
        $emails['depart_email_full_payment']             = new Email_Admin_Full_Payment();
        $emails['depart_email_full_payment_received']    = new Email_Full_Payment();
        $emails['depart_email_payment_reminder']         = new Email_Payment_Reminder();
        
        return $emails;
    }
    
    public function woocommerce_deposit_tab( $tabs ) {
        $tabs['depart_deposit'] = [
            'label'    => __( 'Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'target'   => 'depart_deposits_tab_data',
            'class'    => [],
            'priority' => 50,
        ];
        
        return $tabs;
    }
    
    public function woocommerce_deposit_tab_content() {
        if ( $this->data_store->get_setting( 'enabled' ) ) {
            global $post;
            $product        = wc_get_product( $post->ID );
            $plans          = get_option( 'depart_payment_plan' );
            $exists_plans   = $product->get_meta( 'depart_exists_plans' );
            $plan_ids_saved = [];
            if ( is_array( $exists_plans ) && ! empty( $exists_plans ) ) {
                foreach ( $exists_plans as $plan ) {
                    $plan_ids_saved[] = $plan['plan_id'];
                }
            }
            $deposit_disable = $product->get_meta( 'depart_deposit_disabled' );
            $force_deposit   = $product->get_meta( 'depart_force_deposit' );
            $deposit_type    = empty( $product->get_meta( 'depart_deposit_type' ) ) ? 'global' : $product->get_meta( 'depart_deposit_type' );
            foreach ( $plans as $plan ) {
                $plan_options[ $plan['plan_id'] ] = $plan['plan_name'];
            }
            ?>
            <div id="depart_deposits_tab_data" class="panel hidden">
                <div class="woocommerce_options_panel">
                    <div class="options_group">
                        <?php
                        echo wp_kses_post( wp_nonce_field( 'depart_nonce', '_depart_nonce', false ) );
                        
                        woocommerce_wp_checkbox( [
                            'id'            => 'depart_deposit_disabled',
                            'name'          => 'depart_deposit_disabled',
                            'label'         => esc_html__( 'Disable Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            'description'   => esc_html__( 'Disable deposit feature for this product', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            'wrapper_class' => 'form-row form-row-full',
                            'cbvalue'       => 'yes',
                            'value'         => $deposit_disable,
                        ] );
                        
                        woocommerce_wp_checkbox( [
                            'id'            => 'depart_force_deposit',
                            'name'          => 'depart_force_deposit',
                            'label'         => esc_html__( 'Force deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            'description'   => esc_html__( 'Customers must buy this product by making a deposit instead of paying the full price.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            'wrapper_class' => 'form-row form-row-full',
                            'cbvalue'       => 'yes',
                            'value'         => $force_deposit,
                        ] );
                        
                        woocommerce_wp_select( [
                            'id'            => 'depart_deposit_type',
                            'label'         => esc_html__( 'Deposit type', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            'options'       => [
                                'global' => esc_html__( 'Global', 'depart-deposit-and-part-payment-for-woocommerce' ),
                                'custom' => esc_html__( 'Custom', 'depart-deposit-and-part-payment-for-woocommerce' ),
                            ],
                            'wrapper_class' => 'form-row form-row-full',
                            'value'         => $deposit_type,
                        ] );
                        
                        ?>
                    </div>
                </div>
                <div class="wc-metaboxes-wrapper depart-loader <?php echo ( 'global' === $deposit_type ) ? 'hidden' : '' ?>">
                    <div class="toolbar toolbar-top">
                        <div class="actions">
                            <span class="button depart-new-custom-plan"><?php esc_html_e( 'New custom plan', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></span>
                            <select name="depart_deposit_plan" multiple id="depart_deposit_plan">
                                <?php foreach ( $plan_options as $plan_id => $plan_name ) { ?>
                                    <option value="<?php echo esc_attr( $plan_id ); ?>" <?php echo in_array( $plan_id, $plan_ids_saved ) ? 'selected' : '' ?>><?php echo esc_html( $plan_name ); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="wc-metaboxes depart-metaboxes">
                        <?php
                        $custom_plans = get_post_meta( $product->get_id(), 'depart_custom_plans', true );
                        if ( ! empty( $custom_plans ) ) {
                            foreach ( $custom_plans as $custom_plan ) {
                                wc_get_template( 'plan/custom-plan-template.php', [ 'custom_plan' => $custom_plan ], '', $this->template_url );
                            }
                        }
                        ?>
                    </div>
                    <div class="toolbar">
                        <span class="button depart-save-custom-plan button-primary"><?php esc_html_e( 'Save plans', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></span>
                    </div>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div id="depart_deposits_tab_data" class="woocommerce_options_panel hidden">
                <div class="options_group">
                    <h3><?php echo esc_html__( 'Deposit Disabled', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Please enable the deposit option from our ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                        <a class="depart-setting-link"
                           href="<?php echo esc_url( admin_url( 'admin.php?page=depart_setting' ) ); ?>"
                           target="_blank">
                            settings
                        </a>
                        page.
                    </p>
                </div>
            </div>
            <?php
        }
    }
    
    public function get_new_plan_template() {
        if ( ! ( isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'depart_nonce' ) ) ) {
            wp_die();
        }
        $custom_plan = [
            'plan_name'     => __( 'New custom plan', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'unit-type'     => 'percentage',
            'deposit'       => '',
            'deposit_fee'   => '',
            'duration'      => '1 Months',
            'total'         => 0,
            'plan_schedule' => [
                [
                    'partial'   => '',
                    'after'     => 1,
                    'date_type' => 'month',
                    'fee'       => '',
                ],
            ],
        ];
        wc_get_template( 'plan/custom-plan-template.php', [ 'custom_plan' => $custom_plan ], '', $this->template_url );
        wp_die();
    }
    
    public function save_custom_plans() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'], $_POST['post_id'], $_POST['depart_deposit_disabled'], $_POST['depart_deposit_type'], $_POST['depart_force_deposit'] ) )
             && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) {
            return;
        }
        $data          = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $data          = json_decode( $data, true );
        $post_id       = sanitize_key( ( $_POST['post_id'] ) );
        $disable       = sanitize_text_field( wp_unslash( $_POST['depart_deposit_disabled'] ) );
        $deposit_type  = sanitize_text_field( wp_unslash( $_POST['depart_deposit_type'] ) );
        $force_deposit = sanitize_text_field( wp_unslash( $_POST['depart_force_deposit'] ) );
        
        $exists_plan_ids = [];
        
        if ( isset ( $_POST['exists_plans'] ) ) {
            $exists_plan_ids = json_decode( wp_unslash( $_POST['exists_plans'] ), true );
            $exists_plan_ids = array_map( 'sanitize_key', $exists_plan_ids );
        }
        
        $plans        = get_option( 'depart_payment_plan' );
        $exists_plans = [];
        $count_id     = count( $data );
        foreach ( $exists_plan_ids as $plan_id ) {
            if ( isset( $plans[ $plan_id ] ) ) {
                $exists_plans[ $count_id ] = $plans[ $plan_id ];
            }
            $count_id ++;
        }
        
        update_post_meta( $post_id, 'depart_custom_plans', $data );
        update_post_meta( $post_id, 'depart_exists_plans', $exists_plans );
        update_post_meta( $post_id, 'depart_deposit_disabled', $disable );
        update_post_meta( $post_id, 'depart_deposit_type', $deposit_type );
        update_post_meta( $post_id, 'depart_force_deposit', $force_deposit );
        
        wp_send_json_success( 'save plan success!' );
        
        wp_die();
    }
    
    public function depart_process_product_meta( $post_id ) {
        if ( ! isset( $_POST['_depart_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_depart_nonce'] ), 'depart_nonce' ) ) {
            return;
        }
        $product = wc_get_product( $post_id );
        
        if ( $product ) {
            $disable       = isset( $_POST['depart_deposit_disabled'] ) ? sanitize_text_field( wp_unslash( $_POST['depart_deposit_disabled'] ) ) : 'no';
            $force_deposit = isset( $_POST['depart_force_deposit'] ) ? sanitize_text_field( wp_unslash( $_POST['depart_force_deposit'] ) ) : 'no';
            $type          = isset( $_POST['depart_deposit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['depart_deposit_type'] ) ) : 'global';
            $product->update_meta_data( 'depart_deposit_disabled', $disable );
            $product->update_meta_data( 'depart_deposit_type', $type );
            $product->update_meta_data( 'depart_force_deposit', $force_deposit );
            $product->save();
        }
    }
    
    public function depart_process_shop_order_meta( $post_id ) {
        if ( ! isset( $_POST['depart_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['depart_nonce'] ), 'depart_nonce' ) ) {
            return;
        }
        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }
        $old_status    = 'wc-' . $order->get_status();
        $completed_ids = [];
        if ( isset( $_POST['depart_partial_payment_completed'] ) ) {
            $completed_ids = array_map( 'sanitize_text_field', wp_unslash( $_POST['depart_partial_payment_completed'] ) );
        }
        $args = [
            'post_parent'     => $post_id,
            'parent_order_id' => $post_id,
            'post_type'       => $this->deposit_type,
            'numberposts'     => - 1,
            'orderby'         => 'ID',
            'order'           => 'ASC',
        ];
        
        $suborders = wc_get_orders( $args );
        if ( ! is_array( $completed_ids ) ) {
            $completed_ids = [];
        }
        if ( is_array( $suborders ) && ! empty( $suborders ) ) {
            
            /* Get the last unpaid suborder */
            $last_unpaid_suborder     = 0;
            $last_processing_suborder = 0;
            foreach( $suborders as $suborder ) {
                $id = $suborder->get_id();
                if ( in_array( $id, $completed_ids ) && ! $suborder->is_paid() ) {
                   $last_unpaid_suborder = $id;
                }
            }
            
            foreach ( $suborders as $suborder ) {
                $id = $suborder->get_id();
                if ( in_array( $id, $completed_ids ) ) {
                    if ( $suborder->has_status( 'completed' ) ) {
                        continue;
                    }
                    
                    /* We want to know if there is a processing suborder after last unpaid suborder*/
                    if ( $suborder->has_status('processing') ) {
                        $last_processing_suborder = $id;
                    }
                    
                    $suborder->set_status( 'completed' );
                    
                    
                    /* We want all suborder status changed before last unpaid suborder trigger send email */
                    if ( $last_unpaid_suborder == $id ) {
                        $last_unpaid_suborder = $suborder;
                        $suborder->save_without_status_transition();
                    }else {
                        $suborder->save();
                    }
                } else {
                    $is_paid = $suborder->get_payment_method_title();
                    if ( $is_paid ) {
                        if ( 'cod' === $suborder->get_payment_method() ) {
                            if ( $suborder->has_status( 'processing' ) ) {
                                continue;
                            }
                            $suborder->set_status( 'processing' );
                        } else {
                            if ( $suborder->has_status( 'on-hold' ) ) {
                                continue;
                            }
                            $suborder->set_status( 'on-hold' );
                        }
                    } else {
                        if ( $suborder->has_status( 'pending' ) ) {
                            continue;
                        }
                        $suborder->set_status( 'pending' );
                    }
                    $suborder->save();
                }
            }
            
            /* Trigger change to send email */
            if ( is_object( $last_unpaid_suborder ) ) {
                $last_unpaid_suborder->trigger_status_transition();
            }
        }
        
        $order      = wc_get_order( $post_id );
        $new_status = 'wc-' . $order->get_status();
        
        $post_status = isset( $_POST['order_status'] ) ? sanitize_text_field( wp_unslash( $_POST['order_status'] ) ) : '';
        
        /* Only change order status when do complete suborder from meta box*/
        if ( $old_status === $post_status ) {
            $_POST['order_status'] = $new_status;
        }
    }
    
    public function depart_add_paid_amount( $formatted_total, $order ) {
        $is_email = did_action( 'woocommerce_email_order_details' );
        /* Hide in template of woocommerce email customizer */
        if ( did_action( 'viwec_render_content' ) ) {
            return $formatted_total;
        }
        if ( is_admin() && ! $is_email ) {
            $is_deposit_order = $order->get_meta( '_depart_is_deposit_order' );
            
            if ( $is_deposit_order ) {
                $paid_amount     = $order->get_meta( '_depart_paid_amount' );
                $html            = '<div>' . __( 'Paid: ', 'depart-deposit-and-part-payment-for-woocommerce' ) . wc_price( $paid_amount ) . '</div>';
                $formatted_total .= $html;
            }
        }
        
        return $formatted_total;
    }
    
    function depart_admin_body_class( $classes ) {
        $current_screen = get_current_screen();
        if ( 'edit-' . $this->deposit_type == $current_screen->id ) {
            return "$classes post-type-shop_order";
        } else {
            return $classes;
        }
    }
    
    public function depart_add_deposit_order_filter( $order_type ) {
        global $typenow;
        
        if ( 'shop_order' === $typenow || 'shop_order' === $order_type ) {
            $current_url    = set_url_scheme( 'http://' . sanitize_text_field( $_SERVER['HTTP_HOST'] ) . sanitize_url( $_SERVER['REQUEST_URI'] ) );
            $current_url    = remove_query_arg( 'paged', $current_url );
            $filter_deposit = 'yes';
            
            $url = esc_url( add_query_arg( compact( 'filter_deposit' ), $current_url ) );
            $filter_button = sprintf( '<a href="%1$s" class="button apply">' . '%2$s' . '</a>', esc_url( $url ), esc_html__( 'Deposit order', 'depart-deposit-and-part-payment-for-woocommerce' ) );
            echo wp_kses_post( $filter_button );
        } elseif ( $this->deposit_type === $typenow || $this->deposit_type === $order_type ) {
            $current_url      = set_url_scheme( 'http://' . sanitize_text_field( $_SERVER['HTTP_HOST'] ) . sanitize_url( $_SERVER['REQUEST_URI'] ) );
            $current_url      = remove_query_arg( 'paged', $current_url );
            $filter_order_due = 'yes';
            
            $url           = esc_url( add_query_arg( compact( 'filter_order_due' ), $current_url ) );
            $filter_button = sprintf( '<a href="%1$s" class="button apply">' . '%2$s' . '</a>', esc_url( $url ), esc_html__( 'Due today', 'depart-deposit-and-part-payment-for-woocommerce' ) );
            echo wp_kses_post( $filter_button );
        }
    }
    
    public function depart_request_query( $query_vars ) {
        if ( isset( $_GET['filter_deposit'] ) && 'yes' === $_GET['filter_deposit'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_vars['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => '_depart_is_deposit_order',
                    'value' => '1',
                ],
            ];
        } elseif ( isset( $_GET['filter_order_due'] ) && 'yes' === $_GET['filter_order_due'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $date_start               = strtotime( gmdate( 'Ymd' ) . ' 00:00:00' );
            $date_end                 = strtotime( gmdate( 'Ymd' ) . ' 23:59:59' );
            $query_vars['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_depart_partial_payment_date',
                    'value'   => [ $date_start, $date_end ],
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ],
            ];
        }
        
        return $query_vars;
    }
    
    public function depart_partial_payments_metabox( $post_type, $post ) {
        $order             = ( $post instanceof \WP_Post ) ? wc_get_order( $post->ID ) : wc_get_order( get_the_id() );
        $main_order_screen = depart_check_woocommerce_cot() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
        $sub_order_screen  = depart_check_woocommerce_cot() ? 'woocommerce_page_wc-orders--' . $this->deposit_type : DEPART_CONST['order_type'];
        if ( $order ) {
            if ( $order->get_type() === DEPART_CONST['order_type'] ) {
                add_meta_box( 'depart_deposit_parent_order', esc_html__( 'Partial Payments', 'depart-deposit-and-part-payment-for-woocommerce' ), [
                    $this,
                    'depart_original_order_details',
                ], $sub_order_screen, 'side', 'high' );
            } else {
                $is_deposit = $order->get_meta( '_depart_is_deposit_order' ) ?? false;
                if ( $is_deposit ) {
                    add_meta_box( 'depart_deposit_partial_payments', esc_html__( 'Installment plan', 'depart-deposit-and-part-payment-for-woocommerce' ), [
                        $this,
                        'schedule_payments_summary',
                    ], $main_order_screen, 'normal', 'high' );
                }
            }
        }
    }
    
    public function schedule_payments_summary( $post ) {
        $order      = ( $post instanceof \WP_Post ) ? wc_get_order( $post->ID ) : wc_get_order( get_the_id() );
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        if ( $is_deposit ) {
            $schedule = depart_get_schedule_payments_summary( $order );
            wc_get_template( 'plan/schedule-payments-summary.php', [ 'schedule' => $schedule ], '', $this->template_url );
        }
    }
    
    public function depart_original_order_details() {
        $order = wc_get_order( get_the_id() );
        if ( $order ) {
            $parent = wc_get_order( $order->get_parent_id() );
            if ( $parent ) {
                ?>
                <p>
                    <?php /* translators: Parent order number*/ ?>
                    <?php echo wp_kses_post( sprintf( __( 'This is a partial payment for order %s', 'depart-deposit-and-part-payment-for-woocommerce' ), $parent->get_order_number() ) ); ?>
                </p>
                <a class="button btn"
                   href="<?php echo esc_url( $parent->get_edit_order_url() ); ?> "> <?php esc_html_e( 'View', 'depart-deposit-and-part-payment-for-woocommerce' ); ?> </a>
                <?php
            }
        }
    }
    
    public function depart_admin_order_totals_after_total( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order->get_type() == DEPART_CONST['order_type'] ) {
            return;
        }
        if ( $order->is_paid() ) {
            return;
        }
        
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        if ( $is_deposit ) {
            $total         = floatval( $order->get_meta( '_depart_total' ) );
            $fee_total     = floatval( $order->get_meta( '_depart_fee_total' ) );
            $paid_amount   = floatval( $order->get_meta( '_depart_paid_amount' ) );
            $unpaid_amount = abs($total + $fee_total - $paid_amount );
            ?>
            <?php
            if ( $fee_total > 0 ) {
                ?>
                <tr class="depart-fee">
                    <td class="label"><?php echo esc_html( depart_get_text_option( 'fees_text' ) ) ?>:</td>
                    <td width="1%"></td>
                    <td class="total balance"><?php echo wp_kses_post( wc_price( $fee_total, [ 'currency' => $order->get_currency() ] ) ); ?></td>
                </tr>
                <?php
            }
            ?>
            <tr class="depart-paid">
                <td class="label"><?php esc_html_e( 'Paid', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>:</td>
                <td width="1%"></td>
                <td class="total balance"><?php echo wp_kses_post( wc_price( $paid_amount, [ 'currency' => $order->get_currency() ] ) ); ?></td>
            </tr>
            <tr class="depart-unpaid">
                <td class="label"><?php esc_html_e( 'Remaining', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>:</td>
                <td width="1%"></td>
                <td class="total balance"><?php echo wp_kses_post( wc_price( $unpaid_amount, [ 'currency' => $order->get_currency() ] ) ); ?></td>
            </tr>
            <?php
        }
    }
    
    public function get_original_deposit_cart_total( \WC_Order $order ) {
        $cart_items = $order->get_items();
        if ( empty( $cart_items ) ) {
            return $order->get_subtotal();
        }
        
        $original_deposit_cart_total = 0;
        $fee_total                   = 0;
        foreach ( $cart_items as $cart_item ) {
            $cart_item_deposit_meta = $cart_item->get_meta( 'depart_deposit_meta' );
            $plan_id                = isset( $cart_item_deposit_meta['plan_id'] ) ? $cart_item_deposit_meta['plan_id'] : 0;
            $deposit_type           = isset( $cart_item_deposit_meta['deposit_type'] ) ? $cart_item_deposit_meta['deposit_type'] : 'global';
            if ( $this->update_deposit_data_to_order_item( $cart_item, $deposit_type, $plan_id ) ) {
                $cart_item->save();
            }
            if ( isset( $cart_item->get_meta( 'depart_deposit_meta' )['enable'] ) && $cart_item->get_meta( 'depart_deposit_meta' )['enable'] ) {
                $original_deposit_cart_total += $cart_item->get_meta( 'depart_deposit_meta' )['total'];
                $fee_total                   += $cart_item->get_meta( 'depart_deposit_meta' )['fee_total'];
            } else {
                $original_deposit_cart_total += $cart_item->get_total( 'edit' );
            }
        }
        $order->update_meta_data( '_depart_fee_total', $fee_total );
        $order->save();
        
        return $original_deposit_cart_total;
    }
    
    public function depart_recalculate_totals( $and_taxes, $order ) {
        /* Only calculate when recalculate button clicked */
        if ( ! did_action( 'wp_ajax_woocommerce_calc_line_taxes' ) ) {
            return;
        }
        
        /* If multi currency enable */
        $wmc_order_info = $order->get_meta( 'wmc_order_info' );
        $rate           = 1;
        $order_currency = $order->get_currency();
        
        if ( is_array( $wmc_order_info ) && ! empty( $wmc_order_info ) ) {
            if ( isset( $wmc_order_info[ $order_currency ]['rate'] ) ) {
                $rate = $wmc_order_info[ $order_currency ]['rate'];
            }
        }
        
        $order_items = $order->get_items();
        
        /* Remove deposit data if order is empty */
        if ( empty( $order_items ) ) {
            $this->recalculate_remove_deposit_data( $order );
        }
        
        $schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
        
        if ( is_array( $schedule ) && ! empty( $schedule ) ) {
            $unpaid_payments         = [];
            $unpaid_total            = 0.0;
            $paid_total              = 0.0;
            $suborder_original_total = 0.0;
            
            /* Recalculate deposit meta data */
            $order_original_total = floatval( $this->get_original_deposit_cart_total( $order ) );
            $fee_total            = wc_format_decimal( floatval( $order->get_meta( '_depart_fee_total' ) ), '' );
            $discount_total       = floatval( $order->get_discount_total() );
            $order_tax_item       = floatval( $order->get_total_tax( 'edit' ) );
            $order_fee_item       = $order->get_total_fees();
            $order_shipping_item  = floatval( $order->get_shipping_total() );
            $order_total          = wc_format_decimal( $order_original_total + $order_tax_item + $order_fee_item + $order_shipping_item - $discount_total, '' );
            foreach ( $schedule as $payment ) {
                $partial_order = wc_get_order( $payment['id'] );
                
                /* Convert all partial payments currency to parent order's currency */
                if ( is_plugin_active( 'woocommerce-multi-currency/woocommerce-multi-currency.php' ) ) {
                    $partial_order_currency = $partial_order->get_currency();
                    if ( $order_currency !== $partial_order_currency ) {
                        $partial_total = depart_wmc_convert_order_total_to_base_price( $partial_order ) * $rate;
                        foreach ( $partial_order->get_fees() as $item ) {
                            $item->set_total( $partial_total );
                            $item->save();
                        }
                        $partial_order->calculate_totals( false );
                        $partial_order->set_currency( $order_currency );
                        $partial_order->save();
                    }
                }
                
                if ( $partial_order ) {
                    if ( $partial_order->needs_payment() ) {
                        $unpaid_payments[] = $partial_order;
                        $unpaid_total      += floatval( $partial_order->get_total() );
                    } else {
                        /* use for calculate multi currency */
                        $paid_total += $partial_order->get_total();
                    }
                    
                    $suborder_original_total += floatval( $partial_order->get_total() );
                }
            }
            $suborder_original_total = wc_format_decimal( $suborder_original_total, '' );
            $difference              = floatval( $order_total ) + $fee_total - $suborder_original_total;
            $difference              = wc_format_decimal( $difference, '' );
            if ( 0 == $suborder_original_total ) {
                return;
            }
            if ( $difference > 0 || $difference < 0 ) {
                $positive             = $difference > 0;
                $difference           = abs( $difference );
                $total_amount_changed = 0;
                
                foreach ( $unpaid_payments as $index => $payment ) {
                    $percentage           = $payment->get_total() / $unpaid_total * 100;
                    $amount               = wc_format_decimal( $percentage * $difference / 100, wc_get_price_decimals() );
                    $remaining            = $difference;
                    $count                = 0;
                    $total_amount_changed += $amount;
                    
                    if ( count( $unpaid_payments ) === $count ) {
                        $amount = $remaining;
                    } else {
                        $remaining -= $amount;
                    }
                    
                    /* Make total amount equal order total absolutely */
                    $is_last = ( ( count( $unpaid_payments ) - 1 ) == $index );
                    
                    if ( $is_last ) {
                        $deviation = $difference - $total_amount_changed;
                        $amount    += $deviation;
                    }
                    
                    if ( $positive ) {
                        foreach ( $payment->get_fees() as $item ) {
                            $item->set_total( $payment->get_total() + $amount );
                            $item->save();
                        }
                    } else {
                        foreach ( $payment->get_fees() as $item ) {
                            $item->set_total( $payment->get_total() - $amount );
                            $item->save();
                        }
                    }
                    
                    $payment->calculate_totals( false );
                    
                    $payment->save();
                }
                
                $future_payment = $order->get_meta( '_depart_future_payment' );
                
                if ( $positive ) {
                    $future_payment += $difference;
                } else {
                    $future_payment -= $difference;
                }
                $order->update_meta_data( '_depart_future_payment', floatval( $future_payment ) );
            }
            $order->update_meta_data( '_depart_total', floatval( $order_total ) );
            $order->update_meta_data( '_depart_paid_amount', $paid_total );
            $order->save();
        }
    }
    
    public function depart_reload_payment_meta_box() {
        check_ajax_referer( 'depart_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die();
        }
        $order_id = isset( $_POST['order_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) : 0;
        $order    = wc_get_order( $order_id );
        
        if ( $order ) {
            $schedule = depart_get_schedule_payments_summary( $order );
            $html     = wc_get_template_html( 'plan/schedule-payments-summary.php', [ 'schedule' => $schedule ], '', $this->template_url );
            wp_send_json_success( [ 'html' => $html ] );
        }
        wp_die();
    }
    
    public function depart_deposit_order_metadata( $item_id, $item, $product ) {
        $order        = $item->get_order();
        $metadata     = $item->get_meta( 'depart_deposit_meta' );
        $deposit_data = [];
        if ( ! empty( $metadata ) ) {
            $deposit_data[] = (object) [
                'key'   => __( 'Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'value' => wc_price( $metadata['deposit'], array( 'currency' => $order->get_currency() ) ),
            ];
            $deposit_data[] = (object) [
                'key'   => __( 'Future payments', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'value' => wc_price( $metadata['future_payment'], array( 'currency' => $order->get_currency() ) ),
            ];
            $deposit_data[] = (object) [
                'key'   => __( 'Fees', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'value' => wc_price( $metadata['fee_total'], array( 'currency' => $order->get_currency() ) ),
            ];
        }
        if ( ! empty( $deposit_data ) ) {
            foreach ( $deposit_data as $data ) {
                echo '<div class="wc-order-item-deposit"><strong>' . esc_html( $data->key ) . ': </strong> ' . wp_kses_post( $data->value ) . '</div>';
            }
        }
    }
    
    public function depart_add_recalculate_deposit_button( $order ) {
        if ( $order->is_editable() && ! empty( $order->get_items() ) && 'auto-draft' != $order->get_status() ) {
            ?>
            <button type="button"
                    class="button button-primary recalculate-deposit-action"
                    data-order_id="<?php echo esc_attr( $order->get_id() ) ?>"
            >
                <?php esc_html_e( 'Recalculate deposit', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
            </button>
            <script type="text/template" id="tmpl-wdp-modal-recalculate-deposit"></script>
            <?php
        }
    }
    
    public function get_recalculate_deposit_modal() {
        check_ajax_referer( 'depart_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die();
        }
        
        $order_id = isset( $_POST['order_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) : 0;
        $order    = wc_get_order( $order_id );
        
        if ( $order ) {
            $html = wc_get_template_html( 'order/recalculate-deposit-modal.php', [ 'order' => $order ], '', $this->template_url );
            wp_send_json_success( [ 'html' => $html ] );
            wp_die();
        } else {
            wp_send_json_error( 'Order not found!' );
            wp_die();
        }
    }
    
    public function save_deposit_data() {
        check_ajax_referer( 'depart_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die();
        }
        
        if ( ! isset( $_POST['order_id'], $_POST['wdp_data'] ) ) {
            wp_send_json_error( 'Form data is not correct!' );
        }
        
        $order_id = sanitize_key( wp_unslash( $_POST['order_id'] ) );
        $wpd_data = array_map( '_sanitize_text_fields', wp_unslash( $_POST['wdp_data'] ) );
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( 'Order not found!' );
        }
        $order_items = $order->get_items();
        if ( empty( $order_items ) ) {
            wp_die();
        }
        
        $is_deposit_order = false;
        /* Add deposit data for each order item */
        foreach ( $order_items as $item ) {
            $id = $item->get_id();
            
            $dp_enabled = isset( $wpd_data[ 'wdp_deposits_deposit_enabled_' . $id ] ) ? $wpd_data[ 'wdp_deposits_deposit_enabled_' . $id ] : '';
            $dp_type    = isset( $wpd_data[ 'wdp_deposits_deposit_type_' . $id ] ) ? $wpd_data[ 'wdp_deposits_deposit_type_' . $id ] : '';
            $dp_plan_id = isset( $wpd_data[ 'wdp_deposits_payment_plan_' . $id ] ) ? $wpd_data[ 'wdp_deposits_payment_plan_' . $id ] : false;
            if ( $dp_enabled && $dp_type && false !== $dp_plan_id ) {
                if ( $this->update_deposit_data_to_order_item( $item, $dp_type, $dp_plan_id ) ) {
                    $item->save_meta_data();
                    $is_deposit_order = true;
                }
            }
        }
        
        /* Remove prior installment plan */
        $this->clear_exist_installment_plan( $order );
        
        /* If no item has deposit enabled, remove deposit data */
        if ( ! $is_deposit_order ) {
            $this->recalculate_remove_deposit_data( $order );
            wp_die();
        }
        
        /* Calculate deposit data */
        $extra_options = [
            'coupon'       => isset( $wpd_data['wdp_deposits_coupon'] ) ? $wpd_data['wdp_deposits_coupon'] : 'deposit',
            'tax'          => isset( $wpd_data['wdp_deposits_tax'] ) ? $wpd_data['wdp_deposits_tax'] : 'deposit',
            'fee'          => isset( $wpd_data['wdp_deposits_fee'] ) ? $wpd_data['wdp_deposits_fee'] : 'deposit',
            'shipping'     => isset( $wpd_data['wdp_deposits_shipping'] ) ? $wpd_data['wdp_deposits_shipping'] : 'deposit',
            'shipping_tax' => isset( $wpd_data['wdp_deposits_shipping_tax'] ) ? $wpd_data['wdp_deposits_shipping_tax'] : 'deposit',
        ];
        $this->recalculate_update_deposit_data( $order, $extra_options );
    }
    
    public function recalculate_update_deposit_data( $order, $extra_options ) {
        $deposit_info = $this->recalculate_deposit_info( $order, $extra_options );
        if ( ! $deposit_info['deposit_enabled'] ) {
            $this->recalculate_remove_deposit_data( $order );
            wp_send_json_error( 'Deposit disabled because deposit amount greater than Order Total.', 'depart-deposit-and-part-payment-for-woocommerce' );
        }
        $this->add_deposit_meta_data_to_order( $order, $deposit_info );
        $this->recalculate_update_installment_plan( $order );
    }
    
    public function clear_exist_installment_plan( $order ) {
        $exist_schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
        remove_action( 'woocommerce_before_delete_order', [ $this, 'depart_before_delete_partial_orders' ], 9 );
        if ( ! empty( $exist_schedule ) ) {
            add_filter( 'depart_enable_delete_suborder', function() {
                return true;
            });
            foreach ( $exist_schedule as $item ) {
                $partial_order = wc_get_order( $item['id'] );
                if ( $partial_order ) {
                    $partial_order->delete( true );
                }
            }
        }
    }
    
    public function add_deposit_meta_data_to_order( $order, $deposit_info ) {
        if ( ! empty( $deposit_info ) ) {
            $deposit        = $deposit_info['deposit_amount'];
            $remaining      = floatval( $order->get_total( 'edit' ) ) - $deposit;
            $schedule       = $deposit_info['payment_schedule'];
            $interest_total = $deposit_info['fee_total'];
            $extra_options  = $deposit_info['extra_options'];
            $total          = $deposit_info['depart_total'];
            $original_total = $deposit_info['depart_original_total'];
            
            $order->update_meta_data( 'depart_deposit_payment_schedule', $schedule, true );
            $order->update_meta_data( '_depart_deposit_amount', $deposit, true );
            $order->update_meta_data( '_depart_future_payment', $remaining, true );
            $order->update_meta_data( '_depart_fee_total', $interest_total, true );
            $order->update_meta_data( '_depart_is_deposit_order', true );
            $order->update_meta_data( '_depart_paid_amount', 0 );
            $order->update_meta_data( '_depart_extra_options', $extra_options );
            $order->update_meta_data( '_depart_total', $total );
            $order->update_meta_data( '_depart_depart_original_total', $original_total );
            $order->save();
        }
    }
    
    public function recalculate_remove_deposit_data( \WC_Order $order ) {
        $order_items = $order->get_items();
        
        foreach ( $order_items as $item ) {
            $item->delete_meta_data( 'depart_deposit_meta' );
        }
        
        $order->delete_meta_data( 'depart_deposit_payment_schedule' );
        $order->delete_meta_data( '_depart_deposit_amount' );
        $order->delete_meta_data( '_depart_future_payment' );
        $order->delete_meta_data( '_depart_fee_total' );
        $order->delete_meta_data( '_depart_is_deposit_order' );
        $order->delete_meta_data( '_depart_paid_amount' );
        $order->delete_meta_data( '_depart_extra_options' );
        $order->save();
    }
    
    public function update_deposit_data_to_order_item( $item, $deposit_type, $plan_id ) {
        /* Using net total of order item*/
        $item_total   = $item->get_subtotal();
        $plan         = null;
        $product      = wc_get_product( $item->get_product_id() );
        $payment_type = 'percentage';
        $quantity     = $item->get_quantity();
        switch ( $deposit_type ) {
            case 'global':
                $plan = get_option( 'depart_payment_plan' )[ $plan_id ] ?? null;
                break;
            case 'custom':
                $plan = $product->get_meta( 'depart_custom_plans' )[ $plan_id ] ?? null;
                if ( ! $plan ) {
                    $plan = $product->get_meta( 'depart_exists_plans' )[ $plan_id ] ?? null;
                }
                $payment_type = $plan['unit-type'] ?? 'percentage';
                break;
        }
        if ( $plan ) {
            $deposit       = 0;
            $remaining     = 0;
            $fee           = 0;
            $deposit_fee   = 0;
            $sub_total     = wc_format_decimal( floatval( $item_total ), wc_get_price_decimals() );
            $sub_total_tax = $item->get_subtotal_tax();
            $tax_deposit   = 0;
            $tax_total     = 0;
            switch ( $payment_type ) {
                case 'percentage':
                    $deposit     = depart_get_due_amount( $plan['deposit'], $sub_total );
                    $tax_deposit = depart_get_tax_amount( $plan['deposit'], $sub_total_tax );
                    $deposit_fee = $fee = depart_get_due_amount( $plan['deposit_fee'], $deposit );
                    foreach ( $plan['plan_schedule'] as $partial ) {
                        $partial_amount = depart_get_due_amount( $partial['partial'], $sub_total );
                        $fee            += depart_get_due_amount( $partial['fee'], $partial_amount );
                    }
                    break;
                case 'fixed':
                    $deposit     = $plan['deposit'] * $quantity;
                    $percentage  = $deposit / $sub_total;
                    $tax_deposit = depart_get_tax_amount( $percentage, $sub_total_tax );
                    $deposit_fee = $fee = floatval( $plan['deposit_fee'] );
                    foreach ( $plan['plan_schedule'] as $partial ) {
                        $fee       += floatval( $partial['fee'] );
                        $remaining += $partial['partial'];
                    }
                    $fee         *= $quantity;
                    $deposit_fee *= $quantity;
                    break;
            }
            $remaining *= $quantity;
            if ( $deposit < $sub_total ) {
                $deposit_meta['enable']         = 1;
                $deposit_meta['deposit']        = $deposit;
                $deposit_meta['future_payment'] = ( 'fixed' === $payment_type ) ? $remaining : $sub_total - $deposit;
                $deposit_meta['total']          = $deposit_meta['future_payment'] + $deposit;
                $deposit_meta['tax_deposit']    = wc_format_decimal( $tax_deposit, wc_get_price_decimals() );
                $deposit_meta['tax_total']      = $tax_total;
                $deposit_meta['deposit_fee']    = $deposit_fee;
                $deposit_meta['fee_total']      = $fee;
                $deposit_meta['plan']           = $plan;
                $deposit_meta['plan_id']        = $plan_id;
                $deposit_meta['unit-type']      = $payment_type;
                $deposit_meta['deposit_type']   = $deposit_type;
                $item->update_meta_data( 'depart_deposit_meta', $deposit_meta, true );
                
                return true;
            } else {
                $deposit_meta = [
                    'enable' => 0,
                ];
                $item->update_meta_data( 'depart_deposit_meta', $deposit_meta, true );
                
                return false;
            }
        }
    }
    
    public function recalculate_deposit_info( \WC_Order $order, $extra_options ) {
        $items_total    = $order->get_subtotal();
        $origin_total   = 0;
        $deposit_amount = 0;
        $fee_total      = 0;
        $deposit_fee    = 0;
        
        $deposit_enabled = true;
        
        foreach ( $order->get_items() as $item ) {
            $item_meta = $item->get_meta( 'depart_deposit_meta' ) ?? false;
            if ( $item_meta && isset( $item_meta['enable'] ) && $item_meta['enable'] ) {
                $deposit_amount += $item_meta['deposit'];
                $fee_total      += $item_meta['fee_total'];
                $deposit_fee    += $item_meta['deposit_fee'];
                $origin_total   += $item_meta['future_payment'];
            } else {
                $deposit_amount += $item->get_subtotal();
            }
        }
        $origin_total            += $deposit_amount;
        $coupon_handling         = $extra_options['coupon'];
        $fees_handling           = $extra_options['fee'];
        $taxes_handling          = $extra_options['tax'];
        $shipping_handling       = $extra_options['shipping'];
        $shipping_taxes_handling = $extra_options['shipping_tax'];
        
        $deposit_discount       = 0.0;
        $deposit_fees           = 0.0;
        $deposit_taxes          = 0.0;
        $deposit_shipping       = 0.0;
        $deposit_shipping_taxes = 0.0;
        
        $division           = ( 0 == $items_total ) ? 1 : $items_total;
        $deposit_percentage = $deposit_amount * 100 / floatval( $division );
        
        // remaining amounts for build schedule later
        $remaining_amounts = [];
        
        // coupon handling
        $discount_total = floatval( $order->get_discount_total() );
        switch ( $coupon_handling ) {
            case 'deposit':
                $deposit_discount = $discount_total;
                break;
            case 'split':
                $deposit_discount = $deposit_percentage * $discount_total / 100;
                break;
        }
        $remaining_amounts['discount'] = $discount_total - $deposit_discount;
        
        // taxes handling
        $order_tax_item = floatval( $order->get_total_tax( 'edit' ) );
        switch ( $taxes_handling ) {
            case 'deposit':
                $deposit_taxes = $order_tax_item;
                break;
            case 'split':
                $deposit_taxes = $deposit_percentage * $order_tax_item / 100;
                break;
        }
        $remaining_amounts['tax'] = $order_tax_item - $deposit_taxes;
        
        // fees handling
        $order_fee_item = $order->get_total_fees();
        switch ( $fees_handling ) {
            case 'deposit':
                $deposit_fees = $order_fee_item;
                break;
            
            case 'split':
                $deposit_fees = $order_fee_item * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['fee'] = $order_fee_item - $deposit_fees;
        
        // Shipping handling
        $order_shipping_item = floatval( $order->get_shipping_total() );
        switch ( $shipping_handling ) {
            case 'deposit':
                $deposit_shipping = $order_shipping_item;
                break;
            
            case 'split':
                $deposit_shipping = $order_shipping_item * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['shipping'] = $order_shipping_item - $deposit_shipping;
        
        // Shipping taxes handling.
        $order_shipping_tax_item = floatval( $order->get_shipping_tax( 'edit' ) );
        switch ( $shipping_taxes_handling ) {
            case 'deposit':
                $deposit_shipping_taxes = $order_shipping_tax_item;
                break;
            
            case 'split':
                $deposit_shipping_taxes = $order_shipping_tax_item * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['shipping_tax'] = $order_shipping_tax_item - $deposit_shipping_taxes;
        
        $deposit_amount += $deposit_fees + $deposit_taxes + $deposit_shipping + $deposit_shipping_taxes - $deposit_discount - $order_shipping_tax_item;
        $total          = $origin_total + $order_tax_item + $order_fee_item + $order_shipping_item - $discount_total;
        
        if ( $deposit_amount <= 0 || ( $total + $discount_total - $deposit_amount - $remaining_amounts['discount'] ) <= 0 ) {
            $deposit_enabled = false;
        }
        
        $deposit_info = [
            'deposit_enabled'       => $deposit_enabled,
            'deposit_amount'        => $deposit_amount,
            'deposit_fee'           => $deposit_fee,
            'fee_total'             => $fee_total,
            'depart_total'          => $total,
            'depart_original_total' => $origin_total, // Original amount excluding tax,shipping adn discount
            'depart_remaining'      => $remaining_amounts,
            'extra_options'         => [
                'coupon'       => $coupon_handling,
                'fee'          => $fees_handling,
                'tax'          => $taxes_handling,
                'shipping'     => $shipping_handling,
                'shipping_tax' => $shipping_taxes_handling,
            ],
            'payment_schedule'      => [],
        ];
        
        if ( $deposit_enabled ) {
            $payment_schedule                 = $this->build_payment_schedule( $remaining_amounts, $deposit_amount, $total, $deposit_fee, $fee_total, $order );
            $deposit_info['payment_schedule'] = $payment_schedule;
        }
        
        return $deposit_info;
    }
    
    public function build_payment_schedule( $remaining_amounts, $deposit, $total, $deposit_fee, $fee_total, $order ) {
        $current_date         = new \DateTime();
        $current_date_string  = $current_date->getTimestamp();
        $next_payments        = $total - $deposit;
        $origin_next_payments = $next_payments + $remaining_amounts['discount'] - $remaining_amounts['tax'] - $remaining_amounts['fee'] - $remaining_amounts['shipping'] - $remaining_amounts['shipping_tax'];
        $schedule             = [];
        $plans                = [];
        foreach ( $order->get_items() as $item ) {
            $item_meta = $item->get_meta( 'depart_deposit_meta' );
            if ( isset( $item_meta['enable'] ) && $item_meta['enable'] ) {
                $plans[] = depart_get_schedule( $item_meta['plan'], $item->get_subtotal() );
            }
        }
        
        foreach ( $plans as $plan ) {
            foreach ( $plan as $partial ) {
                $date = $partial['date'];
                if ( array_key_exists( $date, $schedule ) ) {
                    $schedule[ $date ]['amount'] += $partial['amount'];
                    $schedule[ $date ]['fee']    += $partial['fee'];
                } else {
                    $schedule[ $date ] = [
                        'id'     => '',
                        'type'   => 'partial',
                        'date'   => $date,
                        'amount' => $partial['amount'],
                        'fee'    => $partial['fee'],
                    ];
                }
            }
        }
        
        $schedule_total = $deposit + $deposit_fee;
        foreach ( $schedule as &$payment ) {
            $rate             = $payment['amount'] / $origin_next_payments;
            $amount           = $rate * $next_payments;
            $fee              = $payment['fee'];
            $payment['total'] = $amount + $fee;
            $schedule_total   += $payment['total'];
        }
        $difference                       = $total + $fee_total - $schedule_total;
        $schedule[ $current_date_string ] = [
            'id'     => '',
            'type'   => 'deposit',
            'date'   => $current_date_string,
            'amount' => $deposit,
            'fee'    => $deposit_fee,
            'total'  => $deposit + $deposit_fee,
        ];
        
        usort( $schedule, function( $a, $b ) {
            return $a['date'] - $b['date'];
        } );
        
        $last_key                       = key( array_slice( $schedule, - 1, 1, true ) );
        $schedule[ $last_key ]['total'] += $difference;
        
        return $schedule;
    }
    
    public function recalculate_update_installment_plan( $order ) {
        $payment_schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
        $data             = $order->get_data();
        if ( ! empty( $payment_schedule ) ) {
            foreach ( $payment_schedule as $partial_key => $partial ) {
                $partial_payment = new Partial_Order();
                
                $amount = $partial['total'];
                /* translators: Order number*/
                $name                 = esc_html__( 'Partial Payment for order %s', 'depart-deposit-and-part-payment-for-woocommerce' );
                $partial_payment_name = apply_filters( 'depart_deposit_partial_payment_name', sprintf( $name, $order->get_order_number() . '-' . ++ $partial_key ), $partial, $order->get_id() );
                $item                 = new \WC_Order_Item_Fee();
                
                $item->set_props( [
                    'total' => $amount,
                ] );
                
                $item->set_name( $partial_payment_name );
                $partial_payment->add_item( $item );
                $partial_payment->set_created_via( 'admin' );
                $partial_payment->set_currency( get_woocommerce_currency() );
                $partial_payment->set_parent_id( $order->get_id() );
                $partial_payment->set_customer_id( $order->get_customer_id( 'edit' ) );
                $partial_payment->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
                $partial_payment->add_meta_data( '_depart_partial_payment_type', $partial['type'] );
                $partial_payment->add_meta_data( '_depart_partial_payment_date', $partial['date'] );
                $partial_payment->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
                $partial_payment->set_total( $amount );
                $partial_payment->save();
                
                $payment_schedule[ -- $partial_key ]['id'] = $partial_payment->get_id();
                
                $order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
                if ( $order_number_meta ) {
                    $partial_payment->add_meta_data( '_alg_wc_full_custom_order_number', $order_number_meta );
                }
                
                $partial_payment->save();
            }
            $order->update_meta_data( 'depart_deposit_payment_schedule', $payment_schedule );
            $order->save();
        }
    }
    
    public function depart_before_delete_partial_orders( $id, $order ) {
        $order = wc_get_order( $id );
        
        if ( ! $order || ! method_exists( $order, 'get_type' ) ) {
            return;
        }
        
        if ( $order->get_type() === $this->deposit_type ) {
            $delete_enable = apply_filters( 'depart_enable_delete_suborder', $this->delete_enable );
            if ( ! $delete_enable ) {
                /* translators: Order id */
                $error_message = sprintf( __( 'Can not delete suborder %s. Because it\'s parent has not been deleted.', 'depart-deposit-and-part-payment-for-woocommerce' ), $order->get_order_number() );
                wp_die( esc_html( $error_message ), 'Error', [ 'response' => 403 ] );
            }
        }
        
        if ( $order->get_type() === 'shop_order' ) {
            $schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
            if ( is_array( $schedule ) && ! empty( $schedule ) ) {
                $this->delete_enable = true;
                foreach ( $schedule as $payment ) {
                    if ( isset( $payment['id'] ) && is_numeric( $payment['id'] ) ) {
                        wp_delete_post( absint( $payment['id'] ), true );
                    }
                }
            }
        }
    }
    
    public function depart_trash_partial_orders( $id ) {
        if ( ! current_user_can( 'delete_posts' ) || ! $id ) {
            return;
        }
        
        $order = wc_get_order( $id );
        
        if ( ! is_a( $order, 'WC_Order' ) ) {
            return;
        }
        
        if ( ! $order || $order->get_type() !== 'shop_order' ) {
            return;
        }
        
        $args = [
            'post_parent'     => $id,
            'parent_order_id' => $id,
            'post_type'       => $this->deposit_type,
            'numberposts'     => - 1,
        ];
        
        $partial_orders = wc_get_orders( $args );
        remove_filter( 'pre_trash_post', [ $this, 'depart_prevent_user_trash_partial_orders' ] );
        if ( is_array( $partial_orders ) && ! empty( $partial_orders ) ) {
            foreach ( $partial_orders as $partial_order ) {
                add_post_meta( $partial_order->get_id(), '_wp_trash_meta_status', $partial_order->get_status() );
                add_post_meta( $partial_order->get_id(), '_wp_trash_meta_time', time() );
                $partial_order->set_status( 'trash' );
                $partial_order->save();
            }
        }
        add_filter( 'pre_trash_post', [ $this, 'depart_prevent_user_trash_partial_orders' ], 10, 2 );
    }
    
    public function depart_untrash_partial_orders( $id, $previous_status ) {
        $order = wc_get_order( $id );
        
        if ( ! $order || ! method_exists( $order, 'get_type' ) ) {
            return;
        }
        
        if ( $order->get_type() === $this->deposit_type ) {
            /* translators: Order id */
            $error_message = sprintf( __( 'Can not untrash suborder %s. Because it\'s parent order is trashed.', 'depart-deposit-and-part-payment-for-woocommerce' ), $order->get_order_number() );
            wp_die( esc_html( $error_message ), 'Error', [ 'response' => 403 ] );
        }
        
        $args = [
            'post_parent'     => $id,
            'parent_order_id' => $id,
            'post_type'       => $this->deposit_type,
            'numberposts'     => - 1,
            'status'          => 'trash',
        ];
        
        $partial_orders = wc_get_orders( $args );
        
        if ( is_array( $partial_orders ) && ! empty( $partial_orders ) ) {
            foreach ( $partial_orders as $partial_order ) {
                $previous_status = get_post_meta( $partial_order->get_id(), '_wp_trash_meta_status', true );;
                delete_post_meta( $partial_order->get_id(), '_wp_trash_meta_status' );
                delete_post_meta( $partial_order->get_id(), '_wp_trash_meta_time' );
                $partial_order->set_status( $previous_status );
                $partial_order->save();
            }
        }
    }
    
    public function depart_prevent_user_trash_partial_orders( $id, $order ) {
        if ( ! $id ) {
            $order = wc_get_order( $order );
        }
        
        if ( ! $order || ! method_exists( $order, 'get_type' ) ) {
            return;
        }
        
        if ( $order->get_type() === $this->deposit_type ) {
            $parent = wc_get_order( $order->get_parent_id() );
            
            if ( $parent && $parent->get_status() != 'trash' ) {
                /* translators: Order id*/
                $error_message = sprintf( __( 'Can not trash suborder %s. Because it\'s parent order is untrash.', 'depart-deposit-and-part-payment-for-woocommerce' ), $order->get_order_number() );
                wp_die( esc_html( $error_message ), 'Error', [ 'response' => 403 ] );
            }
        }
    }
    
    public function depart_define_shop_order_custom_columns( $columns ) {
        $show_columns = [];
        foreach ( $columns as $key => $column ) {
            $show_columns[ $key ] = $column;
            if ( $key === 'order_status' ) {
                $show_columns['order_reminder'] = __( 'Reminder email', 'depart-deposit-and-part-payment-for-woocommerce' );
            }
        }
        
        return $show_columns;
    }
    
    public function depart_render_shop_order_custom_column( $column, $order_id ) {
        if ( 'order_reminder' === $column ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            
            if ( $order->get_type() === 'shop_order' ) {
                $is_deposit_order = $order->get_meta( '_depart_is_deposit_order' );
                $mark             = '';
                if ( ! $is_deposit_order ) {
                    return;
                }
                $enable_suborder = depart_get_suborder_needs_payment( $order );
                if ( ! $enable_suborder ) {
                    return;
                }
                $statuses_accepted = [ 'installment', 'overdue' ];
                if ( $this->data_store->get_setting( 'free_partial_charge' ) ) {
                    $statuses_accepted[] = 'on-hold';
                }
            } else {
                $statuses_accepted = [ 'pending', 'failed' ];
                if ( ! $this->data_store->get_setting( 'free_partial_charge' ) ) {
                    $parent_order    = wc_get_order( $order->get_parent_id() );
                    $enable_suborder = depart_get_suborder_needs_payment( $parent_order );
                    if ( ! $enable_suborder || $enable_suborder->get_id() != $order->get_id() ) {
                        return;
                    }
                }
                $is_mail_sent = $order->get_meta( '_depart_reminder_email_sent' );
                
                if ( $is_mail_sent ) {
                    $mark = '<span class="dashicons dashicons-yes"></span>';
                } else {
                    $mark = '<span class="dashicons dashicons-no"></span>';
                }
            }
            
            $html = $mark . '<a class="depart-send-reminder-email-button" href="#/send-mail" data-id="' . $order->get_id() . '">' . __( 'Send', 'depart-deposit-and-part-payment-for-woocommerce' ) . '</a>';
            
            if ( in_array( $order->get_status(), $statuses_accepted ) ) {
                echo wp_kses_post( '<div class="depart-mail-reminder-action">' . $html . '</div>' );
            }
        }
    }
    
    public function depart_send_reminder_email() {
        if ( ! ( isset( $_POST['nonce'], $_POST['order_id'] ) ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) {
            return;
        }
        
        $order_id = sanitize_key( wp_unslash( $_POST['order_id'] ) );
        
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $emails = WC()->mailer()->get_emails();
            if ( ! isset( $emails['depart_email_payment_reminder'] ) || $emails['depart_email_payment_reminder']->enabled === 'no' || ! $emails['depart_email_payment_reminder']->trigger( $order_id, $order ) ) {
                wp_send_json_error( esc_html__( 'Email sent failed', 'depart-deposit-and-part-payment-for-woocommerce' ) );
            } else {
                wp_send_json_success( esc_html__( 'Email sent successfully', 'depart-deposit-and-part-payment-for-woocommerce' ) );
            }
        }
        wp_send_json_error( esc_html__( 'Invalid Order', 'depart-deposit-and-part-payment-for-woocommerce' ) );
    }
    
}