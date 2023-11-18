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
		<input type="hidden" name="vicodin-deposit-type" id="vicodin-deposit-type" value="<?php esc_attr_e( $deposit_type ); ?>">
		<span ><?php esc_html_e('Select plan', 'vico-deposit-and-installment' ); ?></span>
	</div>
    <div id="vicodin-deposit-modal">
        <div class="vicodin-modal-content">
            <span class="close">&times;</span>
            <div class="vicodin-plan-boxes">
                <?php
                    $price = $product->get_price();
                    $currency_symbol = get_woocommerce_currency_symbol();
                    foreach( $plans as $id=>$plan ) {
                        ?>
                        <div class="vicodin-plan-box">
                            <input type="radio" name="vicodin-plan-select" id="vicodin-plan-<?php esc_attr_e( $id ); ?>" value="<?php esc_attr_e( $plan['plan-id'] ?? $id ); ?>" data-plan_name="<?php esc_html_e( $plan['plan-name'] ); ?>">
                            <label class="vicodin-plan-summary" for="vicodin-plan-<?php esc_attr_e( $id ); ?>">
                                <div class="vicodin-select"></div>
                                <div class="vicodin-plan-info">
                                    <h3 class="vicodin-plan-name"><?php esc_html_e( $plan['plan-name'] ); ?></h3>
                                    <div class="vicodin-deposit-amount"><?php esc_html_e( 'Pay deposit : ', 'vico-deposit-and-installment' ); ?>
                                        <span><?php echo esc_html( vicodin_get_due_amount( $plan['deposit'],$price, $plan['unit-type'] ?? 'percentage' ) ) . ' ' . $currency_symbol; ?></span>
                                    </div>
                                </div>
                            </label>
                            <table class="vicodin-plan-schedule">
                                <thead>
                                <tr>
                                    <td><?php esc_html_e( 'Payment date', 'vico-deposit-and-installment' ); ?></td>
                                    <td><?php esc_html_e( 'Amount', 'vico-deposit-and-installment' ); ?></td>
                                    <td><?php esc_html_e( 'Fee', 'vico-deposit-and-installment' ); ?></td>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $plan_dues = vicodin_get_payment_dues( $plan, $price );
                                foreach( $plan_dues as $due ) {
                                ?>
                                    <tr>
                                        <td><?php echo esc_html( $due['date'] ); ?></td>
                                        <td><?php echo esc_html( $due['amount'] ) . ' ' . $currency_symbol; ?></td>
                                        <td><?php echo esc_html( $due['fee'] ) . ' ' . $currency_symbol; ?></td>
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