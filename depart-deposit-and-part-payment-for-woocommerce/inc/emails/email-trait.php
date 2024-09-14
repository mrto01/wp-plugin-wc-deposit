<?php

namespace VicoDIn\Inc\Emails;

defined( 'ABSPATH' ) || exit;

trait Email_Trait {
    
    public function declare_placeholders() {
        $this->placeholders = [
            '{order_date}'              => '',
            '{wdp_parent_order_number}' => '',
            '{wdp_suborder_number}'     => '',
            '{wdp_part_payment_link}'   => '',
            '{wdp_payment_due_date}'    => '',
            '{wdp_deposit_amount}'      => '',
            '{wdp_partial_amount}'      => '',
            '{wdp_full_payment}'        => '',
            '{wdp_paid_amount}'         => '',
            '{wdp_remaining_amount}'    => '',
            '{wdp_fee_total}'           => '',
        ];
    }
    
    public function init_placeholder_values( \WC_Order $order, $parent_order ) {
        $this->object                                      = $parent_order;
        $this->deposit_amount                              = $parent_order->get_meta( '_depart_deposit_amount' );
        $this->future_payment                              = $parent_order->get_meta( '_depart_future_payment' );
        $this->paid_amount                                 = $this->object->get_meta( '_depart_paid_amount' );
        $this->remaining_amount                            = floatval( $this->object->get_total() ) - floatval( $this->paid_amount );
        $this->fee_total                                   = $parent_order->get_meta( '_depart_fee_total' );
        $this->partial_amount                              = $order->get_total();
        $this->placeholders['{wdp_order_date}']            = wc_format_datetime( $this->object->get_date_created() );
        $this->placeholders['{wdp_parent_order_number}']   = $this->object->get_order_number();
        $this->placeholders['{wdp_suborder_number}']       = $order->get_order_number();
        $this->placeholders['{wdp_payment_due_date}']      = date_i18n( wc_date_format(), $order->get_meta( '_depart_partial_payment_date' ) );
        $this->placeholders['{wdp_deposit_amount}']        = wc_price( $this->deposit_amount, array( 'currency' => $this->object->get_currency() ) );
        $this->placeholders['{wdp_future_payment_amount}'] = wc_price( $this->future_payment, array( 'currency' => $this->object->get_currency() ) );
        $this->placeholders['{wdp_partial_amount}']        = wc_price( $this->partial_amount, array( 'currency' => $this->object->get_currency(), 'decimals' => wc_get_price_decimals() ) );
        $this->placeholders['{wdp_paid_amount}']           = wc_price( $this->paid_amount, array( 'currency' => $this->object->get_currency() ) );
        $this->placeholders['{wdp_remaining_amount}']      = wc_price( $this->remaining_amount, array( 'currency' => $this->object->get_currency() ) );
        $this->placeholders['{wdp_fee_total}']             = wc_price( $this->fee_total, array( 'currency' => $this->object->get_currency() ) );
        $this->placeholders['{wdp_order_detail_link}']     = esc_url( $this->object->get_view_order_url() );
        $this->placeholders['{wdp_edit_order_link}']       = esc_url( $order->get_edit_order_url() );
        $this->placeholders['{wdp_payment_link}']          = $order->get_checkout_payment_url();
        $this->placeholders['{wdp_order_billing_name}']    = $parent_order->get_formatted_billing_full_name();
    }
    
}