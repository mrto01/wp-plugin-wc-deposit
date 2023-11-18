<div class="woocommerce_attribute wc-metabox postbox closed">
	<h3>
		<div class="handlediv" title="Click to toggle"></div>
		<a href="#" class="vicodin-edit-plan edit"><?php esc_html_e( 'Edit', 'vico-deposit-and-installment' ); ?></a>
		<a href="#" class="vicodin-remove-plan delete" rel="85"><?php esc_html_e( 'Remove', 'vico-deposit-and-installment' ); ?></a>
		<strong class="vicodin-plan-name"> <?php esc_html_e( $custom_plan['plan-name'], 'vico-deposit-and-installment' ); ?> </strong>
	</h3>
	<div class="woocommerce_variable_attributes wc-metabox-content hidden">
	<table class="vicodin-plan-schedule">
		<tbody>
		<tr>
			<td>
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => 'vicodin_plan_name',
						'name'          => 'plan-name',
						'value'         => $custom_plan['plan-name'],
						'placeholder'   => 'Plan name required',
						'label'         => __( 'Plan name', 'vico-deposit-and-installment'),
						'wrapper_class' => 'form-row',
					)
				);
				?>
			</td>
			<td></td>
			<td></td>
			<td>
				<?php
				woocommerce_wp_select(
					array(
						'id'            => 'vicodin_unit_type',
						'name'          => 'unit-type',
						'label'         => __( 'Type', 'vico-deposit-and-installment' ),
						'options'       => array(
							'fixed'      => __( 'Fixed', 'vico-deposit-and-installment' ),
							'percentage' => __( 'Percentage', 'vico-deposit-and-installment' )
						),
                        'value'          => $custom_plan['unit-type'],
						'wrapper_class' => 'form-row',
					)
				);
				?>
			</td>
		</tr>
		<tr>
			<td>
                <p class="form-field form-row">
                    <label ><?php esc_html_e( 'Pay immediately', 'vico-deposit-and-installment'); ?></label>
                    <input type="text" name="deposit-amount" class="short wc_input_price" value="<?php esc_attr_e($custom_plan['deposit']); ?>">
                </p>
			</td>
			<td></td>
			<td></td>
			<td>
                <p class="form-field form-row">
                    <label for="deposit_fee"><?php esc_html_e( 'Fee', 'vico-deposit-and-installment'); ?></label>
                    <input type="text" name="deposit-fee" class="short wc_input_price" id="partial_fee" value="<?php esc_attr_e($custom_plan['deposit-fee']); ?>">
                </p>
			</td>
		</tr>
		<?php
            foreach( $custom_plan['plan-schedule'] as $partial){
                ?>
                <tr>
                    <td>
                        <p class="form-field form-row">
                            <label for=""><?php esc_html_e( 'Amount', 'vico-deposit-and-installment'); ?></label>
                            <input type="text" name="partial-payment" class="short wc_input_price" value="<?php esc_attr_e( $partial['partial'] ) ?>">
                        </p>
                    </td>
                    <td>
                        <p class="form-field form-row">
                            <label for=""><?php esc_html_e( 'After', 'vico-deposit-and-installment'); ?></label>
                            <input type="number" name="partial-day" class="short" value="<?php esc_html_e( $partial['after'] ) ?>">
                        </p>
                    </td>
                    <td>
                        <?php woocommerce_wp_select( array(
	                        'id'            => 'partial-date',
	                        'name'          => 'partial-date',
	                        'label'         => __( 'Type', 'vico-deposit-and-installment' ),
	                        'options'       => array(
		                        'day'       => __( 'Day(s)', 'vico-deposit-and-installment' ),
		                        'month'     => __( 'Month(s)', 'vico-deposit-and-installment' ),
                                'year'      => __( 'Year(s)', 'vico-deposit-and-installment' )
	                        ),
	                        'value'          => $partial['date-type'],
	                        'wrapper_class' => 'form-row',
                        ) ); ?>
                    </td>
                    <td>
                        <p class="form-field form-row">
                            <label for=""><?php esc_html_e( 'Fee', 'vico-deposit-and-installment'); ?></label>
                            <input type="text" name="partial-fee" class="short wc_input_price" value="<?php esc_html_e( $partial['fee']) ; ?>">
                        </p>
                    </td>
                    <td>
                        <div class="partial-actions">
                            <span class="dashicons dashicons-plus increase-field"></span>
                            <span class="dashicons dashicons-minus decrease-field hidden"></span>
                        </div>
                    </td>
                </tr>
            <?php
            }
        ?>
		</tbody>
		<tfoot >
		<tr>
			<td>
				<span><?php esc_html_e( 'Total: ', 'vico-deposit-and-installment' ); ?></span>
				<span class="partial-total"><?php esc_html_e( $custom_plan['total'] ); ?></span>
				<span class="woo-currency-symbol"><?php echo get_woocommerce_currency_symbol() ?></span>
			</td>
			<td>
				<?php esc_html_e( 'Duration : ','vico-deposit-and-installment' ); ?>
				<span class="partial-duration"><?php esc_html_e( $custom_plan['duration'], 'vico-deposit-and-installment' ) ?></span>
			</td>
		</tr>
		</tfoot>
	</table>
</div>
</div>