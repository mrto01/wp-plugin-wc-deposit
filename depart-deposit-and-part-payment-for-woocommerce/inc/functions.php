<?php

defined( 'ABSPATH' ) || exit;

function depart_get_schedule( $plan, $price ) {
    $schedule_formatted = [];
    $current_date       = new DateTime();
    $schedule           = $plan['plan_schedule'];
    $unit               = $plan['unit-type'] ?? 'percentage';
    $total              = 0.0;
    $deposit            = depart_get_due_amount( $plan['deposit'], $price, $unit );
    
    foreach ( $schedule as $partial ) {
        $after     = empty( $partial['after'] ) ? '0' : $partial['after'];
        $date_type = $partial['date_type'] ?? 'month';
        $amount    = $partial['partial'];
        $fee       = $partial['fee'];
        
        if ( 'month' === $date_type ) {
            $current_date->modify( '+' . $after . ' months' );
        } elseif ( 'day' === $date_type ) {
            $current_date->modify( '+' . $after . ' days' );
        } elseif ( 'year' === $date_type ) {
            $current_date->modify( '+' . $after . ' years' );
        }
        
        $due_date = $current_date->getTimestamp();
        
        $amount = depart_get_due_amount( $amount, $price, $unit );
        $total  += $amount;
        
        $fee = depart_get_due_amount( $fee, $amount, $unit );
        
        $schedule_formatted[] = [
            'date'   => $due_date,
            'fee'    => $fee,
            'amount' => $amount,
        ];
    }
    if ( $unit === 'percentage' ) {
        // make schedule equal price absolutely
        $difference                                = $price - ( $total + $deposit );
        $last_key                                  = key( array_slice( $schedule_formatted, - 1, 1, true ) );
        $schedule_formatted[ $last_key ]['amount'] += $difference;
    }
    
    return $schedule_formatted;
}

function depart_get_schedule_include_tax( $schedule_formatted, $tax_total, $future_payment ) {
    if ( $tax_total > 0 ) {
        $total = 0;
        foreach ( $schedule_formatted as $index => $payment ) {
            $percent                      = $payment['amount'] * 100 / $future_payment;
            $tax                          = depart_get_due_amount( $percent, $tax_total );
            $payment['amount']            += $tax;
            $total                        += $payment['amount'];
            $schedule_formatted[ $index ] = $payment;
        }
        $difference                                = ( $future_payment + $tax_total ) - $total;
        $last_key                                  = key( array_slice( $schedule_formatted, - 1, 1, true ) );
        $schedule_formatted[ $last_key ]['amount'] += $difference;
    }
    
    return $schedule_formatted;
}

function depart_get_due_amount( $amount, $price, $unit = 'percentage' ) {
    if ( 'fixed' === $unit ) {
        return (float) apply_filters( 'depart_get_due_amount', $amount );
    }
    $amount = floatval( $amount ) * floatval( $price ) / 100;
    
    return round( $amount, wc_get_price_decimals() );
}

function depart_get_suborder_needs_payment( $order ) {
    $suborder = wc_get_orders( [
        'type'            => DEPART_CONST['order_type'],
        'post_parent'     => $order->get_id(),
        'parent_order_id' => $order->get_id(),
        'numberposts'     => - 1,
        'post_status'     => [ 'pending', 'failed' ],
        'orderby'         => 'ID',
        'order'           => 'ASC',
    ] );
    
    $suborder = $suborder[0] ?? null;
    
    return $suborder;
}

function depart_check_woocommerce_cot() {
    return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

function depart_get_schedule_payments_summary( $order ) {
    $settings         = get_option( 'depart_deposit_setting' );
    $payment_disorder = false;
    if ( isset( $settings['free_partial_charge'] ) ) {
        $payment_disorder = $settings['free_partial_charge'];
    }
    $schedule            = $order->get_meta( 'depart_deposit_payment_schedule' );
    $parent_order_status = $order->get_status();
    $pay_enable          = true;
    $payment_details     = [];
    if ( is_array( $schedule ) && ! empty( $schedule ) ) {
        foreach ( $schedule as $payment ) {
            $partial_payment = null;
            if ( isset( $payment['id'] ) && ! empty( $payment['id'] ) ) {
                $partial_payment = wc_get_order( $payment['id'] );
            }
            if ( ! is_a( $partial_payment, 'WC_Order' ) ) {
                continue;
            }
            $order_id            = $payment['id'];
            $payment_id          = $partial_payment->get_order_number();
            $payment_date        = date_i18n( wc_date_format(), $payment['date'] );
            $payment_status      = $partial_payment->get_status();
            $payment_status_name = wc_get_order_status_name( $payment_status );
            $payment_method      = $partial_payment->get_payment_method_title();
            $amount              = wc_price( $partial_payment->get_total(), array( 'currency' => $partial_payment->get_currency() ) );
            $checkout_url        = $partial_payment->get_checkout_payment_url();
            $edit_url            = $partial_payment->get_edit_order_url();
            $payable             = false;
            if ( $payment_disorder && $partial_payment->is_unpaid() ) {
                $payable = true;
            } elseif ( $partial_payment->is_unpaid() && $pay_enable && $parent_order_status === 'installment' ) {
                $payable    = true;
                $pay_enable = false;
            }
            $payment_details[] = [
                'ID'             => $order_id,
                'id'             => $payment_id,
                'due_date'       => $payment_date,
                'status'         => $payment_status,
                'status_name'    => $payment_status_name,
                'payment_method' => $payment_method,
                'amount'         => $amount,
                'checkout_url'   => $checkout_url,
                'edit_url'       => $edit_url,
                'payable'        => $payable,
            ];
        }
    }
    
    return $payment_details;
}

function depart_get_current_lang() {
    $current_lang = '';
    if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
        $current_lang = wpml_get_current_language();
    } elseif ( class_exists( 'Polylang' ) ) {
        $current_lang = pll_current_language();
    }
    
    return $current_lang;
}

function depart_get_text_option( $option, $data = null ) {
    if ( null == $data ) {
        global $depart_settings;
        $data = $depart_settings;
    }
    if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
        $current_lang = wpml_get_current_language();
        if ( isset( $data[ $option . '_' . $current_lang ] ) ) {
            return esc_html( $data[ $option . '_' . $current_lang ] );
        }
    } elseif ( class_exists( 'Polylang' ) ) {
        $current_lang = pll_current_language();
        if ( isset( $data[ $option . '_' . $current_lang ] ) ) {
            return esc_html( $data[ $option . '_' . $current_lang ] );
        }
    }
    
    return esc_html( $data[ $option ] );
}

function depart_get_tax_amount( $tax_rate, $tax ) {
    global $depart_settings;
    $tax_handling = $depart_settings['tax'];
    $tax_amount   = 0;
    if ( 'split' === $tax_handling ) {
        $tax_amount = ( $tax * $tax_rate ) / 100;
    } elseif ( 'deposit' === $tax_handling ) {
        $tax_amount = $tax;
    }
    
    return $tax_amount;
}

function depart_display_tax( $page ) {
    global $depart_settings;
    
    if ( 'shop' === $page ) {
        $display = $depart_settings[ 'tax_display_' . $page ];
    } else {
        $display = $depart_settings[ 'tax_display_' . $page ];
    }
    
    if ( 'default' === $display ) {
        $display = get_option( 'woocommerce_tax_display_' . $page );
    }
    
    return $display;
}

function depart_get_ancestors( $categories ) {
    $ancestors = [];
    if ( is_array( $categories ) && ! empty( $categories ) ) {
        foreach ( $categories as $cat_id ) {
            $cat_ancestor_ids = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
            $ancestors        = array_merge( $ancestors, $cat_ancestor_ids );
        }
    }
    
    return array_merge( $categories, $ancestors );
}

function depart_get_available_plans() {
    $plans           = get_option( 'depart_payment_plan', [] );
    $available_plans = [];
    if ( ! empty( $plans ) ) {
        foreach ( $plans as $id => $plan ) {
            if ( $plan['plan_active'] ) {
                $available_plans[ $id ] = $plan;
            }
        }
    }
    
    return $available_plans;
}

function depart_check_viredis_enable() {
    if ( is_plugin_active( 'redis-woo-dynamic-pricing-and-discounts/redis-woo-dynamic-pricing-and-discounts.php' ) ) {
        $pd_data = VIREDIS_DATA::get_instance();
        if ( $pd_data->get_params('pd_enable') ) {
            return true;
        }
    }
    
    return false;
}

function depart_wmc_convert_order_total_to_base_price( $order ) {
    $wmc_order_info = $order->get_meta( 'wmc_order_info' );
    $rate           = 1;
    $order_currency = $order->get_currency();
    
    if ( is_array( $wmc_order_info ) && !empty( $wmc_order_info ) ) {
        if ( isset( $wmc_order_info[ $order_currency ]['rate'] ) ) {
            $rate = $wmc_order_info[ $order_currency ]['rate'];
        }
    }
    return $order->get_total() / $rate;
}
