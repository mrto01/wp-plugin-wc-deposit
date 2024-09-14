<?php

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

?>
    <p><?php echo esc_html( $email_text ) ?></p>
<?php /* translators: %s: Partial payment amount*/ ?>
    <p><?php echo wp_kses_post( sprintf( __( 'Payment amount: %s', 'depart-deposit-and-part-payment-for-woocommerce' ), $current_payment ) ); ?></p>
<?php

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );