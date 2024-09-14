<?php

namespace VicoDIn\Inc\Payment;

use Exception;
use WC_Payment_Tokens;

defined( 'ABSPATH' ) || exit;

class Auto_Payment {
    
    static $instance = null;
    
    public $gateways = array();
    
    public function __construct() {
        $gateways = array();
        if ( is_plugin_active( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ) ) {
            $gateways['paypal'] = PPCP_Gateway::instance();
        }
        if ( is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
            $gateways['stripe'] = Stripe_Gateway::instance();
        }
        if ( is_plugin_active( 'woocommerce-square/woocommerce-square.php' ) ) {
            $gateways['square'] = Square_Gateway::instance();
        }
        $this->gateways = $gateways;
    }
    
    public static function instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public static function is_available() {
        global $depart_settings;
        
        if ( ! $depart_settings['auto_charge'] ) {
            return false;
        }
        if ( empty( self::instance()->get_available_gateways() ) ) {
            return false;
        }
        
        return true;
    }
    
    public function get_available_gateways() {
        $available_gateways = array();
        $_gateways          = $this->gateways;
        if ( is_array( $_gateways ) && ! empty( $_gateways ) ) {
            foreach ( $_gateways as $name => $gateway ) {
                if ( $gateway->is_available() ) {
                    $available_gateways[ $name ] = $gateway;
                }
            }
        }
        
        return $available_gateways;
    }
    
    public static function process_payment( $order ) {
        $order = wc_get_order( $order );
        if ( ! $order || $order->get_type() !== DEPART_CONST['order_type'] || ! self::is_available() ) {
            return;
        }
        
        if ( $order->needs_payment() ) {
            $main_order = wc_get_order( $order->get_parent_id() );
            if ( $main_order ) {
                try {
                    $token_id = $main_order->get_meta( '_depart_auto_payment_token_id' );
                    
                    if ( $token_id ) {
                        $token = WC_Payment_Tokens::get( $token_id );
                        if ( ! $token ) {
                            throw new Exception( __( 'Invalid token id.', 'depart-deposit-and-part-payment-for-woocommerce' ) );
                        }
                        $gateway_id         = $token->get_gateway_id();
                        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        $payment_method     = isset( $available_gateways[ $gateway_id ] ) ? $available_gateways[ $gateway_id ] : false;
                        if ( ! $payment_method ) {
                            return;
                        }
                        $order->set_payment_method( $payment_method );
                        $order->update_meta_data( '_payment_token', $token_id );
                        $order->save();
                        $payment_method->validate_fields();
                        $gateways   = self::instance()->get_available_gateways();
                        $token_type = self::identify_gateway_id( $gateway_id );
                        if ( empty( $gateways[ $token_type ] ) ) {
                            throw new Exception( __( 'Gateway is not supported!.', 'depart-deposit-and-part-payment-for-woocommerce' ) );
                        }
                        $gateways[ $token_type ]->process_part_payment( $order, $token );
                    }
                } catch ( \Exception $e ) {
                    $order->update_status( 'failed' );
                }
            }
        }
    }
    
    public static function identify_gateway_id( $gateway_id ) {
        $gateway            = '';
        $available_gateways = self::instance()->get_available_gateways();
        if ( str_starts_with( $gateway_id, 'ppcp' ) ) {
            $gateway = 'paypal';
        } elseif ( str_starts_with( $gateway_id, 'stripe' ) ) {
            $gateway = 'stripe';
        } elseif ( str_starts_with( $gateway_id, 'square' ) ) {
            $gateway = 'square';
        }
        
        if ( ! isset( $available_gateways[ $gateway ] ) ) {
            $gateway = '';
        }
        
        return $gateway;
    }
    
    public static function is_switchable() {
        if ( empty( self::instance()->get_available_gateways() ) ) {
            return false;
        }else {
            return true;
        }
    }
    
}
