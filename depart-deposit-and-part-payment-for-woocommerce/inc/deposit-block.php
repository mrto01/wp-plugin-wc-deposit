<?php

namespace VicoDIn\Inc;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Utilities\NoticeHandler;

defined( 'ABSPATH' ) || exit;

class Deposit_Block {
    
    protected static $instance = null;
    public $deposit_order = null;
    
    private function __construct() {
        add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'register_deposit_summary_block' ) );
        add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_deposit_summary_block' ) );
        add_action( 'depart_add_deposit_data_to_block_cart', array( $this, 'add_deposit_data_to_cart' ) );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_deposit_order' ) );
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function register_deposit_summary_block( $integration_registry ) {
        $integration_registry->register( new Deposit_Block_Integration() );
    }
    
    public function get_deposit_data() {
        global $depart_settings;
        $deposit_info = WC()->cart->depart_deposit_info;
        $decimals     = wc_get_price_decimals();
        $show_fees    = $depart_settings['show_fees'];
        
        return array(
            'deposit_amount'       => $this->prepare_money_response( $deposit_info['deposit_amount'], $decimals ),
            'future_payment'       => $this->prepare_money_response( $deposit_info['depart_total'] - $deposit_info['deposit_amount'], $decimals ),
            'fee_total'            => $this->prepare_money_response( $deposit_info['fee_total'], $decimals ),
            'deposit_fee'          => $this->prepare_money_response( $deposit_info['deposit_fee'] ),
            'show_fees'            => $show_fees,
            'deposit_text'         => depart_get_text_option( 'deposit_payment_text' ),
            'future_payments_text' => depart_get_text_option( 'future_payments_text' ),
            'fees_text'            => depart_get_text_option( 'fees_text' ),
        );
    }
    
    public function get_deposit_data_properties() {
        return array(
            'deposit_amount'       => [
                'description' => __( 'Deposit amount of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'number',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'future_payment'       => [
                'description' => __( 'Future payments of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'number',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'fee_total'            => [
                'description' => __( 'Total fee of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'number',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'deposit_text'         => [
                'description' => __( 'Label for deposit amount of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'text',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'future_payments_text' => [
                'description' => __( 'Label for future payments of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'text',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
            'fees_text'            => [
                'description' => __( 'Label for fees of the cart.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'text',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
        
        );
    }
    
    public function add_deposit_data_to_cart() {
        woocommerce_store_api_register_endpoint_data( array(
            'endpoint'        => CartSchema::IDENTIFIER,
            'namespace'       => 'depositData',
            'data_callback'   => array( $this, 'get_deposit_data' ),
            'schema_callback' => array( $this, 'get_deposit_data_properties' ),
            'schema_type'     => ARRAY_A,
        ) );
    }
    
    public function prepare_money_response( $amount, $decimals = 2, $rounding_mode = PHP_ROUND_HALF_UP ) {
        $extend = StoreApi::container()->get( ExtendSchema::class );
        
        return $extend->get_formatter( 'money' )->format( $amount, [
            'decimals'      => $decimals,
            'rounding_mode' => $rounding_mode,
        ] );
    }
    
    public function process_deposit_order( \WC_Order $order ) {
        if ( ! isset( WC()->cart->depart_deposit_info['deposit_enabled'] ) || ! WC()->cart->depart_deposit_info['deposit_enabled'] ) {
            return;
        }
        
        try {
            $user_agent         = wc_get_user_agent();
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $data               = $order->get_data();
            /* Clear old schedule if exist*/
            $this->clear_schedule( $order );
            do_action( 'depart_checkout_update_order_meta', $order->get_id() );
            $order->read_meta_data();
            $payment_schedule = $order->get_meta( 'depart_deposit_payment_schedule' );
            
            if ( $payment_schedule ) {
                foreach ( $payment_schedule as $partial_key => $partial ) {
                    $partial_payment = new Partial_Order();
                    
                    $amount = $partial['total'];
                    /* translators: Order number*/
                    $name                 = esc_html__( 'Partial Payment for order %s', 'depart-deposit-and-part-payment-for-woocommerce' );
                    $partial_payment_name = apply_filters( 'depart_deposit_partial_payment_name', sprintf( $name, $order->get_order_number() . '-' . ++ $partial_key ), $partial, $order->get_id() );
                    $item                 = new \WC_Order_Item_Fee();
                    
                    $item->set_props( array(
                        'total' => $amount,
                    ) );
                    $item->set_name( $partial_payment_name );
                    $partial_payment->add_item( $item );
                    $partial_payment->set_created_via( 'store-api' );
                    $this->update_address_from_cart( $partial_payment );
                    $partial_payment->set_currency( get_woocommerce_currency() );
                    $partial_payment->set_parent_id( $order->get_id() );
                    $partial_payment->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
                    $partial_payment->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
                    $partial_payment->set_customer_user_agent( $user_agent );
                    $order->update_meta_data( 'is_vat_exempt', wc()->cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no' );
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
                    if ( 'deposit' === $partial['type'] ) {
                        $this->deposit_order = $partial_payment;
                        $partial_payment->set_payment_method( $available_gateways[ $data['payment_method'] ] ?? $data['payment_method'] );
                        $partial_payment->save();
                    }
                }
                $order->update_meta_data( 'depart_deposit_payment_schedule', $payment_schedule );
                $order->set_payment_method( '' );
                $order->set_payment_method_title( '' );
                $order->save();
                add_action( 'woocommerce_rest_checkout_process_payment_with_context', array(
                    $this,
                    'process_deposit_payment',
                ), 10, 2 );
            }
        } catch ( \Exception $e ) {
            wp_send_json_error( 'checkout-error', $e->getMessage() );
        }
    }
    
    public function process_deposit_payment( $context, &$result ) {
        if ( $result->status ) {
            return;
        }
        
        $context->set_order( $this->deposit_order );
        
        //		$post_data = $_POST;
        
        // Set constants.
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
        
        // Add the payment data from the API to the POST global.
        $_POST = $context->payment_data;
        
        // Call the process payment method of the chosen gateway.
        $payment_method_object = $context->get_payment_method_instance();
        
        if ( ! $payment_method_object instanceof \WC_Payment_Gateway ) {
            return;
        }
        
        $payment_method_object->validate_fields();
        
        // If errors were thrown, we need to abort.
        NoticeHandler::convert_notices_to_exceptions( 'woocommerce_rest_payment_error' );
        
        // Process Payment.
        $gateway_result = $payment_method_object->process_payment( $context->order->get_id() );
        
        // Restore $_POST data.
        //		$_POST = $post_data;
        
        // If `process_payment` added notices, clear them. Notices are not displayed from the API -- payment should fail,
        // and a generic notice will be shown instead if payment failed.
        wc_clear_notices();
        
        // Handle result.
        $result->set_status( isset( $gateway_result['result'] ) && 'success' === $gateway_result['result'] ? 'success' : 'failure' );
        
        // set payment_details from result.
        $result->set_payment_details( array_merge( $result->payment_details, $gateway_result ) );
        $result->set_redirect_url( $gateway_result['redirect'] );
    }
    
    public function update_address_from_cart( $order ) {
        $order->set_props( [
            'billing_first_name'  => wc()->customer->get_billing_first_name(),
            'billing_last_name'   => wc()->customer->get_billing_last_name(),
            'billing_company'     => wc()->customer->get_billing_company(),
            'billing_address_1'   => wc()->customer->get_billing_address_1(),
            'billing_address_2'   => wc()->customer->get_billing_address_2(),
            'billing_city'        => wc()->customer->get_billing_city(),
            'billing_state'       => wc()->customer->get_billing_state(),
            'billing_postcode'    => wc()->customer->get_billing_postcode(),
            'billing_country'     => wc()->customer->get_billing_country(),
            'billing_email'       => wc()->customer->get_billing_email(),
            'billing_phone'       => wc()->customer->get_billing_phone(),
            'shipping_first_name' => wc()->customer->get_shipping_first_name(),
            'shipping_last_name'  => wc()->customer->get_shipping_last_name(),
            'shipping_company'    => wc()->customer->get_shipping_company(),
            'shipping_address_1'  => wc()->customer->get_shipping_address_1(),
            'shipping_address_2'  => wc()->customer->get_shipping_address_2(),
            'shipping_city'       => wc()->customer->get_shipping_city(),
            'shipping_state'      => wc()->customer->get_shipping_state(),
            'shipping_postcode'   => wc()->customer->get_shipping_postcode(),
            'shipping_country'    => wc()->customer->get_shipping_country(),
            'shipping_phone'      => wc()->customer->get_shipping_phone(),
        ] );
    }
    
    public function clear_schedule( \WC_Order $order ) {
        $args            = [
            'post_parent'     => $order->get_id(),
            'parent_order_id' => $order->get_id(),
            'post_type'       => DEPART_CONST['order_type'],
            'numberposts'     => - 1,
        ];
        
        $payments = wc_get_orders( $args );
        
        if ( is_array( $payments ) && ! empty( $payments ) ) {
            
            add_filter( 'depart_enable_delete_suborder', function( $enable ) {
                return true;
            } );
            
            foreach ( $payments as $payment ) {
                $payment->delete( true );
            }
        }
    }
   
    
}
