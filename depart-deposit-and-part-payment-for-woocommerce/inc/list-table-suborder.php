<?php

namespace VicoDIn\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * List_Table_Suborder Class.
 */
class List_Table_Suborder extends List_Table {
	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $list_table_type;

	/**
	 * Constructor.
	 */
	public function __construct() {
        $this->list_table_type = DEPART_CONST['order_type'];
		parent::__construct();
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
		add_action( 'admin_print_scripts', array( $this, 'order_preview_template' ) );
		add_filter( 'get_search_query', array( $this, 'search_label' ) );
		add_filter( 'query_vars', array( $this, 'add_custom_query_var' ) );
		add_action( 'parse_query', array( $this, 'search_custom_fields' ) );
	}

	/**
	 * Render blank state.
	 */
	protected function render_blank_state() {}

	/**
	 * Define primary column.
	 *
	 * @return string
	 */
	protected function get_primary_column() {
		return 'order_number';
	}

	/**
	 * Get row actions to show in the list table.
	 *
	 * @param array   $actions Array of actions.
	 * @param WP_Post $post Current post object.
	 * @return array
	 */
	protected function get_row_actions( $actions, $post ) {
		return array();
	}

	/**
	 * Define hidden columns.
	 *
	 * @return array
	 */
	protected function define_hidden_columns() {
		return array(
			'shipping_address',
			'billing_address',
		);
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function define_sortable_columns( $columns ) {
		$custom = array(
			'order_number'        => 'ID',
            'parent_order_number' => 'ID',
			'order_total'         => 'order_total',
			'order_due_date'      => 'order_due_date',
            'order'
		);
		unset( $columns['comments'] );

		return wp_parse_args( $custom, $columns );
	}

	/**
	 * Define which columns to show on this screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function define_columns( $columns ) {
		$show_columns                        = array();
		$show_columns['cb']                  = $columns['cb'];
		$show_columns['order_number']        = __( 'Order', 'depart-deposit-and-part-payment-for-woocommerce' );
		$show_columns['parent_order_number'] = __( 'Parent order', 'depart-deposit-and-part-payment-for-woocommerce' );
		$show_columns['order_due_date']      = __( 'Due date', 'depart-deposit-and-part-payment-for-woocommerce' );
		$show_columns['order_status']        = __( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' );
		$show_columns['order_total']         = __( 'Total', 'depart-deposit-and-part-payment-for-woocommerce' );
		$show_columns['wc_actions']          = __( 'Actions', 'depart-deposit-and-part-payment-for-woocommerce' );

		wp_enqueue_script( 'wc-orders' );

		return $show_columns;
	}

	/**
	 * Define bulk actions.
	 *
	 * @param array $actions Existing actions.
	 * @return array
	 */
	public function define_bulk_actions( $actions ) {
		if ( isset( $actions['edit'] ) ) {
			unset( $actions['edit'] );
		}

		$actions['mark_processing'] = __( 'Change status to processing', 'depart-deposit-and-part-payment-for-woocommerce' );
		$actions['mark_on-hold']    = __( 'Change status to on-hold', 'depart-deposit-and-part-payment-for-woocommerce' );
		$actions['mark_completed']  = __( 'Change status to completed', 'depart-deposit-and-part-payment-for-woocommerce' );
		$actions['mark_cancelled']  = __( 'Change status to cancelled', 'depart-deposit-and-part-payment-for-woocommerce' );

		if ( wc_string_to_bool( get_option( 'woocommerce_allow_bulk_remove_personal_data', 'no' ) ) ) {
			$actions['remove_personal_data'] = __( 'Remove personal data', 'depart-deposit-and-part-payment-for-woocommerce' );
		}

		return $actions;
	}

	/**
	 * Pre-fetch any data for the row each column has access to it. the_order global is there for bw compat.
	 *
	 * @param int $post_id Post ID being shown.
	 */
	protected function prepare_row_data( $post_id ) {
		global $depart_suborder;

		if ( empty( $this->object ) || $this->object->get_id() !== $post_id ) {
			$this->object = wc_get_order( $post_id );
			$depart_suborder    = $this->object;
		}
	}

	/**
	 * Render column: order_number.
	 */
	protected function render_order_number_column( ) {
        $order = $this->object;
        $buyer = '';

        if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
            /* translators: 1: first name 2: last name */
            $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'depart-deposit-and-part-payment-for-woocommerce' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
        } elseif ( $order->get_billing_company() ) {
            $buyer = trim( $order->get_billing_company() );
        } elseif ( $order->get_customer_id() ) {
            $user  = get_user_by( 'id', $order->get_customer_id() );
            $buyer = ucwords( $user->display_name );
        }

        /**
         * Filter buyer name in list table orders.
         *
         * @since 3.7.0
         *
         * @param string   $buyer Buyer name.
         * @param WC_Order $order Order data.
         */
        $buyer = apply_filters( 'woocommerce_admin_order_buyer_name', $buyer, $order );

        if ( $order->get_status() === 'trash' ) {
            echo wp_kses_post( '<strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong>' );
        } else {
            echo wp_kses_post( '<a href="' . esc_url( $order->get_edit_order_url() ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>' );
        }
	}

    public function render_parent_order_number_column( ) {
        $order = $this->object;
        $parent_order = wc_get_order( $order->get_parent_id() );
        if ( $order->get_status() === 'trash' ) {
            if ( $parent_order ) {
                echo wp_kses_post( '<strong>#' . esc_attr( $parent_order->get_id() ) . '</strong>' );
            }
        } else {
            echo wp_kses_post( '<a href="#" class="order-preview" data-order-id="' . absint( $parent_order->get_id() ) . '" title="' . esc_attr( __( 'Preview', 'depart-deposit-and-part-payment-for-woocommerce' ) ) . '">' . esc_html( __( 'Preview', 'depart-deposit-and-part-payment-for-woocommerce' ) ) . '</a>' );
            echo wp_kses_post( '<a href="' . esc_url( $parent_order->get_edit_order_url() ) . '" class="order-view"><strong>#' . esc_attr( $parent_order->get_order_number() ) . '</strong></a>' );
        }
    }

	/**
	 * Render column: order_status.
	 */
	protected function render_order_status_column( ) {
        $order = $this->object;
        $tooltip = '';
        remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
        $comment_count = get_comment_count( $order->get_id() );
        add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
        $approved_comments_count = absint( $comment_count['approved'] );

        if ( $approved_comments_count ) {
            $latest_notes = wc_get_order_notes(
                array(
                    'order_id' => $order->get_id(),
                    'limit'    => 1,
                    'orderby'  => 'date_created_gmt',
                )
            );

            $latest_note = current( $latest_notes );

            if ( isset( $latest_note->content ) && 1 === $approved_comments_count ) {
                $tooltip = wc_sanitize_tooltip( $latest_note->content );
            } elseif ( isset( $latest_note->content ) ) {
                /* translators: %d: notes count */
                $tooltip = wc_sanitize_tooltip( $latest_note->content . '<br/><small style="display:block">' . sprintf( _n( 'Plus %d other note', 'Plus %d other notes', ( $approved_comments_count - 1 ), 'depart-deposit-and-part-payment-for-woocommerce' ), $approved_comments_count - 1 ) . '</small>' );
            } else {
                /* translators: %d: notes count */
                $tooltip = wc_sanitize_tooltip( sprintf( _n( '%d note', '%d notes', $approved_comments_count, 'depart-deposit-and-part-payment-for-woocommerce' ), $approved_comments_count ) );
            }
        }

        // Gracefully handle legacy statuses.
        if ( in_array( $order->get_status(), array( 'trash', 'draft', 'auto-draft' ), true ) ) {
            $status_name = ( get_post_status_object( $order->get_status() ) )->label;
        } else {
            $status_name = wc_get_order_status_name( $order->get_status() );
        }

        if ( $tooltip ) {
            printf( '<mark class="order-status %s tips" data-tip="%s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $order->get_status() ) ), wp_kses_post( $tooltip ), esc_html( $status_name ) );
        } else {
            printf( '<mark class="order-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $order->get_status() ) ), esc_html( $status_name ) );
        }
	}

	/**
	 * Render column: order_date.
	 */
	protected function render_order_date_column() {

	}
    protected function render_order_due_date_column() {
        $order = $this->object;
        $order_due_date = $order->get_meta( '_depart_partial_payment_date' );
        $order_timestamp = $order_due_date ?? '';
        if ( ! $order_timestamp ) {
            echo wp_kses_post( '&ndash;' );
            return;
        }

        // Check if the order due date is within the last 24 hours, and not in the future.
        if ( strtotime( '+1 day', time() ) > $order_timestamp && time() <= $order_timestamp ) {
            /* translators: %s: Order due date */
            $show_date = sprintf( _x( 'in %s', '%s = human-readable time difference', 'depart-deposit-and-part-payment-for-woocommerce' ), human_time_diff( time(), $order_timestamp ) );
        } elseif ( gmdate('Ymd', time() ) === gmdate( 'Ymd', $order_timestamp ) ) {
            $show_date = __('today', 'depart-deposit-and-part-payment-for-woocommerce');
        }else {
            $show_date = date_i18n( wc_date_format(), $order_timestamp );
        }
        printf(
            '<time datetime="%1$s" title="%2$s">%3$s</time>',
            esc_attr( $order_timestamp ),
            esc_html( date_i18n( wc_date_format(), strtotime( $order_timestamp ) ) ),
            esc_html( $show_date )
        );
	}

	/**
	 * Render column: order_total.
	 */
	protected function render_order_total_column() {
        $order = $this->object;
        if ( $order->get_payment_method_title() ) {
            /* translators: %s: method */
            printf( '<span class="tips" data-tip="' . esc_attr( sprintf( __( 'via %s', 'depart-deposit-and-part-payment-for-woocommerce' ), $order->get_payment_method_title() ) ) . '">' . wp_kses_post( $order->get_formatted_order_total() ) . '</span>' );
        } else {
            echo wp_kses_post( $order->get_formatted_order_total() );
        }
	}

	/**
	 * Render column: wc_actions.
	 */
	protected function render_wc_actions_column() {
        $order = $this->object;
        printf( '<p>' );

        /**
         * Fires before the order action buttons (within the actions column for the order list table)
         * are registered.
         *
         * @param WC_Order $order Current order object.
         * @since 6.7.0
         */
        do_action( 'woocommerce_admin_order_actions_start', $order );

        $actions = array();


        if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
            $actions['processing'] = array(
                'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
                'name'   => __( 'Processing', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'action' => 'processing',
            );
        }

        if ( $order->has_status( array( 'pending', 'on-hold', 'processing' ) ) ) {
            $actions['complete'] = array(
                'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
                'name'   => __( 'Complete', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'action' => 'complete',
            );
        }

        /**
         * Provides an opportunity to modify the action buttons within the order list table.
         *
         * @param array    $action Order actions.
         * @param WC_Order $order  Current order object.
         * @since 6.7.0
         */
        $actions = apply_filters( 'woocommerce_admin_order_actions', $actions, $order );

        echo wp_kses_post( wc_render_action_buttons( $actions ) );

        /**
         * Fires after the order action buttons (within the actions column for the order list table)
         * are rendered.
         *
         * @param WC_Order $order Current order object.
         * @since 6.7.0
         */
        do_action( 'woocommerce_admin_order_actions_end', $order );

        printf( '</p>' );
	}

	/**
	 * Render column: billing_address.
	 */
	protected function render_billing_address_column() {
	}

	/**
	 * Render column: shipping_address.
	 */
	protected function render_shipping_address_column() {
	}

	/**
	 * Template for order preview.
	 *
	 * @since 3.3.0
	 */
	public function order_preview_template() {
        $feature_enabled         = get_option('woocommerce_feature_custom_order_tables_enabled', 'no');
        $custom_order_enabled    = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
        $order_page_enabled = false;
        if ( $feature_enabled === 'yes' && $custom_order_enabled === 'yes' ) {
            $order_page_enabled = true;
        }

        $order_edit_url_placeholder =
            $order_page_enabled
                ? esc_url( admin_url( 'admin.php?page=wc-orders&action=edit' ) ) . '&id={{ data.data.id }}'
                : esc_url( admin_url( 'post.php?action=edit' ) ) . '&post={{ data.data.id }}';
        ?>
        <script type="text/template" id="tmpl-wc-modal-view-order">
            <div class="wc-backbone-modal wc-order-preview">
                <div class="wc-backbone-modal-content">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <mark class="order-status status-{{ data.status }}"><span>{{ data.status_name }}</span></mark>
                            <?php /* translators: %s: order ID */ ?>
                            <h1><?php echo esc_html( sprintf( __( 'Order #%s', 'depart-deposit-and-part-payment-for-woocommerce' ), '{{ data.order_number }}' ) ); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></span>
                            </button>
                        </header>
                        <article>
                            <?php do_action( 'woocommerce_admin_order_preview_start' );  ?>

                            <div class="wc-order-preview-addresses">
                                <div class="wc-order-preview-address">
                                    <h2><?php esc_html_e( 'Billing details', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                                    {{{ data.formatted_billing_address }}}

                                    <# if ( data.data.billing.email ) { #>
                                    <strong><?php esc_html_e( 'Email', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></strong>
                                    <a href="mailto:{{ data.data.billing.email }}">{{ data.data.billing.email }}</a>
                                    <# } #>

                                    <# if ( data.data.billing.phone ) { #>
                                    <strong><?php esc_html_e( 'Phone', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></strong>
                                    <a href="tel:{{ data.data.billing.phone }}">{{ data.data.billing.phone }}</a>
                                    <# } #>

                                    <# if ( data.payment_via ) { #>
                                    <strong><?php esc_html_e( 'Payment via', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></strong>
                                    {{{ data.payment_via }}}
                                    <# } #>
                                </div>
                                <# if ( data.needs_shipping ) { #>
                                <div class="wc-order-preview-address">
                                    <h2><?php esc_html_e( 'Shipping details', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                                    <# if ( data.ship_to_billing ) { #>
                                    {{{ data.formatted_billing_address }}}
                                    <# } else { #>
                                    <a href="{{ data.shipping_address_map_url }}" target="_blank">{{{ data.formatted_shipping_address }}}</a>
                                    <# } #>

                                    <# if ( data.shipping_via ) { #>
                                    <strong><?php esc_html_e( 'Shipping method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></strong>
                                    {{ data.shipping_via }}
                                    <# } #>
                                </div>
                                <# } #>

                                <# if ( data.data.customer_note ) { #>
                                <div class="wc-order-preview-note">
                                    <strong><?php esc_html_e( 'Note', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></strong>
                                    {{ data.data.customer_note }}
                                </div>
                                <# } #>
                            </div>

                            {{{ data.item_html }}}

                            <?php do_action( 'woocommerce_admin_order_preview_end' );  ?>
                        </article>
                        <footer>
                            <div class="inner">
                                {{{ data.actions_html }}}

                                <a class="button button-primary button-large" aria-label="<?php esc_attr_e( 'Edit this order', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>" href="<?php echo esc_url( $order_edit_url_placeholder ); ?>"><?php esc_html_e( 'Edit', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></a>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>
        <?php
	}

	/**
	 * Get items to display in the preview as HTML.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public static function get_order_preview_item_html( $order ) {
		$hidden_order_itemmeta = apply_filters(
			'woocommerce_hidden_order_itemmeta',
			array(
				'_qty',
				'_tax_class',
				'_product_id',
				'_variation_id',
				'_line_subtotal',
				'_line_subtotal_tax',
				'_line_total',
				'_line_tax',
				'method_id',
				'cost',
				'_reduced_stock',
				'_restock_refunded_items',
			)
		);

		$line_items = apply_filters( 'woocommerce_admin_order_preview_line_items', $order->get_items(), $order );
		$columns    = apply_filters(
			'woocommerce_admin_order_preview_line_item_columns',
			array(
				'product'  => __( 'Product', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'quantity' => __( 'Quantity', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'tax'      => __( 'Tax', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'total'    => __( 'Total', 'depart-deposit-and-part-payment-for-woocommerce' ),
			),
			$order
		);

		if ( ! wc_tax_enabled() ) {
			unset( $columns['tax'] );
		}

		$html = '
		<div class="wc-order-preview-table-wrapper">
			<table cellspacing="0" class="wc-order-preview-table">
				<thead>
					<tr>';

		foreach ( $columns as $column => $label ) {
			$html .= '<th class="wc-order-preview-table__column--' . esc_attr( $column ) . '">' . esc_html( $label ) . '</th>';
		}

		$html .= '
					</tr>
				</thead>
				<tbody>';

		foreach ( $line_items as $item_id => $item ) {

			$product_object = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$row_class      = apply_filters( 'woocommerce_admin_html_order_preview_item_class', '', $item, $order );

			$html .= '<tr class="wc-order-preview-table__item wc-order-preview-table__item--' . esc_attr( $item_id ) . ( $row_class ? ' ' . esc_attr( $row_class ) : '' ) . '">';

			foreach ( $columns as $column => $label ) {
				$html .= '<td class="wc-order-preview-table__column--' . esc_attr( $column ) . '">';
				switch ( $column ) {
					case 'product':
						$html .= wp_kses_post( $item->get_name() );

						if ( $product_object ) {
							$html .= '<div class="wc-order-item-sku">' . esc_html( $product_object->get_sku() ) . '</div>';
						}

						$meta_data = $item->get_all_formatted_meta_data( '' );

						if ( $meta_data ) {
							$html .= '<table cellspacing="0" class="wc-order-item-meta">';

							foreach ( $meta_data as $meta_id => $meta ) {
								if ( in_array( $meta->key, $hidden_order_itemmeta, true ) ) {
									continue;
								}
								$html .= '<tr><th>' . wp_kses_post( $meta->display_key ) . ':</th><td>' . wp_kses_post( force_balance_tags( $meta->display_value ) ) . '</td></tr>';
							}
							$html .= '</table>';
						}
						break;
					case 'quantity':
						$html .= esc_html( $item->get_quantity() );
						break;
					case 'tax':
						$html .= wc_price( $item->get_total_tax(), array( 'currency' => $order->get_currency() ) );
						break;
					case 'total':
						$html .= wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );
						break;
					default:
						$html .= apply_filters( 'woocommerce_admin_order_preview_line_item_column_' . sanitize_key( $column ), '', $item, $item_id, $order );
						break;
				}
				$html .= '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '
				</tbody>
			</table>
		</div>';

		return $html;
	}

	/**
	 * Get actions to display in the preview as HTML.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public static function get_order_preview_actions_html( $order ) {
		$actions        = array();
		$status_actions = array();

		if ( $order->has_status( array( 'pending' ) ) ) {
			$status_actions['on-hold'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=on-hold&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'On-hold', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'title'  => __( 'Change order status to on-hold', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'action' => 'on-hold',
			);
		}

		if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			$status_actions['processing'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Processing', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'title'  => __( 'Change order status to processing', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'action' => 'processing',
			);
		}

		if ( $order->has_status( array( 'pending', 'on-hold', 'processing' ) ) ) {
			$status_actions['complete'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Completed', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'title'  => __( 'Change order status to completed', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'action' => 'complete',
			);
		}

		if ( $status_actions ) {
			$actions['status'] = array(
				'group'   => __( 'Change status: ', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'actions' => $status_actions,
			);
		}

		return wc_render_action_buttons( apply_filters( 'woocommerce_admin_order_preview_actions', $actions, $order ) );
	}

	/**
	 * Get order details to send to the ajax endpoint for previews.
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public static function order_preview_get_order_details( $order ) {
		if ( ! $order ) {
			return array();
		}

		$payment_via      = $order->get_payment_method_title();
		$payment_method   = $order->get_payment_method();
		$payment_gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : array();
		$transaction_id   = $order->get_transaction_id();

		if ( $transaction_id ) {

			$url = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_transaction_url( $order ) : false;

			if ( $url ) {
				$payment_via .= ' (<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $transaction_id ) . '</a>)';
			} else {
				$payment_via .= ' (' . esc_html( $transaction_id ) . ')';
			}
		}

		$billing_address  = $order->get_formatted_billing_address();
		$shipping_address = $order->get_formatted_shipping_address();

		return apply_filters(
			'woocommerce_admin_order_preview_get_order_details',
			array(
				'data'                       => $order->get_data(),
				'order_number'               => $order->get_order_number(),
				'item_html'                  => self::get_order_preview_item_html( $order ),
				'actions_html'               => self::get_order_preview_actions_html( $order ),
				'ship_to_billing'            => wc_ship_to_billing_address_only(),
				'needs_shipping'             => $order->needs_shipping_address(),
				'formatted_billing_address'  => $billing_address ? $billing_address : __( 'N/A', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'formatted_shipping_address' => $shipping_address ? $shipping_address : __( 'N/A', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'shipping_address_map_url'   => $order->get_shipping_address_map_url(),
				'payment_via'                => $payment_via,
				'shipping_via'               => $order->get_shipping_method(),
				'status'                     => $order->get_status(),
				'status_name'                => wc_get_order_status_name( $order->get_status() ),
			),
			$order
		);
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param  string $redirect_to URL to redirect to.
	 * @param  string $action      Action name.
	 * @param  array  $ids         List of ids.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {
		$ids     = apply_filters( 'woocommerce_bulk_action_ids', array_reverse( array_map( 'absint', $ids ) ), $action, 'order' );
		$changed = 0;

		if ( 'remove_personal_data' === $action ) {
			$report_action = 'removed_personal_data';

			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );

				if ( $order ) {
					do_action( 'woocommerce_remove_order_personal_data', $order );
					$changed++;
				}
			}
		} elseif ( false !== strpos( $action, 'mark_' ) ) {
			$order_statuses = wc_get_order_statuses();
			$new_status     = substr( $action, 5 ); // Get the status name from action.
			$report_action  = 'marked_' . $new_status;

			// Sanity check: bail out if this is actually not a status, or is not a registered status.
			if ( isset( $order_statuses[ 'wc-' . $new_status ] ) ) {
				// Initialize payment gateways in case order has hooked status transition actions.
				WC()->payment_gateways();

				foreach ( $ids as $id ) {
					$order = wc_get_order( $id );
					$order->update_status( $new_status, __( 'Order status changed by bulk edit:', 'depart-deposit-and-part-payment-for-woocommerce' ), true );
					do_action( 'woocommerce_order_edit_status', $id, $new_status );
					$changed++;
				}
			}
		}

		if ( $changed ) {
			$redirect_to = add_query_arg(
				array(
					'post_type'   => $this->list_table_type,
					'bulk_action' => $report_action,
					'changed'     => $changed,
					'ids'         => join( ',', $ids ),
				),
				$redirect_to
			);
		}

		return esc_url_raw( $redirect_to );
	}

	/**
	 * Show confirmation message that order status changed for number of orders.
	 */
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		// Bail out if not on shop order list page.
		if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) ) {
			return;
		}

		$order_statuses = wc_get_order_statuses();
		$number         = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
		$bulk_action    = wc_clean( wp_unslash( $_REQUEST['bulk_action'] ) );

		// Check if any status changes happened.
		foreach ( $order_statuses as $slug => $name ) {
			if ( 'marked_' . str_replace( 'wc-', '', $slug ) === $bulk_action ) { // WPCS: input var ok, CSRF ok.
				/* translators: %d: orders count */
				$message = sprintf( _n( '%s order status changed.', '%s order statuses changed.', $number, 'depart-deposit-and-part-payment-for-woocommerce' ), number_format_i18n( $number ) );
				printf( '<div class="updated"><p>' . esc_html( $message ) . '</p></div>' );
				break;
			}
		}

		if ( 'removed_personal_data' === $bulk_action ) { // WPCS: input var ok, CSRF ok.
			/* translators: %d: orders count */
			$message = sprintf( _n( 'Removed personal data from %s order.', 'Removed personal data from %s orders.', $number, 'depart-deposit-and-part-payment-for-woocommerce' ), number_format_i18n( $number ) );
			printf( '<div class="updated"><p>' . esc_html( $message ) . '</p></div>' );
		}
	}

	/**
	 * See if we should render search filters or not.
	 */
	public function restrict_manage_posts() {
		global $typenow;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) ) {
			$this->render_filters();
		}
	}

	/**
	 * Render any custom filters and search inputs for the list table.
	 */
	protected function render_filters() {
        $user_string = '';
        $user_id     = '';
        
        if ( ! empty( $_GET['_customer_user'] ) ) {
            $user_id = absint( $_GET['_customer_user'] );
            $user    = get_user_by( 'id', $user_id );

            $user_string = sprintf(
            /* translators: 1: user display name 2: user ID 3: user email */
                esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
                $user->display_name,
                absint( $user->ID ),
                $user->user_email
            );
        }

        // Note: use of htmlspecialchars (below) is to prevent XSS when rendered by selectWoo.
        ?>
        <select class="wc-customer-search" name="_customer_user" data-placeholder="<?php esc_attr_e( 'Filter by registered customer', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>" data-allow_clear="true">
            <option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo wp_kses_post( htmlspecialchars(  $user_string ) ); ?></option>
        </select>
        <?php
	}

	/**
	 * Handle any filters.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function request_query( $query_vars ) {
		global $typenow;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) ) {
			return $this->query_filters( $query_vars );
		}

		return $query_vars;
	}

	/**
	 * Handle any custom filters.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	protected function query_filters( $query_vars ) {
		global $wp_post_statuses;

		// Filter the orders by the posted customer.
		if ( ! empty( $_GET['_customer_user'] ) ) {
			$query_vars['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_customer_user',
					'value'   => (int) $_GET['_customer_user'],
					'compare' => '=',
				),
			);
		}

		// Sorting.
		if ( isset( $query_vars['orderby'] ) ) {
			if ( 'order_total' === $query_vars['orderby'] ) {
				$query_vars = array_merge( $query_vars, array(
					'meta_key' => '_order_total', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'  => 'meta_value_num',
				) );
			}
            if ( 'order_due_date' === $query_vars['orderby'] ) {
                $query_vars = array_merge( $query_vars, array(
                    'meta_key' => '_depart_partial_payment_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'orderby'  => 'meta_value',
                ) );
            }
		}

		// Status.
		if ( empty( $query_vars['post_status'] ) ) {
			$post_statuses = wc_get_order_statuses();

			foreach ( $post_statuses as $status => $value ) {
				if ( isset( $wp_post_statuses[ $status ] ) && false === $wp_post_statuses[ $status ]->show_in_admin_all_list ) {
					unset( $post_statuses[ $status ] );
				}
			}

			$query_vars['post_status'] = array_keys( $post_statuses );
		}
		return $query_vars;
	}

	/**
	 * Change the label when searching orders.
	 *
	 * @param mixed $query Current search query.
	 * @return string
	 */
	public function search_label( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || $this->list_table_type !== $typenow || ! get_query_var( 'suborder_search' ) || ! isset( $_GET['s'] ) ) {
			return $query;
		}

		return wc_clean( wp_unslash( $_GET['s'] ) );
	}

	/**
	 * Query vars for custom searches.
	 *
	 * @param mixed $public_query_vars Array of query vars.
	 * @return array
	 */
	public function add_custom_query_var( $public_query_vars ) {
        if ( isset( $_GET['orderby'] ) && 'meta_value' === $_GET['orderby'] ) {
            $_GET['meta_key']     = '_depart_partial_payment_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            $_GET['post_status']  = array( 'wc-pending', 'wc-processing',  'wc-on-hold');
        }
		$public_query_vars[] = 'suborder_search';
		return $public_query_vars;
	}

	/**
	 * Search custom fields as well as content.
	 *
	 * @param WP_Query $wp Query object.
	 */
	public function search_custom_fields( $wp ) {
        global $pagenow;

        if ( 'edit.php' !== $pagenow || $this->list_table_type !== $wp->query_vars['post_type'] ) {
            return;
        }

        $post_ids = isset( $_GET['s'] ) && ! empty( $wp->query_vars['s'] ) ? wc_order_search( wc_clean( wp_unslash( $_GET['s'] ) ) ) : array();

        if ( ! empty( $post_ids ) ) {
            // Remove "s" - we don't want to search order name.
            unset( $wp->query_vars['s'] );

            // so we know we're doing this.
            $wp->query_vars['suborder_search'] = true;

            // Search by found posts.
            $wp->query_vars['post__in'] = array_merge( $post_ids, array( 0 ) );
        }

        if ( isset( $_GET['order_date_type'] ) && isset( $_GET['m'] ) ) {
            $date_type  = wc_clean( wp_unslash( $_GET['order_date_type'] ) );
            $date_query = wc_clean( wp_unslash( $_GET['m'] ) );
            // date_paid and date_completed are stored in postmeta, so we need to do a meta query.
            if ( 'date_paid' === $date_type || 'date_completed' === $date_type ) {
                $date_start = \DateTime::createFromFormat( 'Ymd H:i:s', "$date_query 00:00:00" );
                $date_end   = \DateTime::createFromFormat( 'Ymd H:i:s', "$date_query 23:59:59" );

                unset( $wp->query_vars['m'] );

                if ( $date_start && $date_end ) {
                    $wp->query_vars['meta_key']     = "_$date_type"; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    $wp->query_vars['meta_value']   = array( strval( $date_start->getTimestamp() ), strval( $date_end->getTimestamp() ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    $wp->query_vars['meta_compare'] = 'BETWEEN';
                }
            }
        }
	}
}

