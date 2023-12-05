<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace VicoDIn\Inc;
use VicoDIn\Inc\Emails\Email_Deposit_paid;
use VicoDIn\Inc\Emails\Email_Partial_paid;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined( 'ABSPATH' ) || exit;

class Deposit_Backend {
	public $slug;

	public $dist_url;

	public $deposit_type;

	protected static $instance = null;

	public function __construct() {

		$this->slug = VICODIN_CONST['slug'];
		$this->dist_url = VICODIN_CONST['dist_url'];
		$this->deposit_type = VICODIN_CONST['order_type'];

		if ( vicodin_check_wc_active() ) {
			add_filter( 'woocommerce_email_classes', array( $this, 'vicodin_email_classes' ) );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_deposit_tab' ) );
			add_filter( 'woocommerce_product_data_panels', array( $this, 'woocommerce_deposit_tab_content' ) );
            add_action( 'woocommerce_process_product_meta', array( $this, 'vicodin_process_product_meta' ) );

//            In order management page
            add_filter( 'admin_body_class', array( $this, 'vicodin_admin_body_class' ) );
			add_action( 'add_meta_boxes', array( $this, 'vicodin_partial_payments_metabox' ), 31, 2);
            add_action( 'woocommerce_process_shop_order_meta', array( $this, 'vicodin_process_shop_order_meta' ) );
			add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'vicodin_admin_order_totals_after_total'));
            add_action( 'woocommerce_order_after_calculate_totals', array( $this,'vicodin_recalculate_totals' ), 11, 2 );
            add_action( 'wp_ajax_vicodin_reload_payment_meta_box', array( $this, 'vicodin_reload_payment_meta_box' ) );

//            Manage order actions
			add_action('woocommerce_before_delete_order', array($this, 'vicodin_delete_partial_orders'),10, 2 );
			add_action( 'woocommerce_trash_order', array( $this, 'vicodin_trash_partial_orders' ) );
			add_action( 'woocommerce_untrash_order', array($this, 'vicodin_untrash_partial_orders'));
			add_action( 'woocommerce_before_trash_order', array( $this, 'vicodin_prevent_user_trash_partial_orders'), 10, 2 );
		}

		add_action( 'wp_ajax_vicodin_get_new_plan_template', array( $this, 'get_new_plan_template' ) );
        add_action( 'wp_ajax_vicodin_save_custom_plans', array( $this, 'save_custom_plans'));
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function vicodin_email_classes( $email ) {
		$email['Vicodin_Email_Deposit_Paid'] = new Email_Deposit_paid();
		$email['Vicodin_Email_Partial_Paid'] = new Email_Partial_paid();

		return $email;
	}
	public function woocommerce_deposit_tab( $tabs ) {
		$tabs['vicodin_deposit'] = array(
			'label'    => __( 'Deposit', 'vico-deposit-and-installment' ),
			'target'   => 'vicodin_deposits_tab_data',
			'class'    => array(),
			'priority' => 50,
		);

		return $tabs;
	}

	public function woocommerce_deposit_tab_content() {
		$vicodin_settings = get_option( 'vicodin_deposit_setting' );
		if ( $vicodin_settings['enabled'] ) {
			global $post;
			$product = wc_get_product( $post->ID );
            $plans = get_option( 'vicodin_payment_plan' );
            $exists_plans = $product->get_meta('vicodin_exists_plans' );
            $plan_ids_saved = array();
            if ( is_array( $exists_plans ) && ! empty( $exists_plans ) ) {
	            foreach ( $exists_plans as $plan ) {
		            $plan_ids_saved[] = $plan['plan_id'];

	            }
            }
            $deposit_disable = $product->get_meta('vicodin_deposit_disabled' );
            $deposit_type = empty( $product->get_meta( 'vicodin_deposit_type' ) ) ? 'global' : $product->get_meta( 'vicodin_deposit_type' );
            foreach ( $plans as $plan ) {
                $plan_options[ $plan['plan_id'] ] = $plan['plan_name'];
            }
				?>
                <div id="vicodin_deposits_tab_data" class="panel hidden">
                    <div class="woocommerce_options_panel">
                        <div class="options_group">
		                    <?php
		                    woocommerce_wp_checkbox( array(
			                    'id'            => 'vicodin_deposit_disabled',
                                'name'          => 'vicodin_deposit_disabled',
			                    'label'         => esc_html__( 'Disable Deposit', 'vico-deposit-and-installment' ),
			                    'description'   => esc_html__( 'Disabled deposit feature for this product', 'vico-deposit-and-installment' ),
			                    'wrapper_class' => 'form-row form-row-full',
                                'cbvalue'       => 'yes',
                                'value'         => $deposit_disable
		                    ) );

		                    woocommerce_wp_select( array(
			                    'id'            => 'vicodin_deposit_type',
			                    'label'         => esc_html__( 'Deposit type', 'vico-deposit-and-installment' ),
			                    'options'       => array(
                                    'global' => esc_html__( 'Global', 'vico-deposit-and-installment' ),
                                    'custom' => esc_html__( 'Custom', 'vico-deposit-and-installment' )
			                    ),
			                    'wrapper_class' => 'form-row form-row-full',
                                'value'         => $deposit_type
		                    ) );

		                    ?>
                        </div>
                    </div>
                    <div class="wc-metaboxes-wrapper vicodin-loader <?php echo ( 'global' === $deposit_type ) ? 'hidden' : '' ?>">
                        <div class="toolbar toolbar-top">
	                       <div class="actions">
                               <span class="button vicodin-new-custom-plan"><?php esc_html_e( 'New custom plan', 'vico-deposit-and-installment' ); ?></span>
                               <select name="vicodin_deposit_plan" multiple id="vicodin_deposit_plan">
                                   <?php foreach ( $plan_options as $plan_id => $plan_name ) { ?>
                                       <option value="<?php echo esc_attr( $plan_id ); ?>" <?php echo in_array( $plan_id, $plan_ids_saved ) ? 'selected' : '' // phpcs:ignore WordPress.PHP.StrictInArray ?>><?php echo esc_html( $plan_name ); ?></option>
                                    <?php } ?>
                               </select>
                           </div>
                        </div>
                       <div class="wc-metaboxes">
	                       <?php
	                       $custom_plans = get_post_meta( $product->get_id(), 'vicodin_custom_plans',true);
	                       if ( ! empty( $custom_plans ) ) {
		                       foreach ( $custom_plans as $custom_plan ) {
			                       include __DIR__ . '/views/template-plan-metabox.php';
		                       }
	                       }
	                       ?>
                        </div>
                        <div class="toolbar">
                            <span class="button vicodin-save-custom-plan button-primary"><?php esc_html_e( 'Save plans', 'vico-deposit-and-installment' ); ?></span>
                        </div>
                    </div>
                </div>
				<?php

		} else {
			?>
            <div id="vicodin_deposits_tab_data" class="woocommerce_options_panel hidden">
                <div class="options_group">
                    <h3><?php echo esc_html__( 'Deposit Disabled', 'vico-deposit-and-installment' ); ?></h3>
                    <p><?php esc_html_e( 'Please enable the deposit option from our', 'vico-deposit-and-installment' ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=vicodin_setting' ) ); ?>" target="_blank">settings</a> page.</p>
                </div>
            </div>
			<?php
		}
	}

    public function get_new_plan_template() {
        $custom_plan = array(
            'plan_name'     => __('New custom plan', 'vico-deposit-and-installment'),
            'unit-type'     => 'fixed',
            'deposit'       => '',
            'deposit_fee'   => '',
            'duration'      => '',
            'total'         => 0,
            'plan_schedule' => array(
                array(
                    'partial'   => '',
                    'after'     => '',
                    'date_type' => 'day',
                    'fee'       => ''
                )
            ),
        );
        include __DIR__ . '/views/template-plan-metabox.php';
        wp_die();
    }

    public function save_custom_plans() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'], $_POST['post_id'] ) ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'vicodin_nonce' ) ) {
            return;
        }
	    $data       = sanitize_text_field( wp_unslash( $_POST['data'] ) );
	    $data       = json_decode( $data, true );
        $post_id    = sanitize_key( ( $_POST['post_id'] ) );

        $exists_plan_ids = array();

        if ( isset ( $_POST['exists_plans'] ) ) {
	        $exists_plan_ids = json_decode( wp_unslash( $_POST['exists_plans'] ), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $exists_plan_ids = array_map( 'sanitize_key', $exists_plan_ids );
        }

        $plans = get_option( 'vicodin_payment_plan' );
        $exists_plans = array();
        $count_id = count( $data );
        foreach ( $exists_plan_ids as $plan_id ) {
            if ( isset( $plans[ $plan_id ] ) ) {
                $exists_plans[ $count_id ] = $plans[ $plan_id ];
            }
            $count_id++;
        }

	    update_post_meta( $post_id, 'vicodin_custom_plans', $data );

        update_post_meta( $post_id, 'vicodin_exists_plans', $exists_plans );

        wp_send_json_success( 'save plan success!' );

        wp_die();
    }

	public function vicodin_process_product_meta( $post_id ) {

        $product = wc_get_product($post_id);

        if ( $product ) {
	        $disable = isset($_POST['vicodin_deposit_disabled']) ? sanitize_text_field( wp_unslash( $_POST['vicodin_deposit_disabled'] ) ) : 'no';
	        $type = isset( $_POST['vicodin_deposit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vicodin_deposit_type'] ) ) : 'global';
	        $product->update_meta_data( 'vicodin_deposit_disabled', $disable );
	        $product->update_meta_data( 'vicodin_deposit_type', $type );

	        $product->save();
        }
	}

	public function vicodin_process_shop_order_meta( $post_id ) {
		$order = wc_get_order( $post_id );
		$old_status = 'wc-' . $order->get_status();

		$completed_ids = array();
        if ( isset( $_POST['vicodin_partial_payment_completed'] ) ) {
            $completed_ids = array_map( 'sanitize_text_field', wp_unslash( $_POST['vicodin_partial_payment_completed'] ) );
        }
		$args = array(
			'post_parent'     => $post_id,
            'parent_order_id' => $post_id,
			'post_type'       => 'vwcdi_partial_order',
			'numberposts'     => -1,
			'orderby'         => 'ID',
			'order'           => 'ASC',
		);

        $suborders = wc_get_orders( $args );

		if ( ! is_array( $completed_ids ) ) {
            $completed_ids = array();
		}

        if ( is_array( $suborders ) && ! empty( $suborders ) ) {
            foreach ( $suborders as $suborder ) {
                $id = $suborder->get_id();
                if ( in_array( $id, $completed_ids ) ) {
                    $suborder->set_status('completed');
                }else {
                    $is_paid = $suborder->get_payment_method_title();
                    if ( $is_paid ) {
                        $suborder->set_status('on-hold');
                    }else {
                        $suborder->set_status('pending');
                    }
                }

                $suborder->save();

            }
        }

		$order = wc_get_order( $post_id );
        $new_status = 'wc-' . $order->get_status();

        $post_status = isset( $_POST['order_status'] ) ? sanitize_text_field( wp_unslash( $_POST['order_status'] ) ) : '';

		if ( $old_status === $post_status ) {
            $_POST['order_status'] = $new_status;
		}

	}
	function vicodin_admin_body_class( $classes ) {
		$current_screen = get_current_screen();
		if ( 'edit-vwcdi_partial_order' == $current_screen->id ) {
			return "$classes post-type-shop_order";
		} else {
			return $classes;
		}
	}



    public function vicodin_partial_payments_metabox( $post_type, $post ){
	    $order = ( $post instanceof \WP_Post ) ? wc_get_order( $post->ID ) : wc_get_order( get_the_id() );
	    $main_order_screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	    $sub_order_screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? 'woocommerce_page_wc-orders--vwcdi_partial_order' : VICODIN_CONST['order_type'] ;
	    if ( $order ) {
		    if ( $order->get_type() === VICODIN_CONST['order_type'] ) {
			    add_meta_box(
				    'vicodin_deposit_parent_order',
				    esc_html__( 'Partial Payments',
					    'vico-deposit-and-installment' ),
				    array( $this, 'vicodin_original_order_details' ),
				    $sub_order_screen,
				    'side',
				    'high'
			    );
		    } else {
			    $is_deposit = $order->get_meta( '_vicodin_is_deposit_order' ) ?? false;
			    if ( $is_deposit ) {
				    add_meta_box(
					    'vicodin_deposit_partial_payments',
					    esc_html__( 'Installment plan',
						    'vico-deposit-and-installment' ),
					    array( $this, 'partial_payments_summary' ),
					    $main_order_screen,
					    'normal',
					    'high'
				    );
			    }
		    }
	    }
    }

    public function partial_payments_summary( $post ) {
        $order = ( $post instanceof \WP_Post ) ? wc_get_order( $post->ID ) : wc_get_order( get_the_id() );
        $is_deposit = $order->get_meta('_vicodin_is_deposit_order');
        if ( $is_deposit ) {
            include __DIR__ . '/views/installment-plan-summary.php';
        }
    }

    public function vicodin_original_order_details() {
	    $order = wc_get_order( get_the_id() );
	    if ( $order ) {
		    $parent = wc_get_order($order->get_parent_id());
		    if ( $parent ) {
			    ?>
                <p><?php echo wp_kses_post( sprintf(__('This is a partial payment for order %s', 'vico-deposit-and-installment'), $parent->get_order_number() ) ); ?></p>
                <a class="button btn" href="<?php echo esc_url($parent->get_edit_order_url()); ?> "> <?php esc_html_e('View', 'vico-deposit-and-installment'); ?> </a>
			    <?php
		    }
	    }
    }

    public function vicodin_admin_order_totals_after_total( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order->get_type() == VICODIN_CONST['order_type'] ) {
            return;
        }

        $is_deposit = $order->get_meta( '_vicodin_is_deposit_order' );
        if ( $is_deposit ) {
            $deposit = $order->get_meta( '_vicodin_deposit_amount' );
            $remaining = $order->get_meta( '_vicodin_future_payment' );
            $interest = $order->get_meta( '_vicodin_interest_total' );
            ?>
            <tr>
                <td class="label"><?php esc_html_e('Deposit', 'deposits-partial-payments-for-woocommerce'); ?> : </td>
                <td width="1%"></td>
                <td class="total paid"><?php echo wp_kses_post( wc_price($deposit, array('currency' => $order->get_currency()))); ?></td>
            </tr>
            <tr class="vicodin-remaining">
                <td class="label"><?php esc_html_e('Future payments', 'deposits-partial-payments-for-woocommerce'); ?>:</td>
                <td width="1%"></td>
                <td class="total balance"><?php echo wp_kses_post( wc_price($remaining, array('currency' => $order->get_currency()))); ?></td>
            </tr><tr class="vicodin-interest">
                <td class="label"><?php esc_html_e('Total interest', 'deposits-partial-payments-for-woocommerce'); ?>:</td>
                <td width="1%"></td>
                <td class="total balance"><?php echo wp_kses_post( wc_price($interest, array('currency' => $order->get_currency()))); ?></td>
            </tr>
            <?php
        }
    }

    public function vicodin_recalculate_totals( $and_taxes, $order ) {

        $schedule = $order->get_meta( 'vicodin_deposit_payment_schedule' );

        if ( is_array( $schedule ) && ! empty( $schedule ) ) {
            $unpaid_payments = array();
            $unpaid_total = 0.0;
            $original_total = 0.0;
            $interest_total = floatval( $order->get_meta('_vicodin_interest_total') );

            foreach ( $schedule as $payment ) {
                $partial_order = wc_get_order( $payment['id'] );

                if ( $partial_order ) {

	                if ( 'deposit' !== $payment['type'] && 'completed' !== $partial_order->get_status() ) {
		                $unpaid_payments[] = $partial_order;
		                $unpaid_total += floatval( $partial_order->get_total() );
	                }

	                $original_total += floatval( $partial_order->get_total() );
                }
            }
            $difference = floatval( $order->get_total() ) + $interest_total - $original_total ;
            if ( $difference > 0 || $difference < 0 ) {
                $positive = $difference > 0 ;

                $difference = abs( $difference );

                foreach ( $unpaid_payments as $payment ) {
                    $percentage = floatval( $payment->get_total() ) / $unpaid_total * 100;
                    $amount = $percentage * $difference / 100;
	                $remaining = $difference;
	                $count = 0;

	                if ( count( $unpaid_payments ) === $count ) {
		                $amount = $remaining;
	                } else {
		                $remaining -= $amount;
	                }

                    if ( $positive ) {
	                    foreach ( $payment->get_fees() as $item ) {
		                    $item->set_total(floatval($item->get_total() ) + $amount);
		                    $item->save();
	                    }
                    }else {
	                    foreach ( $payment->get_fees() as $item ) {
		                    $item->set_total(floatval( $item->get_total() ) - $amount);
		                    $item->save();
	                    }
                    }

	                $payment->calculate_totals(false);

	                $payment->save();
                }

                $future_payment = $order->get_meta( '_vicodin_future_payment' );

	            if ( $positive ) {
		            $future_payment += $difference;
	            } else {
		            $future_payment -= $difference;
	            }

                $order->update_meta_data( '_vicodin_future_payment', floatval( $future_payment ) );

                $order->save();
            }
        }
    }

    public function vicodin_reload_payment_meta_box() {
        check_ajax_referer( 'vicodin_nonce', 'nonce' );
	    if ( ! current_user_can('edit_shop_orders') ) {
		    wp_die();
	    }
	    $order_id = isset( $_POST['order_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) : 0;
	    $order = wc_get_order($order_id);

	    if ( $order ) {
		    ob_start();
		    include __DIR__ . '/views/installment-plan-summary.php';
		    $html = ob_get_clean();
		    wp_send_json_success( array('html' => $html) );
	    }
        wp_die();
    }
	public function vicodin_delete_partial_orders( $id, $order ) {
		if ( ! current_user_can('delete_posts') || ! $id ) {
			return;
		}

		if ( $order->get_type() === 'shop_order' ) {

			$schedule = $order->get_meta('vicodin_deposit_payment_schedule');

			if ( is_array( $schedule ) && ! empty( $schedule ) ) {
				foreach ( $schedule as $payment ) {
					if ( isset($payment['id'] ) && is_numeric( $payment['id'] ) ) {
						wp_delete_post( absint( $payment['id'] ), true );
					}
				}
			}
		}
	}

	public function vicodin_trash_partial_orders( $id ) {
		if ( ! current_user_can('delete_posts') || ! $id ) {
			return;
		}

        $order = wc_get_order( $id );
        if ( ! $order || $order->get_type() !== 'shop_order' ) {
            return;
        }

        $args = array (
            'post_parent'     => $id,
            'parent_order_id' => $id,
            'post_type'       => 'vwcdi_partial_order',
            'numberposts'     => -1,
        );

        $partial_orders = wc_get_orders( $args );
        remove_filter( 'pre_trash_post', array( $this, 'vicodin_prevent_user_trash_partial_orders' ) );
        if ( is_array( $partial_orders ) && ! empty( $partial_orders ) ) {
            foreach ( $partial_orders as $partial_order ) {
	            add_post_meta( $partial_order->get_id(), '_wp_trash_meta_status', $partial_order->get_status() );
	            add_post_meta( $partial_order->get_id(), '_wp_trash_meta_time', time() );
	            $partial_order->set_status( 'trash' );
                $partial_order->save();
            }
        }
        add_filter( 'pre_trash_post', array( $this, 'vicodin_prevent_user_trash_partial_orders' ) );
	}

    public function vicodin_untrash_partial_orders( $id ) {
	    $order = wc_get_order( $id );
	    if ( ! $order || $order->get_type() !== 'shop_order' ) {
		    return;
	    }

	    $args = array(
		    'post_parent'     => $id,
		    'parent_order_id' => $id,
		    'post_type'       => 'vwcdi_partial_order',
		    'numberposts'     => -1,
            'status'          => 'trash'
	    );

	    $partial_orders = wc_get_orders( $args );

	    if ( is_array( $partial_orders ) && ! empty( $partial_orders ) ) {
		    foreach ( $partial_orders as $partial_order ) {
			    $previous_status = get_post_meta( $partial_order->get_id(), '_wp_trash_meta_status', true );;
			    delete_post_meta( $partial_order->get_id(), '_wp_trash_meta_status' );
			    delete_post_meta( $partial_order->get_id(), '_wp_trash_meta_time' );
			    $partial_order->set_status( $previous_status );
			    $partial_order->save();
		    }
	    }
    }

	public function vicodin_prevent_user_trash_partial_orders( $id, $order ) {
		if ( $order->get_type() === $this->deposit_type ) {
            $parent = wc_get_order($order->get_parent_id());
            if ( $parent && $parent->get_status() != 'trash' ) {
	            $error_message = sprintf(__( 'Can not trash suborder %s. Because it\'s parent order is untrash.', 'vico-deposit-and-installment'), $id );
	            wp_die( $error_message, 'Error', array( 'response' => 403 ) );
            }
		}
	}
}