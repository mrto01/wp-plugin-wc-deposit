<?php

defined( 'ABSPATH' ) || exit;

?>

<div class="depart-deposit-wrapper" data-force_deposit="<?php echo esc_attr( $force_deposit ? 'yes' : 'no' ) ?>">
    <?php echo wp_kses_post( wp_nonce_field( 'depart_nonce', '_depart_nonce', false ) ) ?>
    <div class="depart-deposit-action">
        <?php if ( ! $force_deposit ) { ?>
            <label class="depart-deposit-check">
                <?php echo esc_html( depart_get_text_option( 'deposit_checkbox_text' ) ) ?>
                <input type="checkbox" name="depart-deposit-check" id="depart-deposit-check">
                <span class="depart-deposit-checkmark"></span>
            </label>
        <?php } ?>
        <div class="depart-deposit-options">
            <input type="hidden" name="depart-deposit-type" id="depart-deposit-type"
                   value="<?php echo esc_attr( $deposit_type ); ?>">
            <span id="depart-current-plan"><?php echo esc_html( depart_get_text_option( 'select_plan_text' ) ); ?></span>
            <div class="depart-dropdown-mark"></div>
        </div>
    </div>
    <div id="depart-deposit-modal" class="depart-deposit-modal-product-detail">
        <div class="depart-modal-content">
            <span class="close">&times;</span>
            <?php
            if ( 'variable' === $product_type ) {
                echo sprintf( '<div class="depart-plan-boxes">%s</div>', esc_html__( 'Please select a variable', 'depart-deposit-and-part-payment-for-woocommerce' ) );
            } else {
                include __DIR__ . '/deposit-block-content.php';
            }
            ?>
        </div>
    </div>
</div>