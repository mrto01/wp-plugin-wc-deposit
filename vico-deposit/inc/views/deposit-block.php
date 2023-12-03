<?php

defined( 'ABSPATH' ) || exit;

?>

<div class="vicodin-deposit-wrapper">
	<label class="vicodin-deposit-check" >
		<?php esc_html_e( 'Deposit payment', 'vico-deposit-and-installment' ); ?>
        <input type="checkbox" name="vicodin-deposit-check" id="vicodin-deposit-check">
        <span class="vicodin-deposit-checkmark"></span>
	</label>
	<div class="vicodin-deposit-options">
		<input type="hidden" name="vicodin-deposit-type" id="vicodin-deposit-type" value="<?php echo esc_attr( $deposit_type ); ?>">
		<span ><?php esc_html_e('Select plan', 'vico-deposit-and-installment' ); ?></span>
	</div>
    <div id="vicodin-deposit-modal">
        <div class="vicodin-modal-content">
            <span class="close">&times;</span>
            <div class="vicodin-plan-boxes">
                <?php
                    $price = $product->get_price();
                    foreach ( $plans as $plan_id => $plan ) {
	                    $plan_dues = vicodin_get_payment_dues( $plan, $price );
                        $deposit_amount = vicodin_get_due_amount( $plan['deposit'],$price, $plan['unit-type'] ?? 'percentage' );
                        ?>
                        <div class="vicodin-plan-box">
                            <input type="radio" name="vicodin-plan-select" id="vicodin-plan-<?php echo esc_attr( $plan_id ); ?>" value="<?php echo esc_attr( ( 'custom' === $deposit_type ) ? $plan_id : $plan['plan_id'] ); ?>" data-plan_name="<?php echo esc_attr( $plan['plan_name'] ); ?>">
                            <label class="vicodin-plan-summary" for="vicodin-plan-<?php echo esc_attr( $plan_id ); ?>">
                                <div class="vicodin-select"></div>
                                <div class="vicodin-plan-info">
                                    <h3 class="vicodin-plan_name"><?php echo esc_html( $plan['plan_name'] ); ?></h3>
                                    <div class="vicodin-deposit-amount"><?php esc_html_e( 'Pay deposit : ', 'vico-deposit-and-installment' ); ?>
                                        <span><?php echo wc_price( $deposit_amount ) ?></span>
                                    </div>
                                    <?php if ( $plan['deposit_fee'] ) { ?>
                                        <div class="vicodin-deposit-amount"><?php esc_html_e( 'Fee : ', 'vico-deposit-and-installment' ); ?>
                                            <span><?php echo wc_price( vicodin_get_due_amount( $plan['deposit_fee'],$deposit_amount, $plan['unit-type'] ?? 'percentage' ) ); ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </label>
                            <table class="vicodin-plan_schedule">
                                <thead>
                                <tr>
                                    <td><?php esc_html_e( 'Payment date', 'vico-deposit-and-installment' ); ?></td>
                                    <td><?php esc_html_e( 'Amount', 'vico-deposit-and-installment' ); ?></td>
                                    <?php if ( $plan['fee_total'] ) { ?>
                                        <td><?php esc_html_e( 'Fee', 'vico-deposit-and-installment' ); ?></td>
                                    <?php } ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                foreach ( $plan_dues as $due ) {
                                ?>
                                    <tr>
                                        <td><?php echo esc_html( date_i18n( wc_date_format(), $due['date'] ) ); ?></td>
                                        <td><?php echo wc_price( $due['amount'] ) ?></td>
                                        <?php if ( $plan['fee_total'] ) { ?>
                                            <td><?php echo wc_price( $due['fee'] )?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                ?>
            </div>
        </div>
    </div>
</div>