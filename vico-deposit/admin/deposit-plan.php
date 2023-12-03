<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Deposit_Plan {
	protected static $instance = null;

	public function __construct() {
		add_action( 'wp_ajax_vicodin_get_plan_list',
			array( $this, 'get_home' ) );
		add_action( 'wp_ajax_vicodin_get_plan',
			array( $this, 'get_plan' ) );
		add_action( 'wp_ajax_vicodin_save_plan', array( $this, 'save_plan' ) );
		add_action( 'wp_ajax_vicodin_delete_plan',
			array( $this, 'delete_plan' ) );
		add_action( 'wp_ajax_vicodin_update_plan',
			array( $this, 'update_plan' ) );
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function page_callback() {
		?>
        <div class="vico-deposit wrapper"></div>
		<?php

	}

	protected static function set_field( $field, $multi = false ) {
		if ( $field ) {
			if ( $multi ) {
				return esc_attr( 'vicodin_params[' . $field . '][]' );
			} else {
				return esc_attr( 'vicodin_params[' . $field . ']' );
			}
		} else {
			return '';
		}
	}

	public function get_plan() {
		if ( ! ( isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'vicodin_nonce' ) )
		) {
			wp_die();
		}
		$payment_plans = get_option( 'vicodin_payment_plan' );
        $plan_id       = '';
        if ( isset( $_GET['plan_id'] ) ) {
		    $plan_id   = sanitize_text_field( wp_unslash( $_GET['plan_id'] ) );
        }
		$plan          = [];
		if ( $plan_id ) {
			if ( isset( $payment_plans[ $plan_id ] ) ) {
				$plan = $payment_plans[ $plan_id ];
			} else {
                $message = __( 'Plan not found!', 'vico-deposit-and-installment');
				echo '<h2>' . esc_html( $message ) . '</h2>';
                wp_die();
			}
		} else {
			$plan = [
				'plan_id'          => '',
				'plan_name'        => '',
				'plan_active'      => true,
				'plan_description' => '',
				'deposit'          => '',
				'deposit_fee'      => '',
				'plan_schedule'    => array(
					[
						'partial'   => '',
						'after'     => '',
						'date_type' => 'day',
						'fee'       => ''
					]
				),
				'duration'         => '',
				'total'            => 0
			];
		}

		?>
        <div class="vicodin-action-bar">
            <a href="#/" class="vi-ui button icon" id="vicodin-home-plan">
                <i class="angle double left icon"></i>
            </a>
			<?php
			if ( empty( $plan['plan_name'] ) ) {
				?>
                <h2><?php esc_attr_e( 'Add new plan', 'vico-deposit-and-installment' ); ?></h2>
				<?php
			} else {
				?>
                <h2><?php esc_attr_e( 'Edit plan',
						'vico-deposit-and-installment' ); ?></h2>
				<?php
			}
			?>
            <button class="vi-ui button labeled icon primary"
                    id="vicodin-save-plan">
                <i class="save icon"></i> <?php esc_attr_e( 'Save',
					'vico-deposit-and-installment' ); ?>
            </button>
        </div>
        <form action="" class="vi-ui form segments">
            <input type="hidden" name="plan_id"
                   value="<?php echo esc_attr( $plan['plan_id'] ) ?>">
            <div class="field">
                <label for="plan_name"><?php esc_html_e( 'Plan name',
						'vico-deposit-and-installment' ); ?></label>
                <input type="text"
                       name="plan_name"
                       id="plan_name"
                       value="<?php echo esc_attr( $plan['plan_name'] ) ?>"
                >
            </div>
            <div class="field four wide">
                <label for="plan_active"><?php esc_html_e( 'Active plan',
						'vico-deposit-and-installment' ); ?></label>
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" tabindex="0" class="hidden"
                           name="plan_active"
                           id="plan_active"
						<?php echo $plan['plan_active'] ? 'checked' : ''; ?>
                    >
                    <label for="plan_active"></label>
                </div>
            </div>
            <div class="field">
                <label for="plan_description"><?php esc_html_e( 'Plan description' ); ?></label>
                <textarea
                        name="plan_description"
                        id="plan_description" cols="30"
                        rows="5"><?php esc_html( $plan['plan_description'] ) ?></textarea>
            </div>
            <div class="field">
                <label> <?php esc_html_e( 'Plan schedule',
						'vico-deposit-and-installment' ); ?></label>
            </div>
<!--            <div class="inline field">-->
<!--                <label for="installment-fee">--><?php //esc_html_e( 'Installment fee:',
//						'vico-deposit-and-installment' ); ?><!--</label>-->
<!--                <input type="text"-->
<!--                       name="installment-fee"-->
<!--                       id="installment-fee"><span class="type-unit">%</span>-->
<!--            </div>-->
<!--            <div class="inline field">-->
<!--                <label for="fee-type">--><?php //esc_html_e( 'Fee type:',
//						'vico-deposit-and-installment' ); ?><!--</label>-->
<!--                <select name="fee-type"-->
<!--                        id="fee-type" class="vi-ui dropdown">-->
<!--                    <option value="fixed">--><?php //esc_html_e( 'Fixed',
//							'vico-deposit-and-installment' ); ?><!--</option>-->
<!--                    <option value="percentage">--><?php //esc_html_e( 'Percentage',
//							'vico-deposit-and-installment' ); ?><!--</option>-->
<!--                </select>-->
<!--            </div>-->
<!--            <div class="inline field">-->
<!--                <label>--><?php //esc_html_e( 'Fee',
//						'vico-deposit-and-installment' ); ?><!--</label>-->
<!--                <input type="text" name="unit"><span-->
<!--                        class="type-unit">%</span>-->
<!--                <div class="vi-ui button">--><?php //esc_html_e( 'Apply for all',
//						'vico-deposit-and-installment' ); ?><!--</div>-->
<!--            </div>-->
            <div class="field">
                <table class="vi-ui table segments vicodin-schedule">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'Payment Amount',
					            'vico-deposit-and-installment' ); ?></th>
                        <th colspan="3"><?php esc_html_e( 'Interval',
					            'vico-deposit-and-installment' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <div class="partial-payment">
                                <input type="number" name="partial-payment"
                                       value="<?php echo esc_attr( $plan['deposit'] ) ?>">
                                <span class="type-unit">%</span>
                            </div>
                        </td>
                        <td>
                            <div class="partial-day">
                                <label for=""><?php esc_html_e( 'Immediately',
							            'vico-deposit-and-installment' ); ?></label
                            </div>
                        </td>
                        <td>
                            <div class="partial-fee">
                                    <span><?php esc_html_e( 'Fee',
		                                    'vico-deposit-and-installment' ); ?></span>
                                <input type="number"
                                       name="partial-fee"
                                       value="<?php echo esc_attr( $plan['deposit_fee'] ) ?>">
                                <span class="type-unit">%</span>
                            </div>
                        </td>
                        <td></td>
                    </tr>
		            <?php
		            $date_types = [
			            'day'   => __( 'Day(s)', 'vico-deposit-and-installment'),
			            'month' => __( 'Month(s)', 'vico-deposit-and-installment'),
			            'year'  => __( 'Year(s)', 'vico-deposit-and-installment')
		            ];
		            foreach ( $plan['plan_schedule'] as $pos => $partial ) {
			            ?>
                        <tr>
                            <td>
                                <div class="partial-payment">
                                    <input type="number" name="partial-payment"
                                           value="<?php echo esc_attr( $partial['partial'] ) ?>">
                                    <span class="type-unit">%</span>
                                </div>
                            </td>
                            <td>
                                <div class="partial-day">
                                    <label><?php esc_html_e( 'After',
								            'vico-deposit-and-installment' ); ?></label>
                                    <input type="number" name="partial-day"
                                           value="<?php echo esc_attr( $partial['after'] ) ?>">
                                    <select name="partial-date"
                                            id="partial_date"
                                            class="vi-ui dropdown">
							            <?php
							            foreach ( $date_types as $key => $type ) {
								            ?>
                                            <option value="<?php echo esc_attr( $key ) ?>"
									            <?php echo ( $key == $partial['date_type'] ) ? 'selected' : '' ?> >
									            <?php echo esc_html( $type ); ?>
                                            </option>
								            <?php
							            }
							            ?>
                                    </select>
                                </div>
                            </td>
                            <td>
                                <div class="partial-fee">
                                    <span><?php esc_html_e( 'Fee',
		                                    'vico-deposit-and-installment' ); ?></span>
                                    <input type="number" name="partial-fee"
                                           value="<?php echo esc_attr( $partial['fee'] ) ?>">
                                    <span class="type-unit">%</span>
                                </div>
                            </td>
                            <td>
                                <div class="vi-ui circular button icon decrease-field <?php echo ( count( $plan['plan_schedule'] ) === 1 )
						            ? 'hidden' : '' ?>">
                                    <i class="minus icon"></i>
                                </div>
                                <div class="vi-ui circular button icon increase-field <?php echo( count( $plan['plan_schedule'] ) === ++$pos
						            ? '' : 'hidden' ) ?>">
                                    <i class="plus icon"></i>
                                </div>
                            </td>
                        </tr>
			            <?php
		            }
		            ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th><?php esc_html_e( 'Total: ',
					            'vico-deposit-and-installment' ); ?><span
                                    id="partial-total"><?php echo esc_html( $plan['total'] ); ?></span>
                            %
                        </th>
                        <th colspan="3"><?php esc_html_e( 'Duration: ',
					            'vico-deposit-and-installment' ); ?><span
                                    id="partial-duration"><?php echo esc_html( $plan['duration'] ) ?></span>
                        </th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </form>
		<?php
		wp_die();
	}

	public function save_plan() {
		if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}

		$data    = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data    = json_decode( $data, true );
		$plan_id = $data['plan_id'] ?? null;

		$exist_plans = get_option( 'vicodin_payment_plan', array() );


		if ( $plan_id ) {
			$exist_plans[ $plan_id ] = $data;
		} else {
			$plan_id = get_option( 'vicodin_id' ) ?? 2;
			$plan_id ++;
			$data['plan_id']        = $plan_id;
			$exist_plans[ $plan_id ] = $data;
			update_option( 'vicodin_id', $plan_id );
		}

		update_option( 'vicodin_payment_plan', $exist_plans );

		wp_send_json_success( $plan_id );

		wp_die();
	}

	public function delete_plan() {
		if ( ! ( isset( $_POST['nonce'], $_POST['plan_id'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}
		$exist_plans = get_option( 'vicodin_payment_plan' );
        $plan_id = sanitize_text_field( wp_unslash( $_POST['plan_id'] ) );
        unset( $exist_plans[ $plan_id ] );
        update_option( 'vicodin_payment_plan', $exist_plans );
        $rules = get_option( 'vicodin_deposit_rule' );
        foreach ( $rules as $key => $rule ) {
            $index = array_search( $plan_id, $rule['payment_plans'] );

            if ( false !== $index ) {
                array_splice( $rule['payment_plans'], $index, 1 );
                $rules[ $key ]['payment_plans'] = $rule['payment_plans'];
                $rule_plan_names = array();

                foreach ( $rule['payment_plans'] as $plan ) {
                    $rule_plan_names[] = $exist_plans[ $plan ]['plan_name'];
                }
                $rules[ $key ]['rule_plan_names'] = implode(', ', $rule_plan_names);
            }
        }
        update_option( 'vicodin_deposit_rule', $rules );
		wp_die();
	}

	public function get_home() {
		?>
        <h2><?php esc_attr_e( 'Manage Payment Plans',
				'vico-deposit-and-installment' ); ?></h2>
        <a href="#/plan-new" class="vi-ui button primary"
           id="vicodin-new-plan"><?php esc_attr_e( 'New Plan',
				'vico-deposit-and-installment' ); ?></a>
        <table class="vi-ui table">
            <thead>
            <tr>
                <th><?php esc_attr_e( 'Plan name',
						'vico-deposit-and-installment' ); ?></th>
                <th><?php esc_attr_e( 'Duration',
						'vico-deposit-and-installment' ); ?></th>
                <th colspan="2"></th>
            </tr>
            </thead>
            <tbody>
			<?php
			$payment_plans = get_option( 'vicodin_payment_plan' );
			if ( ! empty ( $payment_plans ) ) {
				foreach ( $payment_plans as $plan ) {
					?>
                    <tr>
                        <td><?php echo esc_html( $plan['plan_name'] ); ?></td>
                        <td><?php echo esc_html( $plan['duration'] ); ?></td>
                        <td>
                            <div class="vi-ui toggle checkbox">
                                <input type="checkbox" name="plan_active"
                                       id="vicodin-enable" <?php echo $plan['plan_active']
									? 'checked' : '' ?>
                                       data-id="<?php echo esc_attr( $plan['plan_id'] ) ?>"
                                       class="vicodin-plan-enable">
                                <label></label>
                            </div>
                        </td>
                        <td class="right aligned">
                            <a href="#/plan/<?php echo esc_attr( $plan['plan_id'] ) ?>"
                               class="vi-ui circular primary icon button vicodin-edit-plan">
                                <i class="edit icon"></i>
                            </a>
                            <button class="vi-ui circular red icon button vicodin-delete-plan mr-1 ml-1"
                                    data-id="<?php echo esc_attr( $plan['plan_id'] ) ?>">
                                <i class="trash icon"></i>
                            </button>
                        </td>
                    </tr>
					<?php
				}
			}
			?>
            </tbody>
        </table>
		<?php
		wp_die();
	}

	public function update_plan() {
		if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}
		$exist_plans = get_option( 'vicodin_payment_plan' );
		$data       = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data       = json_decode( $data, true );

		$plan = $exist_plans[ $data['plan_id'] ];

		if ( isset( $plan ) ) {
			$plan['plan_active']            = $data['plan_active'];
			$exist_plans[ $data['plan_id'] ] = $plan;
		}

		update_option( 'vicodin_payment_plan', $exist_plans );

		wp_send_json_success();

		wp_die();
	}
}
