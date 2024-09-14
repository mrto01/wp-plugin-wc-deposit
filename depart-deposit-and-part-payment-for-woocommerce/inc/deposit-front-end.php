<?php

namespace VicoDIn\Inc;

use VicoDIn\Admin\Deposit_Setting;
use VicoDIn\Inc\Payment\Auto_Payment;
use VicoDIn\Inc\Payment\Stripe_Gateway;
use WC_Payment_Tokens;

defined( 'ABSPATH' ) || exit;

class Deposit_Front_End {
    
    static $instance = null;
    
    public $slug;
    
    public $dist_url;
    
    public $deposit_type;
    
    public $template_url;
    
    private $data_store;
    
    public function __construct() {
        $this->slug         = DEPART_CONST['slug'];
        $this->dist_url     = DEPART_CONST['dist_url'];
        $this->deposit_type = DEPART_CONST['order_type'];
        $this->template_url = DEPART_CONST['plugin_dir'] . '/templates/';
        $this->data_store   = Data::load();
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_styles' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_scripts' ] );
        
        if ( $this->data_store->get_setting( 'enabled' ) ) {
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'depart_get_deposit_block' ], 1111 );
            add_action( 'wp_ajax_depart_get_deposit_variation_block', [ $this, 'depart_get_deposit_variation_block' ] );
            add_filter( 'woocommerce_add_cart_item_data', [ $this, 'depart_add_cart_item_data' ], 10, 3 );
            add_action( 'woocommerce_after_calculate_totals', [ $this, 'depart_after_calculate_totals' ] );
            add_filter( 'woocommerce_get_item_data', [ $this, 'depart_get_item_data' ], 10, 2 );
            add_action( 'wp_ajax_depart_get_deposit_block_from_cart_item', [ $this, 'depart_get_deposit_block_from_cart_item' ] );
            add_action( 'wp_ajax_depart_change_plan_from_cart_item', [ $this, 'depart_change_plan_from_cart_item' ] );
            add_action( 'woocommerce_init', [ $this, 'depart_update_cart_after_change_plan' ] );
            add_action( 'woocommerce_cart_totals_after_order_total', [ $this, 'depart_cart_totals_after_order_total' ] );
            add_filter( 'woocommerce_cart_needs_payment', [ $this, 'depart_cart_needs_payment' ], 10, 2 );
            add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'depart_checkout_create_order_line_item' ], 10, 4 );
            add_action( 'depart_checkout_update_order_meta', [ $this, 'depart_checkout_update_order_meta' ], 10, 2 );
            add_action( 'woocommerce_review_order_after_order_total', [ $this, 'depart_cart_totals_after_order_total' ] );
            add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'depart_get_checkout_payment_url' ], 10, 2 );
            add_action( 'woocommerce_create_order', [ $this, 'depart_create_order' ], 10, 2 );
        }
        
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'depart_available_payment_gateways' ], 99 );
        add_filter( 'woocommerce_order_class', [ $this, 'depart_order_class' ], 10, 3 );
        
        // Handle force deposit option
        add_filter( 'woocommerce_add_to_cart_product_id', [ $this, 'depart_handle_add_to_cart_force_deposit' ] );
        add_filter( 'woocommerce_store_api_add_to_cart_data' , [ $this, 'depart_handle_add_to_cart_force_deposit' ] );
        
        // Add new order type
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'depart_order_details_after_order_table' ] );
        add_action( 'woocommerce_my_account_my_orders_actions', [ $this, 'depart_my_account_my_orders_actions' ], 10, 2 );
        add_action( 'woocommerce_thankyou', [ $this, 'depart_rewrite_order_tails_table' ], 9 );
        add_action( 'depart_after_schedule_payments_summary', [ $this, 'render_auto_payment_option' ] );
        add_filter( 'woocommerce_get_order_item_totals', [ $this, 'depart_get_order_item_totals' ], 10, 2 );
        
        // Handle reduce stock
        add_filter( 'woocommerce_payment_complete_reduce_order_stock', [ $this, 'depart_payment_complete_reduce_order_stock' ], 10, 2 );
        add_action( 'woocommerce_order_status_installment', 'wc_maybe_reduce_stock_levels' );
        
        // Change Order's statuses when suborders' status change.
        add_action( 'woocommerce_order_status_changed', [ $this, 'depart_order_status_changed' ], 10, 4 );
        
        // Add information for email
        add_action( 'woocommerce_email_actions', [ $this, 'depart_email_action' ] );
        add_action( 'woocommerce_order_status_pending_to_installment', [ $this, 'depart_email_new_order' ] );
        add_action( 'woocommerce_order_status_failed_to_installment', [ $this, 'depart_email_new_order' ] );
        add_action( 'woocommerce_order_status_installment_to_processing', [ $this, 'depart_disable_deposit_order_notification' ] );
        add_action( 'woocommerce_order_status_on-hold_to_processing', [ $this, 'depart_disable_deposit_order_notification' ], 10, 3 );
        add_action( 'woocommerce_email_after_order_table', [ $this, 'depart_email_display_payment_schedule' ], 11, 4 );
        add_filter( 'woocommerce_email_enabled_new_order', [ $this, 'depart_disable_suborder_email_notification' ], 10, 3 );
        add_filter( 'woocommerce_email_enabled_customer_on_hold_order', [ $this, 'depart_disable_suborder_email_notification' ], 10, 3 );
        add_filter( 'woocommerce_email_enabled_customer_processing_order', [ $this, 'depart_disable_suborder_email_notification' ], 10, 3 );
        add_filter( 'woocommerce_email_enabled_customer_completed_order', [ $this, 'depart_disable_suborder_email_notification' ], 10, 3 );
        
        // Change amount to deposit amount when pay by payment gateway
        add_action( 'wc_ajax_ppc-create-order', [ $this, 'depart_modify_cart_data' ], 0 );
        
        // Add auto payment url to available payment methods
        add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'depart_add_payment_token_id' ], 10, 2 );
        add_action( 'wp_ajax_depart_set_order_auto_payment', [ $this, 'depart_set_order_auto_payment' ] );
        
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function depart_order_class( $classname, $order_type, $order_id ) {
        if ( $order_type === $this->deposit_type ) {
            return 'VicoDIn\Inc\Partial_Order';
        }
        
        return $classname;
    }
    
    public function is_match_rule( $product, $rule ) {
        $product_id = $product->get_id();
        if ( 'variation' === $product->get_type() ) {
            $product_id = $product->get_parent_id();
        }
        
        $user = ! empty( wp_get_current_user()->roles ) ? wp_get_current_user()->roles[0] : 'no_role';
        
        /* Check user*/
        if ( ! empty( $rule['rule_users_inc'] ) && ! in_array( $user, $rule['rule_users_inc'] ) ) {
            return false;
        }
        if ( ! empty( $rule['rule_users_exc'] ) && in_array( $user, $rule['rule_users_exc'] ) ) {
            return false;
        }
        
        /* Check specific products*/
        if ( ! empty( $rule['rule_products_exc'] ) && in_array( $product_id, $rule['rule_products_exc'] ) ) {
            return false;
        }
        if ( ! empty( $rule['rule_products_inc'] ) ) {
            
            if ( ( ! empty( $rule['rule_categories_inc'] ) || ! empty( $rule['rule_categories_exc'] ) ) && in_array( $product_id, $rule['rule_products_inc'] ) ) {
                return true;
            }else if ( empty( $rule['rule_categories_inc'] ) && empty( $rule['rule_categories_exc'] )  && ! in_array( $product_id, $rule['rule_products_inc'] ) ) {
                return false;
            }
            
        }
        
        /* Check price range*/
        $product_price = $product->get_price();
        $price_start   = empty( $rule['rule_price_range']['price_start'] ) ? '' : $rule['rule_price_range']['price_start'];
        $price_end     = empty( $rule['rule_price_range']['price_end'] ) ? '' : $rule['rule_price_range']['price_end'];
        
        if ( '' !== $price_start || '' !== $price_end ) {
            // Get properly variation's price
            if ( 'variable' === $product->get_type() ) {
                $product_price = $product->get_variation_price( 'max' );
                if ( $price_end && $price_end < $product_price ) {
                    $product_price = $product->get_variation_price( 'min' );
                }
                /* If get plans via ajax, the product has been a variation already */
                if ( did_action('wp_ajax_depart_get_deposit_variation_block') ) {
                    $product_price = $product->get_price();
                }
            } else {
                $product_price = $product->get_price();
            }
            
            if ( ! empty( $rule['rule_price_range']['include_tax'] ) ) {
                $product_price = wc_get_price_including_tax( $product );
            }
            
            if ( $price_start && $product_price < $price_start ) {
                return false;
            }
            
            if ( $price_end && $product_price > $price_end ) {
                return false;
            }
        }
        
        // Get categories in hierarchy
        $categories = depart_get_ancestors( $product->get_category_ids() );
        if ( ! empty( $rule['rule_categories_inc'] ) && ! array_intersect( $categories, $rule['rule_categories_inc'] ) ) {
            return false;
        }
        if ( ! empty( $rule['rule_categories_exc'] ) && array_intersect( $categories, $rule['rule_categories_exc'] ) ) {
            return false;
        }
        if ( ! empty( $rule['rule_tags_inc'] ) && ! array_intersect( $product->get_tag_ids(), $rule['rule_tags_inc'] ) ) {
            return false;
        }
        if ( ! empty( $rule['rule_tags_exc'] ) && array_intersect( $product->get_tag_ids(), $rule['rule_tags_exc'] ) ) {
            return false;
        }
        
        return true;
    }
    
    public function check_rule_match( $product ) {
        $plans = [];
        $rules = get_option( 'depart_deposit_rule', [] );
        foreach ( $rules as $rule ) {
            if ( ! $rule['rule_active'] ) {
                continue;
            }
            if ( $this->is_match_rule( $product, $rule ) ) {
                $exists_plans = get_option( 'depart_payment_plan' );
                $plans        = array_filter( $exists_plans, function( $plan ) use ( $rule ) {
                    if ( $plan['plan_active'] ) {
                        return in_array( $plan['plan_id'], $rule['payment_plans'] );
                    }
                    
                    return '';
                } );
                break;
            }
        }
        
        return $plans;
    }
    
    public function depart_get_deposit_block( $variation = null, $prior_plan_id = false ) {
        /* Hide on cart wcaio sticky bar */
        if ( did_action( 'vi_wcaio_before_add_to_cart_button' ) ) {
            return;
        }
        
        global $product;
        
        if ( ! isset( $product ) ) {
            return;
        }
        
        $deposit_settings = $this->data_store->get_settings();
        $product_disabled = $product->get_meta( 'depart_deposit_disabled' );
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( ! $deposit_settings['enabled'] ) {
            return;
        }
        if ( 'yes' === $product_disabled ) {
            return;
        }
        if ( ! in_array( $product->get_type(), array( 'simple', 'variable', 'booking' ) ) ) {
            return;
        }
        $deposit_type = $product->get_meta( 'depart_deposit_type' );
        
        if ( empty( $deposit_type ) ) {
            $deposit_type = 'global';
        }
        
        $plans        = [];
        $product_type = $product->get_type();
        if ( 'variable' === $product_type && null != $variation ) {
            /* Because get_price() always return regular price*/
            $variation_price = $variation->get_sale_price();
            if ( !$variation_price ) {
                $variation_price = $variation->get_regular_price();
            }
            $price = wc_get_price_excluding_tax( $variation, array( 'price' => $variation_price ) );
        } else {
            $price = wc_get_price_excluding_tax( $product );
        }
        
        if ( 'custom' === $deposit_type ) {
            $plans        = $product->get_meta( 'depart_custom_plans' );
            $exists_plans = $product->get_meta( 'depart_exists_plans' );
            if ( is_array( $plans ) && ! empty( $plans ) ) {
                foreach ( $plans as $key => $plan ) {
                    $unit_type = $plan['unit-type'];
                    $total     = floatval( $plan['total'] );
                    
                    if ( 'fixed' === $unit_type && $price <= $plan['deposit'] ) {
                        unset( $plans[ $key ] );
                    } elseif ( 'percentage' === $unit_type && 100 != $total ) {
                        unset( $plans[ $key ] );
                    }
                }
            }
            if ( is_array( $exists_plans ) && ! empty( $exists_plans ) ) {
                $plans += $exists_plans;
            }
        } else {
            $plans = $this->check_rule_match( $product );
        }
        if ( empty( $plans ) ) {
            return '<div class="depart-plan-boxes">' . __( 'No plans founds (You will pay the full amount)', 'depart-deposit-and-part-payment-for-woocommerce' ) . '</div>';
        }
        
        /* If product must be purchased via deposit */
        $product_force_deposit = $product->get_meta( 'depart_force_deposit' ) === 'yes';
        $force_deposit         = false;
        if ( $product_force_deposit || ( 'global' === $deposit_type && $deposit_settings['force_deposit'] ) ) {
            $force_deposit = true;
            add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'depart_product_single_add_to_cart_text', ] );
        }
        
        /* If customer change plan from cart item */
        if ( false !== $prior_plan_id ) {
            ob_start();
            ?>
            <div id="depart-deposit-modal" class="depart-deposit-modal-cart-item">
                <input type="hidden" name="depart-deposit-type"
                       id="depart-deposit-type"
                       value="<?php echo esc_attr( $deposit_type ); ?>">
                <div class="depart-modal-content">
                    <span class="close">&times;</span>
                    <?php
                    wc_get_template( 'plan/deposit-block-content.php', [
                        'prior_plan_id'    => $prior_plan_id,
                        'plans'            => $plans,
                        'price'            => apply_filters( 'depart_get_due_amount', $price ),
                        'deposit_type'     => $deposit_type,
                        'deposit_settings' => $deposit_settings,
                        'product'          => $product,
                        'from_cart_item'   => '-ci', /* Separate form from cart item and form from product detail page */
                    ], '', $this->template_url );
                    ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        } else {
            if ( null != $variation ) {
                ob_start();
                wc_get_template( 'plan/deposit-block-content.php', [
                    'plans'            => $plans,
                    'price'            => apply_filters( 'depart_get_due_amount', $price ),
                    'deposit_type'     => $deposit_type,
                    'deposit_settings' => $deposit_settings,
                    'product'          => $variation,
                ], '', $this->template_url );
                
                return ob_get_clean();
            } else {
                wc_get_template( 'plan/deposit-block.php', [
                    'deposit_type'     => $deposit_type,
                    'product_type'     => $product_type,
                    'plans'            => $plans,
                    'price'            => apply_filters( 'depart_get_due_amount', $price ),
                    'deposit_settings' => $deposit_settings,
                    'product'          => $product,
                    'force_deposit'    => $force_deposit,
                ], '', $this->template_url );
            }
        }
    }
    
    public function depart_product_single_add_to_cart_text( $text ) {
        return depart_get_text_option( 'add_to_cart_text' );
    }
    
    public function depart_get_deposit_variation_block() {
        if ( ! isset( $_GET['variation_id'], $_GET['nonce'], $_GET['product_id'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), '_depart_nonce' ) ) {
            return;
        }
        $variation_id      = sanitize_text_field( wp_unslash( $_GET['variation_id'] ) );
        $product_id        = sanitize_text_field( wp_unslash( $_GET['product_id'] ) );
        $product_qty       = 1;
        $local_product     = wc_get_product( $product_id );
        $variation_product = wc_get_product( $variation_id );
        
        if ( $variation_product ) {
            /* If quantity has been changed */
            if ( isset( $_GET['quantity'] ) ) {
                $product_qty = sanitize_text_field( wp_unslash( $_GET['quantity'] ) );
            }
            /* Change price by rules of viredis plugin*/
            if ( depart_check_viredis_enable() ) {
                $product_price = \VIREDIS_Frontend_Product_Pricing_Store::get_price( $variation_product->get_price(), $variation_product, $product_qty );
                $variation_product->set_sale_price( $product_price );
            } else {
                $product_price = $variation_product->get_price();
            }

            global $product;
            
            $product = $local_product;
            
            $product->set_price( $product_price );
            $html = $this->depart_get_deposit_block( $variation_product );
            wp_send_json_success( $html );
        } else {
            wp_send_json_error( 'variation not found ' );
        }
        wp_die();
    }
    
    public function depart_get_deposit_block_from_cart_item() {
        if ( ! isset( $_GET['cart_item_key'], $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), '_depart_nonce' ) ) {
            return;
        }
        
        $cart_item_key = sanitize_text_field( wp_unslash( $_GET['cart_item_key'] ) );
        $cart_item     = WC()->cart->get_cart_item( $cart_item_key );
        if ( ! $cart_item ) {
            wp_send_json_error( __( 'Cart item not found', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        global $product;
        
        $product      = wc_get_product( $cart_item['product_id'] );
        $variation_id = $cart_item['variation_id'];
        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );
        } else {
            $variation = null;
        }
        
        /* Change payment plan price base on cart item quantity */
        add_filter( 'depart_get_due_amount', function( $price ) use ( $product, $variation, $cart_item ) {
            if ( depart_check_viredis_enable() ) {
                if ( $variation ) {
                    $price = \VIREDIS_Frontend_Product_Pricing_Store::get_price( $price, $product);
                }else {
                    $price = \VIREDIS_Frontend_Product_Pricing_Store::get_price( $price, $product );
                }
            }
            return $price * $cart_item['quantity'];
        });
        
        /* Change the page to control tax displaying */
        add_filter( 'depart_display_tax_in_plan', function( $page ){
            return 'cart';
        });
        
        wp_send_json_success( $this->depart_get_deposit_block( $variation, $cart_item['depart_deposit']['plan_id'] ) );
    }
    
    function depart_update_cart_after_change_plan() {
        woocommerce_store_api_register_update_callback( [
            'namespace' => 'depart_update_entire_cart',
            'callback'  => function() {},
        ] );
    }
    
    public function depart_change_plan_from_cart_item() {
        if ( ! isset( $_POST['cart_item_key'], $_POST['nonce'], $_POST['plan_id'], $_POST['deposit_type'] )
             || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), '_depart_nonce' ) ) {
            return;
        }
        
        $cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) );
        $plan_id       = sanitize_text_field( wp_unslash( $_POST['plan_id'] ) );
        $deposit_type  = sanitize_text_field( wp_unslash( $_POST['deposit_type'] ) );
        
        $cart_item = WC()->cart->get_cart_item( $cart_item_key );
        if ( ! $cart_item ) {
            wp_send_json_error( __( 'Cart item not found', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        if ( ! in_array( $deposit_type, [ 'custom', 'global' ] ) ) {
            wp_send_json_error( __( 'Plan from client is invalid', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        $cart_item['depart_deposit']['plan_id']      = $plan_id;
        $cart_item['depart_deposit']['deposit_type'] = $deposit_type;
        WC()->cart->cart_contents[ $cart_item_key ]  = $cart_item;
        WC()->cart->calculate_totals();
        wp_send_json_success( __( 'Plan changed', 'depart-deposit-and-part-payment-for-woocommerce' ) );
    }
    
    public function get_select_plan_button_in_cart( $cart_item ) {
        $plan_selected = isset( $cart_item['depart_deposit']['plan'] ) ? $cart_item['depart_deposit']['plan']['plan_name'] : '';
        $button        = '<span class="depart-deposit-options-checkout wc-block-components-product-details__depart-plan" data-cart_item_key="' . esc_attr( $cart_item['key'] ) . '">';
        $button        .= $plan_selected ? esc_html( $plan_selected ) : __( 'Select plan', 'depart-deposit-and-part-payment-for-woocommerce' );
        $button        .= '</span>';
        
        return $button;
    }
    
    public function frontend_enqueue_styles() {
        wp_enqueue_style( 'woocommerce-front-end-' . $this->slug, $this->dist_url . 'woocommerce-front-end.min.css', '', DEPART_CONST['version'] );
    }
    
    public function frontend_enqueue_scripts() {
        wp_enqueue_script( 'woocommerce-front-end-' . $this->slug, $this->dist_url . 'woocommerce-front-end.min.js', [ 'jquery' ], DEPART_CONST['version'], [ 'footer' => true ] );
        $i18n   = [ 'deposit' => depart_get_text_option( 'add_to_cart_text' ) ];
        $params = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( '_depart_nonce' ),
            'i18n'    => $i18n,
            'lang'    => depart_get_current_lang(),
        ];
        wp_localize_script( 'woocommerce-front-end-' . $this->slug, 'vicodinParams', $params );
    }
    
    public function depart_handle_add_to_cart_force_deposit( $data ) {
        
        /* If request from wc_block */
        $product_id = $data;
        if ( current_filter() == 'woocommerce_store_api_add_to_cart_data' ) {
            $product_id = $data['id'];
        }
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            // Let Woocommerce handle if $product_id is not valid
            return $data;
        }
        
        if ( ! $this->data_store->get_setting( 'enabled' ) ) {
            return $data;
        }
        
        $deposit_enabled = $this->check_deposit_enabled( $product );
        
        if ( ! $deposit_enabled ) {
            return $data;
        }
        
        $deposit_type = $product->get_meta( 'depart_deposit_type' );
        
        if ( empty( $deposit_type ) ) {
            $deposit_type = 'global';
        }
        
        
        if ( 'global' === $deposit_type ) {
            $plans = $this->check_rule_match( $product );
            if ( ! empty( $plans ) ) {
                if ( $this->data_store->get_setting( 'force_deposit' ) || $product->get_meta( 'depart_force_deposit' ) === 'yes' ) {
                    
                    if ( ! isset( $_POST['_depart_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_depart_nonce'] ), 'depart_nonce' ) ) {
                        return false;
                    }
                    if ( ! isset( $_POST['depart-deposit-type'], $_POST['depart-plan-select'] )
                         || ! isset( $plans[ sanitize_text_field( wp_unslash( $_POST['depart-plan-select'] ) ) ] )
                         || sanitize_text_field( wp_unslash( $_POST['depart-deposit-type'] ) ) != $deposit_type ) {
                        // Change product id to prevent add to cart
                        $product_id = 0;
                    } else {
                        $_POST['depart-deposit-check'] = true;
                    }
                }
            }
        } elseif ( 'custom' === $deposit_type ) {
            if ( $product->get_meta( 'depart_force_deposit' ) === 'yes' ) {
                $plans        = $product->get_meta( 'depart_custom_plans' );
                $exists_plans = $product->get_meta( 'depart_exists_plans' );
                
                if ( ! isset( $_POST['_depart_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_depart_nonce'] ), 'depart_nonce' ) ) {
                    return false;
                }
                if ( ! isset( $_POST['depart-deposit-type'], $_POST['depart-plan-select'] )
                     || sanitize_text_field( wp_unslash( $_POST['depart-deposit-type'] ) ) != $deposit_type ) {
                    // Change product id to prevent add to cart
                    $product_id = 0;
                } else {
                    $plan_id = sanitize_text_field( wp_unslash( $_POST['depart-plan-select'] ) );
                    if ( ! isset( $plans[ $plan_id ] ) && ! isset( $exists_plans[ $plan_id ] ) ) {
                        $product_id = 0;
                    } else {
                        $_POST['depart-deposit-check'] = true;
                    }
                }
            }
        }
        
        return is_array( $data ) ? array_merge( $data, array( 'id' => $product_id) ) : $product_id;
    }
    
    public function depart_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( ! isset( $_POST['depart-deposit-check'], $_POST['depart-deposit-type'], $_POST['depart-plan-select'], $_POST['_depart_nonce'] )
             || ! wp_verify_nonce( sanitize_key( $_POST['_depart_nonce'] ), 'depart_nonce' ) ) {
            return $cart_item_data;
        }
        
        $product    = wc_get_product( $product_id );
        $db_enabled = $this->check_deposit_enabled( $product );
        
        if ( ! $db_enabled ) {
            return $cart_item_data;
        }
        
        if ( $product->is_type( 'variable' ) ) {
            $product = wc_get_product( $variation_id );
        }
        $cart_item_data['depart_deposit'] = [
            'enable'       => true,
            'product_id'   => $product_id,
            'plan_id'      => sanitize_text_field( wp_unslash( $_POST['depart-plan-select'] ) ),
            'deposit_type' => sanitize_text_field( wp_unslash( $_POST['depart-deposit-type'] ) ),
        ];
        
        return $cart_item_data;
    }
    
    public function depart_get_item_data( $item_data, $cart_item ) {
        $enabled = $this->check_deposit_enabled( $cart_item['data'] );
        
        if ( $enabled ) {
            if ( isset( $cart_item['depart_deposit'], $cart_item['depart_deposit']['deposit'] ) && $cart_item['depart_deposit']['enable'] ) {
                $product = $cart_item['data'];
                
                if ( ! $product ) {
                    return $item_data;
                }
                $deposit        = floatval( $cart_item['depart_deposit']['deposit'] );
                $future_payment = floatval( $cart_item['depart_deposit']['future_payment'] );
                $fee            = floatval( $cart_item['depart_deposit']['fee_total'] );
                $display_tax    = depart_display_tax( 'cart' );
                if ( 'incl' === $display_tax ) {
                    $tax_deposit    = floatval( $cart_item['depart_deposit']['tax_deposit'] );
                    $tax_total      = floatval( $cart_item['depart_deposit']['tax_total'] );
                    $total          = floatval( $cart_item['depart_deposit']['total'] );
                    $deposit        += $tax_deposit;
                    $future_payment = $total + $tax_total - $deposit;
                }
                
                $item_data[] = [
                    'name'    => 'depart-cart-item-key',
                    'display' => 'depart-cart-item-key+' . $cart_item['key'] . '+end',
                ];
                
                $item_data[] = [
                    'name'    => 'depart-plan',
                    'display' => $this->get_select_plan_button_in_cart( $cart_item ),
                ];
                
                $item_data[] = [
                    'name'    => depart_get_text_option( 'deposit_payment_text' ),
                    'display' => '<div class="depart-cart-item-meta">' . wc_price( $deposit, [ 'decimals' => wc_get_price_decimals() ] ) . '</div><br>',
                ];
                
                $item_data[] = [
                    'name'    => depart_get_text_option( 'future_payments_text' ),
                    'display' => '<div class="depart-cart-item-meta">' . wc_price( $future_payment, [ 'decimals' => wc_get_price_decimals() ] ) . '</div><br>',
                ];
                
                if ( isset( $cart_item['depart_deposit']['fee_total'] ) && $cart_item['depart_deposit']['fee_total'] > 0 && $this->data_store->get_setting( 'show_fees' ) ) {
                    $item_data[] = [
                        'name'    => depart_get_text_option( 'fees_text' ),
                        'display' => '<div class="depart-cart-item-meta">' . wc_price( $fee, [ 'decimals' => wc_get_price_decimals() ] ) . '</div><br>',
                    ];
                }
            }
        }
        
        return $item_data;
    }
    
    public function check_deposit_enabled( $product ) {
        if ( ! $product || ! $product->is_type( [ 'simple', 'variable','variation','booking' ] ) ) {
            return false;
        }
        
        if ( $this->data_store->get_setting( 'enabled' ) ) {
            $disabled = $product->get_meta( 'depart_deposit_disabled' );
            
            if ( empty( $disabled ) ) {
                $disabled = 'no';
            }
            
            if ( 'yes' === $disabled ) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
    
    public function depart_after_calculate_totals( $cart ) {
        /* We don't add deposit information to the clone cart when calling ppc-simulate-cart */
        if ( is_admin() && ! defined( 'DOING_AJAX' ) || get_query_var( 'wc-ajax' ) === 'ppc-simulate-cart' ) {
            return; // Prevent running in admin and not during AJAX requests
        }
        if ( $cart ) {
            foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
                $this->depart_update_deposit_meta( $cart_item['data'], $cart_item['quantity'], $cart_item, $cart_item_key );
            }
            $this->depart_calculate_deposit( $cart );
        }
    }
    
    public function depart_update_deposit_meta( $product, $quantity, $cart_item_data, $cart_item_key ) {
        if ( $product && isset( $cart_item_data['depart_deposit'] ) ) {
            if ( isset( $cart_item_data['bundled_by'] ) ) {
                $cart_item_data['depart_deposit']['enable'] = 'no';
            }
            $deposit_enabled = $this->check_deposit_enabled( $product );
            if ( $deposit_enabled ) {
                $deposit_type = $cart_item_data['depart_deposit']['deposit_type'] ?? null;
                
                if ( $deposit_type ) {
                    $payment_type = 'percentage';
                    $plan         = null;
                    $plan_id      = $cart_item_data['depart_deposit']['plan_id'];
                    switch ( $deposit_type ) {
                        case 'global':
                            $plan = get_option( 'depart_payment_plan' )[ $plan_id ] ?? null;
                            break;
                        case 'custom':
                            if ( $product->get_type() === 'variation' ) {
                                // Get main product
                                $product_id = $cart_item_data['depart_deposit']['product_id'];
                                $product    = wc_get_product( $product_id );
                            }
                            $plan = $product->get_meta( 'depart_custom_plans' )[ $plan_id ] ?? null;
                            if ( ! $plan ) {
                                $plan = $product->get_meta( 'depart_exists_plans' )[ $plan_id ] ?? null;
                            }
                            $payment_type = $plan['unit-type'] ?? 'percentage';
                            break;
                    }
                    
                    if ( $plan ) {
                        $deposit        = 0;
                        $remaining      = 0;
                        $fee            = 0;
                        $deposit_fee    = 0;
                        $item_sub_total = wc_format_decimal( floatval( $cart_item_data['line_subtotal'] ), wc_get_price_decimals() );
                        $sub_total_tax  = $cart_item_data['line_subtotal_tax'];
                        $tax_deposit    = 0;
                        $tax_total      = 0;
                        switch ( $payment_type ) {
                            case 'percentage':
                                $deposit     = depart_get_due_amount( $plan['deposit'], $item_sub_total );
                                $tax_deposit = depart_get_tax_amount( $plan['deposit'], $sub_total_tax );
                                $deposit_fee = $fee = depart_get_due_amount( $plan['deposit_fee'], $deposit );
                                foreach ( $plan['plan_schedule'] as $partial ) {
                                    $partial_amount = depart_get_due_amount( $partial['partial'], $item_sub_total );
                                    $fee            += depart_get_due_amount( $partial['fee'], $partial_amount );
                                }
                                break;
                            case 'fixed':
                                $deposit     = $plan['deposit'] * $quantity;
                                $percentage  = $deposit / $item_sub_total;
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
                        if ( isset( $cart_item_data['line_subtotal_tax'] ) ) {
                            $tax_total = $cart_item_data['line_subtotal_tax'];
                        }
                        
                        if ( $deposit < $item_sub_total ) {
                            $cart_item_data['depart_deposit']['enable']  = 1;
                            $cart_item_data['depart_deposit']['deposit'] = $deposit;
                            if ( 'fixed' === $payment_type ) {
                                $cart_item_data['depart_deposit']['future_payment'] = $remaining;
                            } else {
                                $cart_item_data['depart_deposit']['future_payment'] = $item_sub_total - $deposit;
                            }
                            $cart_item_data['depart_deposit']['total']        = $cart_item_data['depart_deposit']['future_payment'] + $deposit;
                            $cart_item_data['depart_deposit']['tax_deposit']  = wc_format_decimal( $tax_deposit, wc_get_price_decimals() );
                            $cart_item_data['depart_deposit']['tax_total']    = $tax_total;
                            $cart_item_data['depart_deposit']['deposit_fee']  = $deposit_fee;
                            $cart_item_data['depart_deposit']['fee_total']    = $fee;
                            $cart_item_data['depart_deposit']['plan']         = $plan;
                            $cart_item_data['depart_deposit']['plan_id']      = $plan_id;
                            $cart_item_data['depart_deposit']['unit-type']    = $payment_type;
                            $cart_item_data['depart_deposit']['deposit_type'] = $deposit_type;
                        } else {
                            $cart_item_data['depart_deposit']['enable'] = 0;
                        }
                        WC()->cart->cart_contents[ $cart_item_key ]['depart_deposit'] = $cart_item_data['depart_deposit'];
                    }
                }
            }
        }
    }
    
    public function depart_cart_totals_after_order_total() {
        if ( isset( WC()->cart->depart_deposit_info['deposit_enabled'] ) && WC()->cart->depart_deposit_info['deposit_enabled'] ) {
            $deposit           = WC()->cart->depart_deposit_info['deposit_amount'];
            $future_payments   = WC()->cart->depart_deposit_info['depart_total'] - $deposit;
            $deposit_fees_html = $remaining_fees_html = '';
            if ( $this->data_store->get_setting( 'show_fees' ) ) {
                $deposit_fees   = (float) isset( WC()->cart->depart_deposit_info['deposit_fee'] ) ? WC()->cart->depart_deposit_info['deposit_fee'] : 0;
                $total_fees     = (float) isset( WC()->cart->depart_deposit_info['fee_total'] ) ? WC()->cart->depart_deposit_info['fee_total'] : 0;
                $remaining_fees = $total_fees - $deposit_fees;
                if ( $deposit_fees > 0 ) {
                    $deposit_fees_html = sprintf( '<small class="order-depart-fee">(+ %s %s)</small>', wc_price( $deposit_fees ), depart_get_text_option( 'fees_text' ) );
                }
                if ( $remaining_fees ) {
                    $remaining_fees_html = sprintf( '<small class="order-depart-fee">(+ %s %s)</small>', wc_price( $remaining_fees ), depart_get_text_option( 'fees_text' ) );
                }
            }
            ?>
            <tr class="order-due">
                <th><?php echo esc_html( depart_get_text_option( 'deposit_payment_text' ) ) ?></th>
                <td
                    data-title="<?php esc_attr_e( 'order-due', 'depart-deposit-and-part-payment-for-woocommerce' ) ?>">
                    <strong><?php echo wp_kses_post( wc_price( $deposit, [ 'decimals' => wc_get_price_decimals() ] ) ) ?></strong>
                    <?php echo wp_kses_post( $deposit_fees_html ) ?>
                </td>
            </tr>
            <tr class="order-rest">
                <th><?php echo esc_html( depart_get_text_option( 'future_payments_text' ) ) ?></th>
                <td
                    data-title="<?php esc_attr_e( 'order-rest', 'depart-deposit-and-part-payment-for-woocommerce' ) ?>">
                    <strong><?php echo wp_kses_post( wc_price( $future_payments, [ 'decimals' => wc_get_price_decimals() ] ) ) ?></strong>
                    <?php echo wp_kses_post( $remaining_fees_html ) ?>
                </td>
            </tr>
            <?php
        }
    }
    
    public function depart_cart_needs_payment( $needs_payment, $cart ) {
        $deposit_enabled = isset( WC()->cart->depart_deposit_info['deposit_enabled'], WC()->cart->depart_deposit_info['deposit_amount'] ) && true === WC()->cart->depart_deposit_info['deposit_enabled'] && WC()->cart->depart_deposit_info['deposit_amount'] <= 0;
        
        if ( $deposit_enabled ) {
            $needs_payment = false;
        }
        
        return $needs_payment;
    }
    
    public function depart_calculate_deposit( $cart ) {
        $items_total    = $cart->get_subtotal();
        $origin_total   = 0;
        $deposit_amount = 0;
        $fee_total      = 0;
        $deposit_fee    = 0;
        
        $is_deposit_cart = false;
        
        $deposit_enabled = false;
        
        foreach ( $cart->get_cart_contents() as $cart_item ) {
            $enabled = $this->check_deposit_enabled( $cart_item['data'] );
            
            if ( $enabled && isset( $cart_item['depart_deposit'], $cart_item['depart_deposit']['deposit'] ) && $cart_item['depart_deposit']['enable'] ) {
                $is_deposit_cart = true;
                $deposit_amount  += $cart_item['depart_deposit']['deposit'];
                $fee_total       += $cart_item['depart_deposit']['fee_total'];
                $deposit_fee     += $cart_item['depart_deposit']['deposit_fee'];
                $origin_total    += $cart_item['depart_deposit']['future_payment'];
            } else {
                $deposit_amount += $cart_item['line_subtotal'];
            }
        }
        $origin_total += $deposit_amount;
        if ( $is_deposit_cart && $deposit_amount < $items_total ) {
            $deposit_enabled = true;
        }
        
        $depart_st = get_option( 'depart_deposit_setting' );
        
        $coupon_handling         = $depart_st['coupon'] ?? 'deposit';
        $fees_handling           = $depart_st['fee'] ?? 'deposit';
        $taxes_handling          = $depart_st['tax'] ?? 'deposit';
        $shipping_handling       = $depart_st['shipping'] ?? 'deposit';
        $shipping_taxes_handling = $depart_st['shipping_tax'] ?? 'deposit';
        
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
        $discount_total = $cart->get_cart_discount_total();
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
        
        switch ( $taxes_handling ) {
            case 'deposit':
                $deposit_taxes = $cart->tax_total;
                break;
            case 'split':
                $deposit_taxes = $deposit_percentage * $cart->tax_total / 100;
                break;
        }
        $remaining_amounts['tax'] = $cart->tax_total - $deposit_taxes;
        
        // fees handling
        
        $fee_taxes = $cart->get_fee_tax();
        switch ( $fees_handling ) {
            case 'deposit':
                $deposit_fees = floatval( $cart->fee_total );
                break;
            
            case 'split':
                $deposit_fees = floatval( $cart->fee_total ) * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['fee'] = $cart->get_fee_total() - $deposit_fees;
        
        // Shipping handling
        
        switch ( $shipping_handling ) {
            case 'deposit':
                $deposit_shipping = $cart->shipping_total;
                break;
            
            case 'split':
                $deposit_shipping = $cart->shipping_total * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['shipping'] = $cart->shipping_total - $deposit_shipping;
        
        // Shipping taxes handling.
        
        switch ( $shipping_taxes_handling ) {
            case 'deposit':
                $deposit_shipping_taxes = $cart->shipping_tax_total;
                break;
            
            case 'split':
                $deposit_shipping_taxes = $cart->shipping_tax_total * $deposit_percentage / 100;
                break;
        }
        $remaining_amounts['shipping_tax'] = $cart->shipping_tax_total - $deposit_shipping_taxes;
        
        $deposit_amount += $deposit_fees + $deposit_taxes + $deposit_shipping + $deposit_shipping_taxes - $deposit_discount;
        $total          = $origin_total + $cart->tax_total + $cart->shipping_total + $cart->shipping_tax_total + $cart->fee_total - $discount_total;
        
        if ( $deposit_amount <= 0 || ( $total + $discount_total - $deposit_amount - $remaining_amounts['discount'] ) <= 0 ) {
            $deposit_enabled = false;
        }
        
        WC()->cart->depart_deposit_info = [
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
        ];
        
        if ( $deposit_enabled ) {
            $payment_schedule                                   = $this->build_payment_schedule( $cart );
            WC()->cart->depart_deposit_info['payment_schedule'] = $payment_schedule;
            do_action( 'depart_add_deposit_data_to_block_cart' );
        }
    }
    
    public function build_payment_schedule( $cart ) {
        $current_date         = new \DateTime();
        $current_date_string  = $current_date->getTimestamp();
        $deposit              = $cart->depart_deposit_info['deposit_amount'];
        $deposit_fee          = $cart->depart_deposit_info['deposit_fee'];
        $total                = $cart->depart_deposit_info['depart_total'];
        $fee_total            = $cart->depart_deposit_info['fee_total'];
        $remaining_amounts    = $cart->depart_deposit_info['depart_remaining'];
        $next_payments        = $total - $deposit;
        $origin_next_payments = $next_payments + $remaining_amounts['discount'] - $remaining_amounts['tax'] - $remaining_amounts['fee'] - $remaining_amounts['shipping'] - $remaining_amounts['shipping_tax'];
        $schedule             = [];
        $plans                = [];
        foreach ( $cart->cart_contents as $cart_item ) {
            if ( isset( $cart_item['depart_deposit'], $cart_item['depart_deposit']['deposit'] ) && $cart_item['depart_deposit']['enable'] ) {
                $plans[] = depart_get_schedule( $cart_item['depart_deposit']['plan'], $cart_item['line_subtotal'] );
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
    
    public function depart_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( $order->get_type() != $this->deposit_type ) {
            $deposit_meta = isset( $values['depart_deposit'] ) ? $values['depart_deposit'] : false;
            if ( $deposit_meta ) {
                $item->add_meta_data( 'depart_deposit_meta', $deposit_meta, true );
            }
        }
    }
    
    public function depart_checkout_update_order_meta( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( $order->get_type() === $this->deposit_type ) {
            return;
        }
        
        if ( isset( WC()->cart->depart_deposit_info, WC()->cart->depart_deposit_info['deposit_enabled'] ) && WC()->cart->depart_deposit_info['deposit_enabled'] ) {
            $deposit        = WC()->cart->depart_deposit_info['deposit_amount'];
            $remaining      = WC()->cart->get_total( 'edit' ) - $deposit;
            $schedule       = WC()->cart->depart_deposit_info['payment_schedule'];
            $interest_total = WC()->cart->depart_deposit_info['fee_total'];
            $extra_options  = WC()->cart->depart_deposit_info['extra_options'];
            $total          = WC()->cart->depart_deposit_info['depart_total'];
            $origin_total   = WC()->cart->depart_deposit_info['depart_original_total'];
            
            $order->add_meta_data( 'depart_deposit_payment_schedule', $schedule, true );
            $order->add_meta_data( '_depart_deposit_amount', $deposit, true );
            $order->add_meta_data( '_depart_future_payment', $remaining, true );
            $order->add_meta_data( '_depart_fee_total', $interest_total, true );
            $order->add_meta_data( '_depart_is_deposit_order', true );
            $order->add_meta_data( '_depart_paid_amount', 0 );
            $order->add_meta_data( '_depart_extra_options', $extra_options );
            $order->add_meta_data( '_depart_total', $total );
            $order->add_meta_data( '_depart_depart_original_total', $origin_total );
            
            $order->save();
        }
    }
    
    public function depart_available_payment_gateways( $available_gateways ) {
        if ( is_checkout() || ( method_exists( wc(), 'is_store_api_request' ) && wc()->is_store_api_request() ) ) {
            $depart_st        = get_option( 'depart_deposit_setting' );
            $pay_slug         = get_option( 'woocommerce_checkout_pay_endpoint', 'order-pay' );
            $order_id         = absint( get_query_var( $pay_slug ) );
            $order            = wc_get_order( $order_id );
            $is_deposit_order = false;
            
            if ( isset( WC()->cart->depart_deposit_info, WC()->cart->depart_deposit_info['deposit_enabled'] ) && WC()->cart->depart_deposit_info['deposit_enabled'] ) {
                $is_deposit_order = true;
            }
            
            if ( $order && $order->get_type() == $this->deposit_type ) {
                $is_deposit_order = true;
            }
            
            if ( $is_deposit_order ) {
                $exclude_payment_methods = $depart_st['exclude_payment_methods'] ?? [];
                foreach ( $available_gateways as $slug => $gateway ) {
                    if ( in_array( $slug, $exclude_payment_methods, true ) ) {
                        unset( $available_gateways[ $slug ] );
                    }
                }
            }
        }
        
        return $available_gateways;
    }
    
    public function depart_get_order_item_totals( $total_rows, $order ) {
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        /* Hide in template of woocommerce email customizer */
        if ( did_action( 'viwec_render_content' ) ) {
            return $total_rows;
        }
        if ( $is_deposit ) {
            $received_slug = get_option( 'woocommerce_checkout_order_received_endpoint', 'order-received' );
            $pay_slug      = get_option( 'woocommerce_checkout_order_pay_endpoint', 'order-pay' );
            $is_checkout   = ( get_query_var( $received_slug ) === '' && is_checkout() );
            $is_email      = did_action( 'woocommerce_email_order_details' ) > 0;
            $total         = floatval( $order->get_meta( '_depart_total' ) );
            $fee_total     = floatval( $order->get_meta( '_depart_fee_total' ) );
            $paid_amount   = floatval( $order->get_meta( '_depart_paid_amount' ) );
            
            /* Check last suborder was paid or not */
            $suborder = null;
            if (property_exists($order, 'suborder') ) {
                $suborder = $order->suborder;
            }
            if ( $suborder && $suborder->is_paid() ) {
                $paid_amount += $suborder->get_total();
            }

            $unpaid_amount = $total + $fee_total - $paid_amount;
            
            /* Reset if unpaid_amount less than 0 */
            
            if ( $unpaid_amount < 0 ) {
                $paid_amount  += $unpaid_amount;
                $unpaid_amount = 0;
            }
            
            $currency_args = [
                'currency' => $order->get_currency(),
                'decimals' => wc_get_price_decimals(),
            ];
            if ( ! $is_checkout || $is_email ) {
                if ( $fee_total ) {
                    $total_rows['depart_fee_total'] = [
                        'label' => __( 'Fees', 'depart-deposit-and-part-payment-for-woocommerce' ),
                        'value' => wc_price( $fee_total, $currency_args ),
                    ];
                }
                $total_rows['depart_paid_amount'] = [
                    'label' => __( 'Paid', 'depart-deposit-and-part-payment-for-woocommerce' ),
                    'value' => wc_price( $paid_amount, $currency_args ),
                ];
                
                $total_rows['depart_remaining_amount'] = [
                    'label' => __( 'Remaining', 'depart-deposit-and-part-payment-for-woocommerce' ),
                    'value' => wc_price( $unpaid_amount , $currency_args ),
                ];
            }
        }
        
        return $total_rows;
    }
    
    public function depart_create_order( $order_id, $checkout ) {
        if ( ! isset( WC()->cart->depart_deposit_info['deposit_enabled'] ) || ! WC()->cart->depart_deposit_info['deposit_enabled'] ) {
            return null;
        }
        
        $data = $checkout->get_posted_data();
        
        try {
            $cart_hash          = WC()->cart->get_cart_hash();
            $order_id           = absint( WC()->session->get( 'order_awaiting_payment' ) );
            $order              = $order_id ? wc_get_order( $order_id ) : null;
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            
            if ( $order && $order->has_cart_hash( $cart_hash ) && $order->has_status( [ 'pending', 'failed' ] ) ) {
                do_action( 'woocommerce_resume_order', $order_id );
                $order->remove_order_items();
            } else {
                $order = new \WC_Order();
            }
            
            $fields_prefix = [
                'shipping' => true,
                'billing'  => true,
            ];
            
            $shipping_fields = [
                'shipping_method' => true,
                'shipping_total'  => true,
                'shipping_tax'    => true,
            ];
            
            foreach ( $data as $key => $value ) {
                if ( is_callable( [ $order, "set_{$key}" ] ) ) {
                    $order->{"set_{$key}"}( $value );
                } elseif ( isset( $fields_prefix[ current( explode( '_', $key ) ) ] ) ) {
                    if ( ! isset( $shipping_fields[ $key ] ) ) {
                        $order->update_meta_data( '_' . $key, $value );
                    }
                }
            }
            $user_agent       = wc_get_user_agent();
            $order_vat_exempt = WC()->cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no';
            
            $order->hold_applied_coupons( $data['billing_email'] );
            $order->set_created_via( 'checkout' );
            $order->set_cart_hash( $cart_hash );
            $order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
            $order->set_currency( get_woocommerce_currency() );
            $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
            $order->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
            $order->set_customer_user_agent( wc_get_user_agent() );
            $order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
            $order->set_payment_method( '' );
            $checkout->set_data_from_cart( $order );
            
            do_action( 'woocommerce_checkout_create_order', $order, $data );
            
            $order_id = $order->save();
            
            do_action( 'depart_checkout_update_order_meta', $order_id, $data );
            
            $order->read_meta_data();
            $payment_schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
            $deposit_id       = null;
            
            if ( $payment_schedule ) {
                foreach ( $payment_schedule as $partial_key => $partial ) {
                    /* Prevent create rubbish suborders when checkout again with same schedule*/
                    $partial_payment = $partial['id'] ? wc_get_order( $partial ['id'] ) : null;
                    if ( ! $partial_payment ) {
                        $partial_payment = new Partial_Order();
                    }
                    
                    $partial_payment->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
                    
                    $amount = $partial['total'];
                    /* translators: Order number*/
                    $name                 = esc_html__( 'Partial Payment for order %s', 'depart-deposit-and-part-payment-for-woocommerce' );
                    $partial_payment_name = apply_filters( 'depart_deposit_partial_payment_name', sprintf( $name, $order->get_order_number() . '-' . ++ $partial_key ), $partial, $order->get_id() );
                    $item                 = new \WC_Order_Item_Fee();
                    
                    $item->set_props( [ 'total' => $amount ] );
                    
                    $item->set_name( $partial_payment_name );
                    $partial_payment->add_item( $item );
                    
                    $partial_payment->set_parent_id( $order->get_id() );
                    $partial_payment->add_meta_data( 'is_vat_exempt', $order_vat_exempt );
                    $partial_payment->add_meta_data( '_depart_partial_payment_type', $partial['type'] );
                    $partial_payment->add_meta_data( '_depart_partial_payment_date', $partial['date'] );
                    $partial_payment->set_currency( get_woocommerce_currency() );
                    $partial_payment->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
                    $partial_payment->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
                    $partial_payment->set_customer_user_agent( $user_agent );
                    $partial_payment->set_total( $amount );
                    $partial_payment->save();
                    $payment_schedule[ -- $partial_key ]['id'] = $partial_payment->get_id();
                    
                    $this->add_apifw_invoice_meta( $partial_payment, $amount, $partial_payment_name );
                    
                    $order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
                    if ( $order_number_meta ) {
                        $partial_payment->add_meta_data( '_alg_wc_full_custom_order_number', $order_number_meta );
                    }
                    
                    //	                 Added for payable payment support
                    foreach ( $data as $key => $value ) {
                        if ( is_callable( [ $order, "set_{$key}" ] ) ) {
                            $partial_payment->{"set_{$key}"}( $value );
                        } elseif ( isset( $fields_prefix[ current( explode( '_', $key ) ) ] ) ) {
                            if ( ! isset( $shipping_fields[ $key ] ) ) {
                                $partial_payment->update_meta_data( '_' . $key, $value );
                            }
                        }
                    }
                    
                    $partial_payment->save();
                    
                    if ( 'deposit' === $partial['type'] ) {
                        $deposit_id = $partial_payment->get_id();
                        $partial_payment->set_payment_method( $available_gateways[ $data['payment_method'] ] ?? $data['payment_method'] );
                        $partial_payment->save();
                    }
                }
            }
            $order->update_meta_data( 'depart_deposit_payment_schedule', $payment_schedule );
            $order->save();
            
            return absint( $deposit_id );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'checkout-error', $e->getMessage() );
        }
    }
    
    public function depart_payment_complete_reduce_order_stock( $trigger_reduce, $order_id ) {
        if ( ! $trigger_reduce ) {
            return false;
        }
        
        $order = wc_get_order( $order_id );
        if ( $order->get_type() == $this->deposit_type ) {
            return false;
        }
        
        if ( $order->get_meta( '_depart_is_deposit_order' ) ) {
            $status = $order->get_status();
            global $depart_settings;
            
            $reduce_on             = $depart_settings['reduce_stock_status'];
            $deposit_statuses      = [ 'installment', 'on-hold' ];
            $full_payment_statuses = [ 'processing', 'complete' ];
            if ( in_array( $status, $deposit_statuses ) && 'full' === $reduce_on ) {
                $trigger_reduce = false;
            } elseif ( in_array( $status, $full_payment_statuses ) && 'deposit' === $reduce_on ) {
                $trigger_reduce = false;
            }
        }
        
        return $trigger_reduce;
    }
    
    public function depart_my_account_my_orders_actions( $actions, $order ) {
        $settings         = get_option( 'depart_deposit_setting' );
        $payment_disorder = false;
        if ( isset( $settings['free_partial_charge'] ) ) {
            $payment_disorder = $settings['free_partial_charge'];
        }
        $status     = $order->get_status();
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        if ( 'installment' === $status || 'pending' === $status || $payment_disorder || 'overdue' === $status || 'failed' === $status ) {
            $args = [
                'post_parent'     => $order->get_id(),
                'parent_order_id' => $order->get_id(),
                'post_type'       => $this->deposit_type,
                'numberposts'     => - 1,
                'post_status'     => 'pending',
                'orderby'         => 'ID',
                'order'           => 'ASC',
            ];
            
            $order_need_payment = wc_get_orders( $args )[0] ?? null;
            
            if ( $order_need_payment && $is_deposit ) {
                $checkout_url = $order_need_payment->get_checkout_payment_url();
                
                $actions['pay_partial'] = [
                    'url'  => esc_url( $checkout_url ),
                    'name' => __( 'Pay', 'depart-deposit-and-part-payment-for-woocommerce' ),
                ];
            }
        }
        
        return $actions;
    }
    
    public function depart_rewrite_order_tails_table( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_type() == $this->deposit_type ) {
            remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
            remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
            if ( apply_filters( 'depart_disable_orders_details_table', true ) ) {
                $order_id = $order->get_parent_id();
                wc_get_template( 'order/partial-order-details.php', [ 'order_id' => $order_id ], '', $this->template_url );
            }
        }
    }
    
    public function render_auto_payment_option( $order ) {
        if ( Auto_Payment::is_available() ) {
            wc_get_template( 'payment/payment-table.php', [ 'order' => $order ], '', $this->template_url );
        }
    }
    
    public function depart_order_details_after_order_table( $order ) {
        $is_deposit_order = $order->get_meta( '_depart_is_deposit_order' );
        if ( $is_deposit_order ) {
            $schedule = depart_get_schedule_payments_summary( $order );
            wc_get_template( 'plan/schedule-payments-summary.php', [
                'schedule' => $schedule,
                'order'    => $order,
            ], '', $this->template_url );
        }
    }
    
    public function depart_get_checkout_payment_url( $url, $order ) {
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        
        if ( $is_deposit && $order->get_type() !== $this->deposit_type ) {
            $schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
            
            if ( is_array( $schedule ) && ! empty( $schedule ) ) {
                foreach ( $schedule as $payment ) {
                    $payment_order = wc_get_order( $payment['id'] );
                    
                    if ( ! $payment_order ) {
                        continue;
                    }
                    
                    if ( ! $payment_order || ! $payment_order->needs_payment() ) {
                        continue;
                    }
                    $url = $payment_order->get_checkout_payment_url();
                    $url = add_query_arg( [ 'payment' => $payment['type'], ], $url );
                    break;
                }
            }
        }
        
        return $url;
    }
    
    public function depart_order_status_changed( $order_id, $old_status, $new_status, $order ) {

        if ( $old_status === $new_status ) {
            return;
        }
        
        if ( $order && $order->get_type() == $this->deposit_type ) {
            $parent          = wc_get_order( $order->get_parent_id() );
            $parent_id       = $parent->get_id();
            $order_total     = floatval( $parent->get_meta( '_depart_total' ) );
            $suborders_total = 0;
            $args            = [
                'post_parent'     => $parent_id,
                'parent_order_id' => $parent_id,
                'post_type'       => $this->deposit_type,
                'numberposts'     => - 1,
            ];

            $suborders   = wc_get_orders( $args );
            $has_overdue = false;

            foreach ( $suborders as $suborder ) {
                if ( 'overdue' === $suborder->get_status() ) {
                    $has_overdue = true;
                } elseif ( $suborder->is_paid() ) {
                    $suborders_total += $suborder->get_total();
                }
            }
            $parent->update_meta_data( '_depart_paid_amount', $suborders_total );

            if ( $has_overdue ) {
                $parent->set_status( 'overdue' );
            } elseif ( $order->is_paid() ) {
                if ( $suborders_total >= $order_total ) {
                    $status = $this->data_store->get_setting( 'paid_full_status' );
                    $parent->set_status( $status );
                } else {
                    $parent->set_status( 'installment' );
                }
            } else {
                if ( 'pending' === $new_status && $suborders_total > 0 ) {
                    $parent->set_status( 'installment' );
                } elseif ( in_array( $new_status, [
                    'on-hold',
                    'overdue',
                    'failed',
                ] ) ) {
                    $parent->set_status( $new_status );
                }
            }
            $parent->save();
        }
    }
    
    public function depart_email_display_payment_schedule( $order, $sent_to_admin, $plain_text, $email ) {
        /* Hide in template of woocommerce email customizer */
        if ( did_action( 'viwec_render_content' ) ) {
            return;
        }
        if ( $order ) {
            $is_deposit_order = $order->get_meta( '_depart_is_deposit_order' );
            if ( $is_deposit_order ) {
                $schedule = depart_get_schedule_payments_summary( $order );
                if ( ! $plain_text ) {
                    wc_get_template( 'emails/email-schedule-payments-summary.php', [ 'schedule' => $schedule ], '', $this->template_url );
                }
            }
        }
    }
    
    public function depart_disable_suborder_email_notification( $enabled, $order, $email ) {
        $exclude_emails = array( 'new_order', 'customer_completed_order' );
        if ( $order && $order->get_parent_id() > 0 ) {
            return false; // Disable the email notification for sub-orders
        }
        
        if ( ! in_array( $email->id, $exclude_emails ) ) {
            if ( $order && $order->get_meta( '_depart_is_deposit_order' ) ) {
                return false;
            }
        }
        
        return $enabled;
    }
    
    public function depart_email_action( $email_actions ) {
        $email_actions[] = 'woocommerce_order_status_pending_to_installment';
        $email_actions[] = 'woocommerce_order_status_failed_to_installment';
        $email_actions[] = 'woocommerce_order_status_installment_to_processing';
        $email_actions[] = 'woocommerce_order_status_on-hold_to_completed';
        
        return $email_actions;
    }
    
    public function depart_email_new_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order->get_type() === $this->deposit_type ) {
            return;
        }
        $emails = WC()->mailer()->get_emails();
        $emails['WC_Email_New_Order']->trigger( $order_id, $order );
        $emails['WC_Email_Customer_Processing_Order']->trigger( $order_id, $order );
    }
    
    public function depart_disable_deposit_order_notification( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_meta( '_depart_is_deposit_order' ) ) {
            add_filter( 'woocommerce_email_enabled_customer_processing_order', function( $enabled ) {
                return false;
            } );
        }
    }
    
    function add_apifw_invoice_meta( $partial_payment, $amount, $name ) {
        $data = [
            'id'                    => false,
            'subtotal'              => $amount,
            'subtotal_tax'          => 0,
            'total'                 => $amount,
            'total_tax'             => 0,
            'price'                 => $amount,
            'price_after_discount'  => $amount,
            'quantity'              => '',
            'weight'                => '',
            'total_weight'          => '',
            'weight_unit'           => '',
            'tax_class'             => '',
            'tax_status'            => '',
            'tax_percent'           => 0,
            'tax_label'             => '',
            'tax_pair'              => '',
            'tax_array'             => '',
            'name'                  => $name,
            'product_id'            => '',
            'variation_id'          => '',
            'product_url'           => '',
            'product_thumbnail_url' => '',
            'sku'                   => '',
            'meta'                  => '',
            'formatted_meta'        => '',
            'raw_meta'              => '',
            'category'              => '',
        ];
        
        $partial_payment->add_meta_data( 'depart_apifw_invoice_meta', $data );
    }
    
    public function depart_modify_cart_data() {
        $stream = file_get_contents( 'php://input' );
        $json   = json_decode( $stream, true );
        if ( isset( $json['context'] ) && in_array( $json['context'], array( 'cart', 'checkout','cart-block','checkout-block' ), true ) ) {
            $this->depart_calculate_deposit( WC()->cart );
            
            if ( isset( WC()->cart->depart_deposit_info, WC()->cart->depart_deposit_info['deposit_enabled'] )
                 && WC()->cart->depart_deposit_info['deposit_enabled'] !== true ) {
                return;
            }
            $amount = WC()->cart->depart_deposit_info['deposit_amount'] + WC()->cart->depart_deposit_info['deposit_fee'];
            WC()->cart->set_total( $amount );
        }
    }
    
    public function depart_add_payment_token_id( $method, $token ) {
        $token_type = Auto_Payment::identify_gateway_id( $method['method']['gateway'] );
        
        if ( ! empty( $token_type ) ) {
            $method['token_id'] = $token->get_id();
        }
        
        return $method;
    }
    
    public function depart_set_order_auto_payment() {
        if ( ! isset( $_GET['payment_token_id'], $_GET['nonce'], $_GET['method'], $_GET['order_id'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), '_depart_nonce' ) ) {
            wp_send_json_error();
        }
        
        $token_id = sanitize_text_field( wp_unslash( $_GET['payment_token_id'] ) );
        $method   = sanitize_text_field( wp_unslash( $_GET['method'] ) );
        $order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
        
        if ( ! in_array( $method, [ 'add', 'remove' ] ) ) {
            wp_send_json_error();
        }
        
        $token = WC_Payment_Tokens::get( $token_id );
        
        if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() ) {
            wp_send_json_error( 'Invalid payment method.', 'depart-deposit-and-part-payment-for-woocommerce' );
        }
        
        $order      = wc_get_order( $order_id );
        $is_deposit = $order->get_meta( '_depart_is_deposit_order' );
        
        if ( ! $order || ! $is_deposit ) {
            wp_send_json_error();
        }
        
        if ( 'add' === $method ) {
            $order->update_meta_data( '_depart_auto_payment_token_id', $token_id );
        } elseif ( 'remove' === $method ) {
            $order->delete_meta_data( '_depart_auto_payment_token_id' );
        } else {
            wp_send_json_error();
        }
        $order->save();
        wp_send_json_success();
    }
    
}