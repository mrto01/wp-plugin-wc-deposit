<?php

defined( 'ABSPATH' ) || exit;

$single_plan = count( $plans ) === 1 ? 'depart-single-plan' : '';

?>

<div class="depart-plan-boxes <?php echo esc_attr( $single_plan ) ?>">
    <?php
    foreach ( $plans as $plan_id => $plan ) {
        $schedule_formatted = depart_get_schedule( $plan, $price );
        $deposit_amount     = depart_get_due_amount( $plan['deposit'], $price, $plan['unit-type'] ?? 'percentage' );
        
        /* Show tax in front-end if enable*/
        if ( 'incl' === depart_display_tax( apply_filters( 'depart_display_tax_in_plan', 'shop') ) ) {
            $product_tax = wc_get_price_including_tax( $product, array( 'price' => $price ) ) - $price;
            if ( $product_tax > 0 ) {
                $deposit_percent    = $deposit_amount * 100 / $price;
                $deposit_tax        = depart_get_tax_amount( $deposit_percent, $product_tax );
                $future_payment     = $price - $deposit_amount;
                $deposit_amount     += $deposit_tax;
                $product_tax        -= $deposit_tax;
                $schedule_formatted = depart_get_schedule_include_tax( $schedule_formatted, $product_tax, $future_payment );
            }
        }
        ?>
        <div class="depart-plan-box  <?php echo ( isset( $prior_plan_id ) && $prior_plan_id == $plan_id || $single_plan) ? 'depart-active' : '' ?>">
            <input type="radio" name="depart-plan-select" id="depart-plan-<?php echo esc_attr( $plan_id ) . esc_attr( isset( $from_cart_item ) ? $from_cart_item : '' ); ?>"
                   value="<?php echo esc_attr( ( 'custom' === $deposit_type ) ? $plan_id : $plan['plan_id'] ); ?>"
                   data-plan_name="<?php echo esc_attr( depart_get_text_option( 'plan_name', $plan ) ); ?>"
                <?php echo ( isset( $prior_plan_id ) && $prior_plan_id == $plan_id ) ? 'checked' : '' ?>
            >
            <div class="depart-plan-summary">
                <div class="depart-plan-info">
                    <h4 class="depart-plan_name"><?php echo esc_html( depart_get_text_option( 'plan_name', $plan ) ); ?></h4>
                    <div class="depart-deposit-amount">
                        <span><?php echo esc_html( depart_get_text_option( 'deposit_payment_text' ) ) ?> : </span>
                        <?php echo wp_kses_post( wc_price( $deposit_amount ) );  ?>
                    </div>
                    <?php if ( $plan['deposit_fee'] && $deposit_settings['show_fees'] ) { ?>
                        <div class="depart-deposit-fee">
                            <span><?php echo esc_html( depart_get_text_option( 'fees_text' ) ) ?> : </span>
                            <?php echo wp_kses_post( wc_price( depart_get_due_amount( $plan['deposit_fee'], $deposit_amount, $plan['unit-type'] ?? 'percentage' ) ) ); ?>
                        </div>
                    <?php } ?>
                </div>
                <label class="depart-select <?php echo ( isset( $prior_plan_id ) && $prior_plan_id == $plan_id ) ? 'depart-active' : '' ?>"
                       for="depart-plan-<?php echo esc_attr( $plan_id ) . esc_attr( isset( $from_cart_item ) ? $from_cart_item : '' ); ?>"><?php echo esc_attr( depart_get_text_option( 'select_mark_text' ) ) ?></label>
                </label>
            </div>
            <table class="depart-plan-schedule depart-schedule-summary selected">
                <thead>
                <tr>
                    <th><?php echo esc_attr( depart_get_text_option( 'payment_date_text' ) ) ?></th>
                    <th><?php echo esc_attr( depart_get_text_option( 'payment_amount_text' ) ) ?></th>
                    <?php if ( $plan['fee_total'] && $deposit_settings['show_fees'] ) { ?>
                        <th><?php echo esc_html( depart_get_text_option( 'fees_text' ) ) ?></th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ( $schedule_formatted as $due ) {
                    ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( wc_date_format(), $due['date'] ) ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( $due['amount'] ) ) ?></td>
                        <?php if ( $plan['fee_total'] && $deposit_settings['show_fees'] ) { ?>
                            <td><?php echo wp_kses_post( wc_price( $due['fee'] ) ); ?></td>
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
