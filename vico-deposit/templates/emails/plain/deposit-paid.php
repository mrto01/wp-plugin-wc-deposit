<?php
if (!defined('ABSPATH')) {
	exit;
}

echo esc_html( $email_heading ) . "\n\n";

echo esc_html( $payment_message );

echo "****************************************************\n\n";

do_action('woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text);

echo sprintf(__('Order number: %s', 'deposits-partial-payments-for-woocommerce'), esc_html( $order->get_order_number() ) ) . "\n";
echo sprintf(__('Order date: %s', 'deposits-partial-payments-for-woocommerce'), date_i18n( wc_date_format(), strtotime($order->get_date_created()))) . "\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text);

echo esc_html( wc_get_email_order_items( $order ) );

echo "----------\n\n";
echo esc_html( $payment_text ) .  "\n";

$totals = $order->get_order_item_totals();
foreach ( $totals as $total ) {
	echo $total['label'] . "\t " . esc_html( $total['value'] ) . "\n";
}

if ( is_array( $schedule ) && ! empty( $schedule ) ) {
	echo __('Payment schedule', 'vico-deposit-and-installment' );
	echo sprintf("%s\t%s\t%s", __('ID', 'vico-deposit-and-installment'), __( 'Payment date', 'vico-deposit-and-installment'), __('Amount', 'vico-deposit-and-installment') ) . "\n";
	foreach ( $schedule as $payment ) {
		echo sprintf( "%s\t%s\t%s", esc_html( $payment['id'] ), date_i18n( wc_date_format(), $payment['date'] ), wc_price( $payment['total'] ) ) . "\n";
	}
}
echo "\n****************************************************\n\n";

do_action('woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text);

echo "\n****************************************************\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters('woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text') );
