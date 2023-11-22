<?php
defined( 'ABSPATH' ) || exit;

$is_admin = $admin ?? is_admin();

?>

<?php if ( !$is_admin ){ ?>
    <p class="vicodin-installment-summary-title"><?php esc_html_e('Installment plan', 'vico-deposit-and-installment'); ?></p>
<?php } ?>

<table class="vicodin-installment-summary">
	<thead>
	<tr>
        <?php
            $installment_columns = array();
            if( $is_admin){
                $installment_columns = array( 'ID', 'Payment date', 'Payment method', 'Amount', 'Status', 'Completed' );
            }else {
	            $installment_columns = array( 'ID', 'Payment date', 'Amount', 'Status', '' );
            }
            foreach ( $installment_columns as $column ) {
                ?>
                <th ><?php esc_html_e( $column, 'vico-deposit-and-installment' ); ?></th>
                <?php
            }
        ?>
	</tr>
	</thead>
	<tbody>
	<?php

		$schedule = $order->get_meta('vicodin_deposit_payment_schedule');
        $main_id = $order->get_order_number();
	    $actions = wc_get_account_orders_actions( $order );
        $pay_enable = true;
        $payment_url = '';
        if ( isset( $actions, $actions['pay_partial'], $actions['pay_partial']['url'] ) ) {
	        $payment_url = $actions['pay_partial']['url'];
        }
		if ( $schedule && is_array( $schedule ) ){
			foreach ( $schedule as $count => $payment ) {
				if(isset($payment['id']) && !empty($payment['id'])) $partial_payment = wc_get_order($payment['id']);
				if( !$partial_payment ) continue;

				$payment_id = $main_id . '-' . ++$count;
				$payment_date = date_i18n( wc_date_format(), strtotime( $payment['date'] ) );
				$status = $partial_payment->get_status();
                $status_name = wc_get_order_status_name( $status );
                $payment_method = $partial_payment->get_payment_method_title();
				$amount = $partial_payment->get_total();
				$price_args = array('currency' => $partial_payment->get_currency(), 'decimals' => wc_get_price_decimals() );
                $amount = wc_price( $amount, $price_args );

                $checkout_url = $partial_payment->get_checkout_payment_url();
                $edit_url = $partial_payment->get_edit_order_url();
				?>
					<tr>

						<?php if( $is_admin ) { ?>

                            <td>
                                <a href="<?php echo esc_url( $edit_url ) ?>"><?php echo wp_kses_post( $payment_id ); ?></a>
                            </td>
                            <td> <?php echo wp_kses_post( $payment_date ); ?> </td>
                            <td> <?php echo wp_kses_post( $payment_method ); ?> </td>
                            <td> <?php echo wp_kses_post( $amount ); ?> </td>
                            <td> <?php echo wp_kses_post( $status_name ); ?> </td>
                            <td><input type="checkbox" name="vicodin_partial_payment_completed[]" value="<?php echo $partial_payment->get_id() ?>" <?php echo $status === 'completed' ? 'checked' : '' ?>> </td>

                        <?php } else{ ?>

                            <td><?php echo wp_kses_post( $payment_id ); ?></td>
                            <td> <?php echo wp_kses_post( $payment_date ); ?> </td>
                            <td> <?php echo wp_kses_post( $amount ); ?> </td>
                            <td> <?php echo wp_kses_post( $status_name ); ?> </td>

                            <?php if ( $status === 'pending' && $pay_enable && $order->get_status() === 'installment' ) { ?>

                                <td>
                                    <a href="<?php echo esc_url( $payment_url ); ?>" class="woocommerce_button button"> <?php esc_html_e( 'Pay', 'vico-deposit-and-installment' ); ?></a>
                                </td>

                                <?php
                                $pay_enable = false;
                            }else{
                                echo '<td></td>';
                            }
                        }?>
					</tr>
				<?php
			}
		}
	?>
	</tbody>
</table>
