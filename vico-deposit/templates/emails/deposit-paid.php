<?php

defined( 'ABSPATH' ) || exit;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf( esc_html__( $email_text, 'vico-deposit-and-installment')) ?></p>

<p><?php echo wp_kses_post( wpautop( wptexturize( __( $payment_text, 'deposits-partial-payments-for-woocommerce' ) ) ) ); ?></p>

<?php

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'vicodin_email_payment_schedule', $order );

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );


do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );


if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
