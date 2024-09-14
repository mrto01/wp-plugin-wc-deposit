<?php
/**
 * Payment methods
 *
 * Shows customer payment methods on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/payment-methods.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.8.0
 */

defined( 'ABSPATH' ) || exit;

$saved_methods     = wc_get_customer_saved_methods_list( get_current_user_id() );
$available_methods = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
$has_methods       = (bool) $saved_methods;
$token_id          = $order->get_meta( '_depart_auto_payment_token_id' );

?>

<h4>
    <?php
        /* translators: %s: Order id*/
        printf( esc_html__( 'Automatic partial payment for order %s', 'depart-deposit-and-part-payment-for-woocommerce' ), '#' . esc_html( $order->get_id() ) )
    ?>
</h4>

<?php if ( $has_methods ) : ?>
    <table class="depart-auto-payment-table">
        <thead>
        <tr>
			<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
                <th>
                    <span><?php echo esc_html( $column_name ); ?></span>
                </th>
			<?php endforeach; ?>
        </tr>
        </thead>
		<?php foreach ( $saved_methods as $type => $methods ) :  ?>
			<?php
			foreach ( $methods as $index => $method ) :
				if ( ! in_array( $method['method']['gateway'], $available_methods ) || empty( $method['token_id'] ) ) {
					continue;
				}
				?>
                <tr>
					<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
                        <td>
							<?php
							if ( has_action( 'woocommerce_account_payment_methods_column_' . $column_id ) ) {
								do_action( 'woocommerce_account_payment_methods_column_' . $column_id, $method );
							} elseif ( 'method' === $column_id ) {
								if ( ! empty( $method['method']['last4'] ) ) {
									/* translators: 1: credit card type 2: last 4 digits */
									echo sprintf( esc_html__( '%1$s ending in %2$s', 'depart-deposit-and-part-payment-for-woocommerce' ), esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
								} else {
									echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
								}
							} elseif ( 'expires' === $column_id ) {
								echo esc_html( $method['expires'] );
							} elseif ( 'actions' === $column_id ) {
								?>
                                <div class="depart-switcher">
                                    <input type="checkbox" name="depart-client-auto-payment"
                                           id="depart-client-auto-payment-<?php echo esc_attr( $method['token_id'] ) ?>"
                                           value="<?php echo esc_attr( $method['token_id'] ) ?>"
                                           data-order="<?php echo esc_attr( $order->get_id() ) ?>" <?php echo $method['token_id'] == $token_id ? 'checked' : '' ?>>
                                    <label for="depart-client-auto-payment-<?php echo esc_attr( $method['token_id'] ) ?>"
                                           class="depart-switch"></label>
                                </div>
								<?php
							}
							?>
                        </td>
					<?php endforeach; ?>
                </tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
    </table>

<?php else : ?>

	<?php wc_print_notice( esc_html__( 'No saved methods found.', 'depart-deposit-and-part-payment-for-woocommerce' ), 'notice' ); ?>

<?php endif; ?>

<?php if ( WC()->payment_gateways->get_available_payment_gateways() ) : ?>
    <a class="button"
       target="_blank"
       href="<?php echo esc_url( wc_get_account_endpoint_url( 'add-payment-method' ) ); ?>"><?php esc_html_e( 'Add payment method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></a>
<?php endif; ?>

<?php