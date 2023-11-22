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
		$payment_plans = get_option( 'vicodin_payment_plan' );
		$plan_id       = $_GET['plan-id'] ?? '';
		$plan          = [];
		if ( $plan_id ) {
			if ( isset( $payment_plans[ $plan_id ] ) ) {
				$plan = $payment_plans[ $plan_id ];
			} else {
				wp_die( 'Plan not found!', 'Error',
					array( 'response' => 404 ) );
			}
		} else {
			$plan = [
				'plan-id'          => '',
				'plan-name'        => '',
				'plan-active'      => true,
				'plan-description' => '',
				'deposit'          => '',
				'deposit-fee'      => '',
				'plan-schedule'    => array(
					[
						'partial'   => '',
						'after'     => '',
						'date-type' => 'day',
						'fee'       => ''
					]
				),
				'duration'         => '',
				'total'    => 0
			];
		}

		?>
        <div class="vicodin-action-bar">
            <a href="#/" class="vi-ui button icon" id="vicodin-home-plan">
                <i class="angle double left icon"></i>
            </a>
			<?php
			if ( empty( $plan['plan-name'] ) ) {
				?>
                <h2><?php esc_attr_e( 'Add new plan',
						'vico-deposit-and-installment' ); ?></h2>
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
            <input type="hidden" name="plan-id"
                   value="<?php echo esc_attr( $plan['plan-id'] ) ?>">
            <div class="field">
                <label for="plan-name"><?php esc_html_e( 'Plan name',
						'vico-deposit-and-installment' ); ?></label>
                <input type="text"
                       name="plan-name"
                       id="plan-name"
                       value="<?php echo esc_attr( $plan['plan-name'] ) ?>"
                >
            </div>
            <div class="field four wide">
                <label for="plan-active"><?php esc_html_e( 'Active plan',
						'vico-deposit-and-installment' ); ?></label>
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" tabindex="0" class="hidden"
                           name="plan-active"
                           id="plan-active"
						<?php echo $plan['plan-active'] ? 'checked' : ''; ?>
                    >
                    <label for="plan-active"></label>
                </div>
            </div>
            <div class="field">
                <label for="plan-description"><?php esc_html_e( 'Plan description' ); ?></label>
                <textarea
                        name="plan-description"
                        id="plan-description" cols="30"
                        rows="5"><?php esc_html( $plan['plan-description'] ) ?></textarea>
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
                                       value="<?php echo esc_attr( $plan['deposit-fee'] ) ?>">
                                <span class="type-unit">%</span>
                            </div>
                        </td>
                        <td></td>
                    </tr>
		            <?php
		            $date_types = [
			            'day'   => 'Day(s)',
			            'month' => 'Month(s)',
			            'year'  => 'Year(s)'
		            ];
		            foreach ( $plan['plan-schedule'] as $partial ) {
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
							            foreach ( $date_types as $key => $type )
							            {
								            ?>
                                            <option value="<?php echo esc_attr( $key ) ?>"
									            <?php echo ( $key
									                         == $partial['date-type'] )
										            ? 'selected' : '' ?>>
									            <?php esc_html_e( $type,
										            'vico-deposit-and-installment' ); ?>
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
                                <div class="vi-ui circular button icon decrease-field <?php echo ( count( $plan['plan-schedule'] )
					                                                                               === 1 )
						            ? 'hidden' : '' ?>">
                                    <i class="minus icon"></i>
                                </div>
                                <div class="vi-ui circular button icon increase-field <?php echo( $partial
					                                                                              === end( $plan['plan-schedule'] )
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
		$plan_id = $data['plan-id'] ?? null;

		$existPlans = get_option( 'vicodin_payment_plan', array() );


		if ( $plan_id ) {
			$existPlans[ $plan_id ] = $data;
		} else {
			$plan_id = get_option( 'vicodin_id' ) ?? 2;
			$plan_id ++;
			$data['plan-id']        = $plan_id;
			$existPlans[ $plan_id ] = $data;
			update_option( 'vicodin_id', $plan_id );
		}

		update_option( 'vicodin_payment_plan', $existPlans );

		echo json_encode( $plan_id );

		wp_die();
	}

	public function delete_plan() {
		$existPlans = get_option( 'vicodin_payment_plan' );
		if ( isset( $_POST['plan-id'] ) ) {
			unset( $existPlans[ $_POST['plan-id'] ] );
			update_option( 'vicodin_payment_plan', $existPlans );
		}
		self::get_home();
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
                        <td><?php esc_html_e( $plan['plan-name'] ); ?></td>
                        <td><?php esc_html_e( $plan['duration'] ); ?></td>
                        <td>
                            <div class="vi-ui toggle checkbox">
                                <input type="checkbox" name="plan-active"
                                       id="vicodin-enable" <?php echo $plan['plan-active']
									? 'checked' : '' ?>
                                       data-id="<?php echo esc_attr( $plan['plan-id'] ) ?>"
                                       class="vicodin-plan-enable">
                                <label></label>
                            </div>
                        </td>
                        <td class="right aligned">
                            <a href="#/plan/<?php echo $plan['plan-id'] ?>"
                               class="vi-ui circular primary icon button vicodin-edit-plan">
                                <i class="edit icon"></i>
                            </a>
                            <button class="vi-ui circular red icon button vicodin-delete-plan mr-1 ml-1"
                                    data-id="<?php echo $plan['plan-id'] ?>">
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
		$existPlans = get_option( 'vicodin_payment_plan' );
		$data       = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data       = json_decode( $data, true );

		$plan = $existPlans[ $data['plan-id'] ];

		if ( isset( $plan ) ) {
			$plan['plan-active']            = $data['plan-active'];
			$existPlans[ $data['plan-id'] ] = $plan;
		}

		update_option( 'vicodin_payment_plan', $existPlans );

		echo json_encode( 'update plan success!' );

		wp_die();
	}
}
