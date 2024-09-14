<?php
defined( 'ABSPATH' ) || exit;
$text_align = is_rtl() ? 'right' : 'left';

?>

<h2><?php esc_html_e('Installment plan', 'depart-deposit-and-part-payment-for-woocommerce'); ?></h2>

<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
        <tr>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'ID', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Payment date', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Amount', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Payment method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ( $schedule as $payment ) {
            ?>
            <tr>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?> "><?php echo '#' . wp_kses_post( $payment['id'] ); ?></td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>"><?php echo wp_kses_post( $payment['due_date'] ); ?></td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>"><?php echo wp_kses_post( $payment['amount'] ); ?></td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>"><?php echo wp_kses_post( $payment['payment_method'] ); ?></td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>"><?php echo wp_kses_post( $payment['status_name'] ); ?></td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
</div>