<?php defined( 'ABSPATH' ) || exit; ?>

<div class="woocommerce_attribute wc-metabox postbox closed">
    <h3>
        <div class="handlediv" title="Click to toggle"></div>
        <a href="#" class="depart-edit-plan edit"><?php esc_html_e( 'Edit', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></a>
        <a href="#" class="depart-remove-plan delete" rel="85"><?php esc_html_e( 'Remove', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></a>
        <div class="tips sort" data-tip="<?php esc_attr_e( 'Drag and drop, or click to set custom plan.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>"></div>
        <strong class="depart-plan_name"> <?php echo esc_html( $custom_plan['plan_name'] ); ?> </strong>
    </h3>
    <div class="woocommerce_variable_attributes wc-metabox-content">
        <table class="depart-plan-schedule">
            <tbody>
            <tr>
                <td colspan="3">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => 'depart_plan_name',
							'name'          => 'plan_name',
							'value'         => $custom_plan['plan_name'],
							'placeholder'   => __( 'Plan name required', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'label'         => __( 'Plan name', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'wrapper_class' => 'form-row',
							'desc_tip'      => true,
							'description'   => __( 'Plan name is used to display in the frontend.', 'depart-deposit-and-part-payment-for-woocommerce' )
						)
					);
					?>
                </td>
                <td colspan="2">
					<?php
					woocommerce_wp_select(
						array(
							'id'            => 'depart_unit_type',
							'name'          => 'unit-type',
							'label'         => __( 'Type', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'options'       => array(
								'fixed'      => __( 'Fixed', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'percentage' => __( 'Percentage', 'depart-deposit-and-part-payment-for-woocommerce' )
							),
							'value'         => $custom_plan['unit-type'],
							'wrapper_class' => 'form-row',
						)
					);
					?>
                </td>
            </tr>
            <tr>
                <td colspan="3">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => '',
							'name'          => 'deposit-amount',
							'value'         => $custom_plan['deposit'],
							'placeholder'   => __( 'f.e. 50', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'label'         => __( 'Pay immediately', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'wrapper_class' => 'form-row',
							'desc_tip'      => true,
							'description'   => 'Deposit payment amount',
						)
					);
					?>
                </td>
                <td colspan="2">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => '',
							'name'          => 'deposit-fee',
							'value'         => $custom_plan['deposit_fee'],
							'placeholder'   => __( 'f.e. 3', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'label'         => __( 'Fees', 'depart-deposit-and-part-payment-for-woocommerce' ),
							'wrapper_class' => 'form-row',
							'desc_tip'      => true,
							'description'   => __( 'Fees will be calculated based on the deposit amount and compounded with it when checkout.', 'depart-deposit-and-part-payment-for-woocommerce' ),
						)
					);
					?>
                </td>
            </tr>
			<?php
			foreach ( $custom_plan['plan_schedule'] as $pos => $partial ) {
				?>
                <tr>
                    <td>
						<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '',
								'name'          => 'partial-payment',
								'value'         => $partial['partial'],
								'placeholder'   => __( 'f.e. 50', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'label'         => __( 'Pay later', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'wrapper_class' => 'form-row',
								'desc_tip'      => true,
								'description'   => 'Part payment amount',
							)
						);
						?>

                    </td>
                    <td>
                        <p class="form-field form-row">
                            <label for=""><?php esc_html_e( 'After', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            <input type="number" name="partial-day" class="short wc_input_decimal" value="<?php echo esc_html( $partial['after'] ) ?>" step="1">
                        </p>
                    </td>
                    <td>
						<?php woocommerce_wp_select(
							array(
								'id'            => '',
								'name'          => 'partial-date',
								'label'         => __( 'Type', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'options'       => array(
									'day'   => __( 'Day(s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
									'month' => __( 'Month(s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
									'year'  => __( 'Year(s)', 'depart-deposit-and-part-payment-for-woocommerce' )
								),
								'value'         => $partial['date_type'],
								'wrapper_class' => 'form-row',
								'desc_tip'      => true,
								'description'   => __( 'Value will be calculated after the previous payment.', 'depart-deposit-and-part-payment-for-woocommerce' )
							) ); ?>
                    </td>
                    <td>
						<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '',
								'name'          => 'partial-fee',
								'value'         => $partial['fee'],
								'placeholder'   => __( 'f.e. 3', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'label'         => __( 'Fees', 'depart-deposit-and-part-payment-for-woocommerce' ),
								'wrapper_class' => 'form-row',
								'desc_tip'      => true,
								'description'   => __( 'Fees will be calculated based on the part payment amount and compounded with it when checkout.', 'depart-deposit-and-part-payment-for-woocommerce' ),
							)
						);
						?>
                    </td>
                    <td>
                        <div class="partial-actions">
                            <div class="increase-field <?php echo ( count( $custom_plan['plan_schedule'] ) === ++ $pos ) ? '' : 'hidden' ?>">
                                <span class="dashicons dashicons-plus"></span>
                            </div>
                            <div class="decrease-field <?php echo ( count( $custom_plan['plan_schedule'] ) === 1 ) ? 'hidden' : '' ?>">
                                <span class="dashicons dashicons-minus"></span>
                            </div>
                        </div>
                    </td>
                </tr>
				<?php
			}
			?>
            </tbody>
            <tfoot>
            <tr>
                <td>
                    <span><?php esc_html_e( 'Total: ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></span>
                    <span class="partial-total"><?php echo esc_html( $custom_plan['total'] ); ?></span>
                    <span class="woo-currency-symbol"><?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?></span>
                </td>
                <td>
					<?php esc_html_e( 'Duration : ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                    <span class="partial-duration"><?php echo esc_html( $custom_plan['duration'] ) ?></span>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
