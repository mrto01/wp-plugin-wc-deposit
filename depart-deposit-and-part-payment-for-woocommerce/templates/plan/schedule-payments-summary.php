<?php

defined( 'ABSPATH' ) || exit;

$is_admin = $admin ?? is_admin();

?>

<?php if ( ! $is_admin ) { ?>
    <h2><?php esc_html_e( 'Installment plan', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
<?php } ?>
<?php if ( $is_admin ) {
    wp_nonce_field( 'depart_nonce', 'depart_nonce', false );
} ?>
<table class="depart-installment-summary">
    <thead>
    <tr>
        <?php if ( $is_admin ) { ?>
            <th><?php esc_html_e( 'ID', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Payment date', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Payment method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Amount', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Completed', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
        <?php } else { ?>
            <th><?php esc_html_e( 'ID', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Payment date', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Payment method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Amount', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th><?php esc_html_e( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th></th>
            <?php
        }
        ?>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ( $schedule as $payment ) {
        ?>
        <tr>
            
            <?php if ( $is_admin ) { ?>
                
                <td>
                    <a
                        href="<?php echo esc_url( $payment['edit_url'] ) ?>"><?php echo wp_kses_post( $payment['id'] ); ?></a>
                </td>
                <td> <?php echo wp_kses_post( $payment['due_date'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['payment_method'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['amount'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['status_name'] ); ?> </td>
                <td><input type="checkbox" name="depart_partial_payment_completed[]"
                           value="<?php echo esc_attr( $payment['ID'] ) ?>" <?php echo 'completed' === $payment['status'] ? 'checked' : '' ?>>
                </td>
            
            <?php } else { ?>
                
                <td> <?php echo wp_kses_post( '#' . $payment['id'] ); ?></td>
                <td> <?php echo wp_kses_post( $payment['due_date'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['payment_method'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['amount'] ); ?> </td>
                <td> <?php echo wp_kses_post( $payment['status_name'] ); ?> </td>
                <?php if ( $payment['payable'] ) {
                    ?>
                    <td>
                        <a href="<?php echo esc_url( $payment['checkout_url'] ); ?>"
                           class="woocommerce_button button"> <?php esc_html_e( 'Pay', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></a>
                    </td>
                    <?php
                } else {
                    echo '<td></td>';
                }
            } ?>
        </tr>
        <?php
    }
    ?>
    </tbody>
</table>

<?php if ( ! $is_admin ) {
    do_action( 'depart_after_schedule_payments_summary', $order );
} ?>
