<?php

defined( 'ABSPATH' ) || exit;

$id_width             = 8;
$amount_width         = 15;
$payment_date_width   = 20;
$payment_method_width = 25;
$status_width         = 15;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo wp_kses_post( sprintf( __( 'Hi %s,', 'depart-deposit-and-part-payment-for-woocommerce' ), esc_html( $order->get_billing_first_name() ) ) ) . "\n\n";
echo esc_html( $email_text ) . "\n";
/* translators: The total of the current order */
echo wp_kses_post( sprintf( __( 'Deposit amount: %s', 'depart-deposit-and-part-payment-for-woocommerce' ), $current_payment ) ) . "\n";
echo esc_html( $payment_text ) . "\n";

echo "\n----------------------------------------\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

if ( is_array( $schedule ) && ! empty( $schedule ) ) {
    echo esc_html__( 'Installment plan', 'depart-deposit-and-part-payment-for-woocommerce' ) . "\n";
    echo wp_kses_post( sprintf( "%-{$id_width}s \t %-{$amount_width}s \t %-{$payment_date_width}s \t %-{$payment_method_width}s \t %-{$status_width}s", __( 'ID', 'depart-deposit-and-part-payment-for-woocommerce' ), __( 'Payment date', 'depart-deposit-and-part-payment-for-woocommerce' ), __( 'Amount', 'depart-deposit-and-part-payment-for-woocommerce' ), __( 'Payment_method', 'depart-deposit-and-part-payment-for-woocommerce' ), __( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ) ) )
         . "\n";
    foreach ( $schedule as $payment ) {
        echo wp_kses_post( sprintf( "%-{$id_width}s \t %-{$amount_width}s \t %-{$payment_date_width}s \t %-{$payment_method_width}s \t %-{$status_width}s", esc_html( $payment['id'] ), wp_kses_post( $payment['amount'] ), wp_kses_post( $payment['due_date'] ), wp_kses_post( $payment['payment_method'] ), wp_kses_post( $payment['status_name'] ),
            ) ) . "\n";
    }
}
echo "\n----------------------------------------\n\n";

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text );

echo "\n****************************************************\n\n";

if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
