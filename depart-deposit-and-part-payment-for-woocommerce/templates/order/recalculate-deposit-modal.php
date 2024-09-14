<?php

use VicoDIn\Admin\Deposit_Setting;

defined( 'ABSPATH' ) || exit;

$order_items  = $order->get_items();
$global_plans = depart_get_available_plans();
$global_plans = array_map( function( $plan ) {
    return array(
        'plan_id'   => $plan['plan_id'],
        'plan_name' => $plan['plan_name'],
    );
}, $global_plans );

?>
<div class="wc-backbone-modal wdp-recalculate-deposit-modal">
    <div class="wc-backbone-modal-content">
        <section class="wc-backbone-modal-main" role="main">
            <header class="wc-backbone-modal-header">
                <h1><?php esc_html_e( 'Deposit configuration', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h1>
                <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                    <span class="screen-reader-text">Close modal panel</span>
                </button>
            </header>
            <article>
                <form id="wdp-modal-recalculate-form" action="" method="post"
                      data-global-plans="<?php echo esc_attr( wp_json_encode( $global_plans ) ) ?>">
                    <table class="widefat">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Enable Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Order Item', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Plan Type', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Plan', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ( ! empty( $order_items ) ) {
                            foreach ( $order_items as $item ) {
                                $product = wc_get_product( $item->get_product_id( 'edit' ) );
                                if ( ! $product ) {
                                    continue;
                                }
                                $custom_plans = $product->get_meta( 'depart_custom_plans' );
                                if ( ! empty( $custom_plans ) ) {
                                    $custom_plans = array_map( function( $index, $plan ) {
                                        return array(
                                            'plan_id'   => $index,
                                            'plan_name' => $plan['plan_name'],
                                        );
                                    }, array_keys( $custom_plans ), $custom_plans );
                                }
                                $item_id         = $item->get_id();
                                $name            = $item->get_name();
                                $deposit_enabled = 'no';
                                $metadata        = $item->get_meta( 'depart_deposit_meta' );
                                if ( ! empty( $metadata ) ) {
                                    $deposit_enabled = 'yes';
                                }
                                $plan_list = $global_plans;
                                $plan_type = isset( $metadata['plan'], $metadata['plan']['plan_type'] ) ? $metadata['plan']['plan_type'] : 'global';
                                $plan_used = isset( $metadata['plan_id'] ) ? $metadata['plan_id'] : 'none';
                                if ( 'custom' == $plan_type ) {
                                    $plan_list = $custom_plans;
                                }
                                ?>
                                <tr class="wdp_calculator_modal_row"
                                    data-custom-plans="<?php echo esc_attr( wp_json_encode( $custom_plans ) ) ?>">
                                    <td>
                                        <label>
                                            <input <?php echo 'yes' === $deposit_enabled ? 'checked' : '' ?>
                                                name="wdp_deposits_deposit_enabled_<?php echo esc_attr( $item_id ) ?>"
                                                class="wdp_enable_deposit" type="checkbox"
                                            />
                                        </label>
                                    </td>
                                    <td><?php echo esc_html( $name ); ?></td>
                                    <td>
                                        <label>
                                            <select class="widefat wdp_deposits_deposit_type"
                                                    name="wdp_deposits_deposit_type_<?php echo esc_attr( $item_id ) ?>"
                                                <?php echo 'no' === $deposit_enabled ? 'disabled' : '' ?>
                                            >
                                                <option <?php echo ( 'global' === $plan_type ) ? 'selected' : '' ?>
                                                    value="global"><?php esc_html_e( 'Global', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
                                                <option <?php echo ( 'custom' === $plan_type ) ? 'selected' : '' ?>
                                                    value="custom"><?php esc_html_e( 'Custom', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
                                            </select>
                                        </label>
                                    </td>
                                    <td style="min-width: 250px;">
                                        <select class="widefat wdp-hidden wdp_deposits_payment_plan"
                                                name="wdp_deposits_payment_plan_<?php echo esc_attr( $item_id ) ?>"
                                            <?php echo 'no' === $deposit_enabled ? 'disabled' : '' ?>
                                        >
                                            <?php foreach ( $plan_list as $plan_id => $plan ) {
                                                $selected = ( $plan_id == $plan_used ) ? 'selected' : '';
                                                echo '<option value="' . esc_attr( strval( $plan_id ) ) . '"' . esc_attr( $selected ) . '>' . esc_html( $plan['plan_name'] )
                                                     . '</option>';
                                            } ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                        </tbody>
                        <tfoot>
                        <?php
                        
                        $extra_options      = Deposit_Setting::get_extra_options();
                        $extra_selects      = Deposit_Setting::get_extra_selects();
                        $extra_options_used = $order->get_meta( '_depart_extra_options' );
                        if ( empty( $extra_options_used ) ) {
                            $extra_options_used = array(
                                'coupon'       => Deposit_Setting::$settings['coupon'],
                                'fee'          => Deposit_Setting::$settings['fee'],
                                'tax'          => Deposit_Setting::$settings['tax'],
                                'shipping'     => Deposit_Setting::$settings['shipping'],
                                'shipping_tax' => Deposit_Setting::$settings['shipping_tax'],
                            );
                        }
                        ?>
                        <tr>
                            <td colspan="4" style=" padding:30px 0 0 0; ">
                                <h3 style="margin-bottom: 3px;"><?php esc_html_e( 'Additional Settings', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h3>
                            </td>
                        </tr>
                        
                        <?php foreach ( $extra_options as $opt_id => $option ) { ?>
                            <tr>
                                <td style="padding-left:0;" colspan="3">
                                    <label for="wdp_deposits_<?php echo esc_attr( $opt_id ) ?>"><?php echo esc_html( $option['title'] ) ?></label>
                                </td>
                                <td>
                                    <label>
                                        <select name="wdp_deposits_<?php echo esc_attr( $opt_id ) ?>">
                                            <?php foreach ( $extra_selects as $slc_id => $slc_value ) { ?>
                                                <option value="<?php echo esc_attr( $slc_id ) ?>"
                                                    <?php echo $extra_options_used[ $opt_id ] == $slc_id ? 'selected' : '' ?>
                                                >
                                                    <?php echo esc_html( $slc_value ) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                </td>
                            </tr>
                        <?php } ?>
                        </tfoot>
                    </table>
                </form>
            </article>
            <footer>
                <div class="inner">
                    <button id="wdp-btn-ok" class="button button-primary button-large wdp_save_deposit_data">
                        <?php esc_html_e( 'Recalculate', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                    </button>
                </div>
            </footer>
        </section>
    </div>
</div>
<div class="wc-backbone-modal-backdrop"></div>