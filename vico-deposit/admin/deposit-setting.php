<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Deposit_Setting {
	static $settings;
	protected static $instance = null;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'save_setting' ) );
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function save_setting() {
		self::$settings = get_option( 'vicodin_deposit_setting', null );
		if ( ! isset( $_POST['_vicodin_nonce'] )
		     || ! isset( $_POST['vicodin_setting_params'] )
		) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['_vicodin_nonce'] ),
			'vicodin_settings' )
		) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$data           = wc_clean( $_POST['vicodin_setting_params'] );
		$args           = array(
			'enabled'                 => '0',
			'auto_charge'             => '0',
			'exclude_payment_methods' => array(),
			'coupon'                  => 'deposit',
			'tax'                     => 'deposit',
			'fee'                     => 'deposit',
			'shipping'                => 'deposit',
			'shipping_tax'            => 'deposit',
		);
		self::$settings = array_merge( $args, $data );
		update_option( 'vicodin_deposit_setting', self::$settings );
	}

	public static function set_field( $field, $multi = false ) {
		if ( $field ) {
			if ( $multi ) {
				return esc_attr( 'vicodin_setting_params[' . $field . '][]' );
			} else {
				return esc_attr( 'vicodin_setting_params[' . $field . ']' );
			}
		} else {
			return '';
		}
	}

	public static function get_extra_options() {
		return array(
			'coupon'       => [
				'title' => 'Coupon Handling.',
				'desc'  => 'How coupon will be handled.'
			],
			'tax'          => [
				'title' => 'Tax Collection',
				'desc'  => 'How tax will be charged.'
			],
			'fee'          => [
				'title' => 'Fee Collection',
				'desc'  => 'How fee will be charged.'
			],
			'shipping'     => [
				'title' => 'Shipping Handling',
				'desc'  => 'How shipping will be charged.'
			],
			'shipping_tax' => [
				'title' => 'Shipping Tax Handling',
				'desc'  => 'How shipping tax will be handled.'
			]
		);
	}

	public static function get_field( $field, $default = '' ) {
		$params = self::$settings;
		if ( $params ) {
			if ( isset( $params[ $field ] ) ) {
				return $params[ $field ];
			} else {
				return $default;
			}
		} else {
			return $default;
		}
	}

	public static function page_callback() {
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		$extra_options    = self::get_extra_options();
		$extra_selects    = [
			'deposit' => 'With Deposit',
			'future'  => 'With Future Payment',
			'split'   => 'Split'
		]
		?>
        <div class="wrapper vico-deposit">
            <h2>Deposit and Installment setting</h2>
            <form method="post" action="" class="vi-ui form">
				<?php wp_nonce_field( 'vicodin_settings',
					'_vicodin_nonce' ) ?>
                <div class="vi-ui top attached tabular menu">
                    <a class="item active" data-tab="general">General</a>
                    <a class="item" data-tab="email">Email</a>
                </div>
                <div class="vi-ui bottom attached tab segment active"
                     data-tab="general">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="vicodin-enable"><?php esc_html_e( 'Enable',
										'vico-deposit-and-installment' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo self::set_field( 'enabled' ); ?>"
                                           id="vicodin-enable"
										<?php echo self::get_field( 'enabled' )
											? 'checked' : '' ?>>
                                    <label></label>
                                </div>
                            </td>
                            <td><p class="description"><?php esc_html_e( 'Turn on Deposit
                                    feature.',
										'vico-deposit-and-installment' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vicodin-enable"><?php esc_html_e( 'Automatic partial
                                    payment',
										'vico-deposit-and-installment' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo self::set_field( 'auto_charge' ) ?>"
                                           id="vicodin-enable"
										<?php echo self::get_field( 'auto_charge' )
											? 'checked' : '' ?>>
                                    <label></label>
                                </div>
                            </td>
                            <td><p class="description"><?php esc_html_e( 'Charge customer balance
                                    when a partial payment is due.',
										'vico-deposit-and-installment' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vicodin-payment"><?php esc_html_e( 'Exclude Payment
                                    method',
										'vico-deposit-and-installment' ); ?></label>
                            </th>
                            <td>
                                <select multiple class="vi-ui dropdown"
                                        name="<?php echo self::set_field( 'exclude_payment_methods',
									        true ) ?>"
                                        id="vicodin-payment">
                                    <option value="">Select method</option>
									<?php
									foreach (
										$payment_gateways as $payment_gateway
									) {
										?>
                                        <option value="<?php esc_attr_e( $payment_gateway->id ); ?>"
											<?php echo ( in_array( $payment_gateway->id,
												self::get_field( 'exclude_payment_methods' ) ) )
												? 'selected'
												: '' ?>><?php esc_html_e( $payment_gateway->title, 'vico-deposit-and-installment' ); ?></option>
										<?php
									}
									?>
                                </select>
                            </td>
                            <td><p class="description"><?php esc_html_e( 'The selected payment
                                    methods will not be available.',
										'vico-deposit-and-installment' ); ?></p>
                            </td>
                        </tr>
						<?php foreach ( $extra_options as $id => $option ) { ?>
                            <tr>
                                <th>
                                    <label for="vicodin-coupon"><?php esc_html_e( $option['title'],
											'vico-deposit-and-installment' ); ?></label>
                                </th>
                                <td>
                                    <select class="vi-ui dropdown"
                                            name="<?php echo self::set_field( $id ) ?>"
                                            id="vicodin-coupon">
										<?php
										foreach (
											$extra_selects as $value => $text
										) {
											?>
                                            <option value="<?php echo $value ?>" <?php echo ( $value
											                                             == self::get_field( $id )
												? 'selected'
												: '' ) ?>><?php esc_html_e($text); ?></option>
											<?php
										}
										?>
                                    </select>
                                </td>
                                <td>
                                    <p class="description"><?php esc_html_e( $option['desc'],
											'vico-deposit-and-installment' ); ?></p>
                                </td>
                            </tr>
						<?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment"
                     data-tab="email">
                    <div class="vi-ui vicodin-email-links">
                        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=email&section=vicodin_email_deposit_paid' ) ?>"
                           class="blue">Order full payment</a>
                        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=email&section=vicodin_email_deposit_paid' ) ?>"
                           class="blue">Partial payment reminder</a>
                        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=email&section=vicodin_email_deposit_paid' ) ?>"
                           class="blue">Deposit paid</a>
                        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=email&section=vicodin_email_partial_paid' ) ?>"
                           class="blue">Partial paid</a>
                    </div>
                </div>
                <div class="vi-ui fluid vicodin-sticky">
                    <button class="vi-ui button labeled icon primary">
                        <i class="save icon"></i> Save
                    </button>
                </div>
                <p></p>
            </form>
        </div>
		<?php
	}

}