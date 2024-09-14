<?php

namespace VicoDIn\Inc;

use Automattic\WooCommerce\Caches\OrderCache;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

class Partial_Order extends \WC_Order {
    
    function get_type() {
        return DEPART_CONST['order_type'];
    }
    
    public function is_editable() {
        return false;
    }
    
    public function get_order_number() {
        global $depart_settings;
        $parent_id        = $this->get_parent_id();
        $parent_order     = wc_get_order( $parent_id );
        $schedule         = $parent_order->get_meta( 'depart_deposit_payment_schedule' );
        $partial_order_id = $this->get_id();
        $sub              = 0;
        
        if ( is_array( $schedule ) && ! empty( $schedule ) ) {
            foreach ( $schedule as $index => $payment ) {
                if ( $payment['id'] == $partial_order_id ) {
                    $sub = $index + 1;
                }
            }
        }
        
        if ( $depart_settings['rewrite_suborder_number'] && 0 != $sub ) {
            return (string) apply_filters( 'depart_order_number', $parent_id . '-' . $sub, $this );
        } else {
            return $this->get_id();
        }
    }
    
    public function is_unpaid() {
        return $this->has_status( [ 'pending', 'failed' ] );
    }
    
    public function save_without_status_transition() {
        
        $this->maybe_set_user_billing_email();
        if ( ! $this->data_store ) {
            return $this->get_id();
        }
        
        try {
            /**
             * Trigger action before saving to the DB. Allows you to adjust object props before save.
             *
             * @param WC_Data          $this The object being saved.
             * @param WC_Data_Store_WP $data_store THe data store persisting the data.
             */
            do_action( 'woocommerce_before_' . $this->object_type . '_object_save', $this, $this->data_store );
            
            if ( $this->get_id() ) {
                $this->data_store->update( $this );
            } else {
                $this->data_store->create( $this );
            }
            
            $this->save_items();
            
            if ( OrderUtil::orders_cache_usage_is_enabled() ) {
                $order_cache = wc_get_container()->get( OrderCache::class );
                $order_cache->remove( $this->get_id() );
            }
            
            /**
             * Trigger action after saving to the DB.
             *
             * @param WC_Data          $this The object being saved.
             * @param WC_Data_Store_WP $data_store THe data store persisting the data.
             */
            do_action( 'woocommerce_after_' . $this->object_type . '_object_save', $this, $this->data_store );
            
        } catch ( \Exception $e ) {
            $message_id = $this->get_id() ? $this->get_id() : __( '(no ID)', 'woocommerce' );
            $this->handle_exception(
                $e,
                wp_kses_post(
                    sprintf(
                    /* translators: 1: Order ID or "(no ID)" if not known. */
                        __( 'Error saving order ID %1$s.', 'woocommerce' ),
                        $message_id
                    )
                )
            );
        }
        
        return $this->get_id();
    }
    
    public function trigger_status_transition() {
        $this->status_transition();
    }
}

