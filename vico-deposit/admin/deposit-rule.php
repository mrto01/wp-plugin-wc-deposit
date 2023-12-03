<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Deposit_Rule {
	protected static $instance = null;

	public function __construct() {
		add_action( 'wp_ajax_vicodin_get_rule_list',
			array( $this, 'get_home' ) );
		add_action( 'wp_ajax_vicodin_get_rule', array( $this, 'get_rule' ) );
		add_action( 'wp_ajax_vicodin_save_rule', array( $this, 'save_rule' ) );
		add_action( 'wp_ajax_vicodin_delete_rule',
			array( $this, 'delete_rule' ) );
		add_action( 'wp_ajax_vicodin_update_rule',
			array( $this, 'update_rule' ) );
        add_action( 'wp_ajax_vicodin_sort_rule_list', array( $this, 'sort_rule_list' ) );
        add_action( 'wp_ajax_vicodin_search_products', array( $this, 'search_products' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function page_callback() {
		?>
        <div class="vico-deposit wrapper"></div>
		<?php
	}

	public function get_rule() {
		if ( ! ( isset( $_GET['nonce'], $_GET['rule_id'] )
		         && wp_verify_nonce( sanitize_key( $_GET['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}

		$currency_unit = get_woocommerce_currency_symbol();
		$categories    = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false
		] );
		$tags          = get_terms( [
			'taxonomy'   => 'product_tag',
			'hide_empty' => false
		] );
		$user_roles    = get_editable_roles();
		$plans         = get_option( 'vicodin_payment_plan' );
		$rules   = get_option( 'vicodin_deposit_rule' );
		$rule    = [];
		$rule_id = false;
        if ( isset( $_GET['rule_id'] ) ) {
            $rule_id = sanitize_text_field( wp_unslash( $_GET['rule_id'] ) );
        }
		if ( $rule_id ) {
			if ( isset ( $rules[ $rule_id ] ) ) {
				$rule = $rules[ $rule_id ];
			} else {
				$message = __( 'Rule not found!', 'vico-deposit-and-installment');
				echo '<h2>' . esc_html( $message ) . '</h2>';
				wp_die();
			}
		} else {
			$rule = [
				'rule_id'             => '',
				'rule_name'           => '',
				'rule_active'         => true,
				'rule_categories_inc' => array( ),
				'rule_categories_exc' => array( ),
				'rule_tags_inc'       => array( ),
				'rule_tags_exc'       => array( ),
				'rule_users_inc'      => array( ),
				'rule_users_exc'      => array( ),
				'rule_products_inc'   => array( ),
				'rule_products_exc'   => array( ),
				'rule_price_range'    => array(
					'price_start' => 0,
					'price_end'   => 0,
				),
				'payment_plans'       => array()
			];
		}


		?>
        <div class="vicodin-action-bar">
            <a href="#/" class="vi-ui button icon" id="vicodin-home-rule">
                <i class="angle double left icon"></i>
            </a>
			<?php
			if ( empty( $rule['rule_name'] ) ) {
				?>
                <h2><?php esc_attr_e( 'Add new rule', 'vico-deposit-and-installment' ); ?></h2>
				<?php
			} else {
				?>
                <h2><?php esc_attr_e( 'Edit rule', 'vico-deposit-and-installment' ); ?></h2>
				<?php
			}
			?>
            <button class="vi-ui button labeled icon primary" id="vicodin-save-rule">
                <i class="save icon"></i> <?php esc_attr_e( 'Save', 'vico-deposit-and-installment' ); ?>
            </button>
        </div>
        <form action="" class="vi-ui form segments">
            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['rule_id'] ) ?>">
            <div class="field">
                <label for="rule_name"><?php esc_html_e( 'Rule name', 'vico-deposit-and-installment' ); ?></label>
                <input type="text"
                       name="rule_name"
                       id="rule_name"
                       value="<?php echo esc_attr( $rule['rule_name'] ) ?>"
                >
            </div>
            <div class="field four wide">
                <label for="rule_active"><?php esc_html_e( 'Active plan', 'vico-deposit-and-installment' ); ?></label>
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" tabindex="0" class="hidden"
                           name="rule_active"
                           id="rule_active"
						<?php echo $rule['rule_active'] ? 'checked' : '' ?>
                    >
                    <label for="rule_active"></label>
                </div>
            </div>
            <?php if ( 'default' != $rule['rule_id'] ) {
                ?>
                <div class="field">
                    <label for="rule_categories_inc"><?php esc_html_e( 'Include categories', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_categories_inc"
                            id="rule_categories_inc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Categories', 'vico-deposit-and-installment' ); ?>">
                        <option value="">  <?php esc_html_e( 'All', 'vico-deposit-and-installment'); ?> </option>
			            <?php
			            if ( $categories ) {
				            foreach ( $categories as $category ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $category->term_id ) ?>"
						            <?php echo in_array( $category->term_id, $rule['rule_categories_inc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $category->name ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_categories_exc"><?php esc_html_e( 'Exclude categories', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_categories_exc"
                            id="rule_categories_exc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Categories', 'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
			            <?php
			            if ( $categories ) {
				            foreach ( $categories as $category ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $category->term_id ) ?>"
						            <?php echo in_array( $category->term_id, $rule['rule_categories_exc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $category->name ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_tags_inc"><?php esc_html_e( 'Include tags', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_tags_inc" id="rule_tags_inc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Tags', 'vico-deposit-and-installment' ); ?>">
                        <option value=""><?php esc_html_e( 'All', 'vico-deposit-and-installment'); ?></option>
			            <?php
			            if ( $tags ) {
				            foreach ( $tags as $tag ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $tag->term_id ) ?>"
						            <?php echo in_array( $tag->term_id, $rule['rule_tags_inc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $tag->name ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_tags_exc"><?php esc_html_e( 'Exclude tags', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_tags_exc" id="rule_tags_exc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Tags', 'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
			            <?php
			            if ( $tags ) {
				            foreach ( $tags as $tag ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $tag->term_id ) ?>"
						            <?php echo in_array( $tag->term_id, $rule['rule_tags_exc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $tag->name ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_users_inc"><?php esc_html_e( 'Include user roles', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_users_inc" id="rule_users_inc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'User roles', 'vico-deposit-and-installment' ); ?>">
                        <option value=""><?php esc_html_e( 'All', 'vico-deposit-and-installment' ); ?></option>
			            <?php
			            if ( $user_roles ) {
				            foreach ( $user_roles as $slug => $user ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $slug ) ?>"
						            <?php echo in_array( $slug, $rule['rule_users_inc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $user['name'] ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_users_exc"><?php esc_html_e( 'Exclude user roles', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_users_exc" id="rule_users_exc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'User roles', 'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
			            <?php
			            if ( $user_roles ) {
				            foreach ( $user_roles as $slug => $user ) {
					            ?>
                                <option
                                        value="<?php echo esc_attr( $slug ) ?>"
						            <?php echo in_array( $slug, $rule['rule_users_exc'] ) ? 'selected' : '' ?>>
						            <?php echo esc_html( $user['name'] ) ?>
                                </option>
					            <?php
				            }
			            }
			            ?>
                    </select>
                </div>
	            <?php
	            $product_inc = array();
	            $product_exc = array();
	            $args = array(
		            'post_type'   => 'product',
		            'post_status' => 'publish',
		            'numberposts' => -1,
	            );
	            if ( ! empty( $rule['rule_products_inc'] ) ) {
		            $args['include'] = $rule['rule_products_inc'];
		            $product_inc = wc_get_products($args);

	            }elseif ( ! empty( $rule['rule_products_exc'] ) ) {
		            $args['include'] = $rule['rule_products_exc'];
		            $product_exc = wc_get_products($args);
	            }

	            ?>
                <div class="field">
                    <label for="rule_products_inc"><?php esc_html_e( 'Include products', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_products_inc"
                            id="rule_products_inc"
                            class="vi-ui search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'Products',
			                    'vico-deposit-and-installment' ); ?>">
                        <option value=""><?php esc_html_e('Enter 3 characters to search', 'vico-deposit-and-installment') ?></option>
			            <?php
			            foreach ( $product_inc as $product ) {
				            ?>
                            <option value="<?php echo esc_attr( $product->get_id() ) ?> " selected>
					            <?php echo esc_html( $product->get_name() ) ?>
                            </option>
				            <?php
			            }
			            ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule_products_exc"><?php esc_html_e( 'Exclude products', 'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule_products_exc"
                            id="rule_products_exc"
                            class="vi-ui search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'Products', 'vico-deposit-and-installment' ); ?>">
                        <option value=""><?php esc_html_e('Enter 3 characters to search', 'vico-deposit-and-installment') ?></option>
			            <?php
			            foreach ( $product_exc as $product ) {
				            ?>
                            <option value="<?php echo esc_attr( $product->get_id() ) ?> " selected>
					            <?php echo esc_html( $product->get_name() ) ?>
                            </option>
				            <?php
			            }
			            ?>
                    </select>
                </div>
                <div class="fields">
                    <div class="eight wide field ">
                        <label for=""><?php esc_html_e( 'Price range', 'vico-deposit-and-installment' ); ?></label>
                        <table class="vi-ui table vicodin-price-range">
                            <tbody>
                            <tr>
                                <td><?php esc_html_e( 'From', 'vico-deposit-and-installment' ); ?></td>
                                <td>
                                    <div>
                                        <b><?php echo esc_html( $currency_unit ) ?></b>
                                        <input type="number" name="price_start"
                                               value="<?php echo esc_attr( $rule['rule_price_range']['price_start'] ) ?>">
                                    </div>
                                </td>
                                <td><?php esc_html_e( 'To', 'vico-deposit-and-installment' ); ?></td>
                                <td>
                                    <div>
                                        <b><?php echo esc_html( $currency_unit ) ?></b>
                                        <input type="number"
                                               name="price_end"
                                               value="<?php echo esc_attr( $rule['rule_price_range']['price_end'] ) ?>">
                                    </div>
                                </td>
                                <!--                            <td>-->
                                <!--                                <div>-->
                                <!--                                    <div class="vi-ui circular button icon decrease-field hidden">-->
                                <!--                                        <i class="minus icon"></i>-->
                                <!--                                    </div>-->
                                <!--                                    <div class="vi-ui circular button icon increase-field">-->
                                <!--                                        <i class="plus icon"></i>-->
                                <!--                                    </div>-->
                                <!--                                </div>-->
                                <!--                            </td>-->
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php
            }?>
            <div class="field">
                <label for="payment_plans"><?php esc_html_e( 'Payment plans', 'vico-deposit-and-installment' ); ?></label>
                <select multiple="" name="payment_plans"
                        id="payment_plans" class="vi-ui dropdown search">
                    <option value=""><?php esc_html_e( 'Select payment plans', 'vico-deposit-and-installment' ); ?></option>
					<?php
					if ( $plans ) {
						foreach ( $plans as $plan ) {
							?>
                            <option
                                value="<?php echo esc_attr( $plan['plan_id'] ) ?>"
                                data-text="<?php echo esc_html( $plan['plan_name'] ); ?>"
								<?php echo in_array( $plan['plan_id'], $rule['payment_plans'] ) ? 'selected' : '' ?>
                            >

								<?php echo esc_html( $plan['plan_name'] ) ?>
                                (<?php echo esc_html( $plan['duration'] ) ?>)
                            </option>
							<?php
						}
					}
					?>
                </select>
            </div>
        </form>
		<?php
		wp_die();
	}

	public function save_rule() {
		if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}

		$data    = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data    = json_decode( $data, true );
		$rule_id = $data['rule_id'] ?? null;
		$exist_rules = get_option( 'vicodin_deposit_rule', array() );

		if ( $rule_id ) {
			if ( isset( $exist_rules[ $rule_id ] ) ) {
				$exist_rules[ $rule_id ] = $data;
			}
		} else {
			$rule_id = get_option( 'vicodin_id' ) ?? 3;
			$rule_id ++;
			$data['rule_id']          = $rule_id;
			$exist_rules  = array( $rule_id => $data ) + $exist_rules;
			update_option( 'vicodin_id', $rule_id );
		}

		update_option( 'vicodin_deposit_rule', $exist_rules );

		wp_send_json_success( $rule_id );

		wp_die();
	}

	public function delete_rule() {
		if ( ! ( isset( $_POST['nonce'], $_POST['rule_id'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}
        $rule_id = sanitize_text_field( wp_unslash( $_POST['rule_id'] ) );
		$exist_rules = get_option( 'vicodin_deposit_rule' );
        unset( $exist_rules[ $_POST['rule_id'] ] );
        update_option( 'vicodin_deposit_rule', $exist_rules );
		self::get_home();
	}

	public function get_home() {
		$rules = get_option( 'vicodin_deposit_rule' );
		?>
            <h2><?php esc_attr_e( 'Manage Deposit Rules',
					'vico-deposit-and-installment' ); ?></h2>
            <a href="#/new-rule"
               class="vi-ui button primary vicodin-new-rule"><?php esc_attr_e( 'New Rule',
					'vico-deposit-and-installment' ); ?></a>
        <?php if ( ! empty( $rules ) ) { ?>
            <table class="vi-ui table">
                <thead>
                <tr>
                    <th></th>
                    <th><?php esc_attr_e( 'Rule Name',
							'vico-deposit-and-installment' ); ?></th>
                    <th><?php esc_attr_e( 'Apply for',
							'vico-deposit-and-installment' ); ?></th>
                    <th><?php esc_attr_e( 'Plans',
							'vico-deposit-and-installment' ); ?></th>
                    <th colspan="2"></th>
                </tr>
                </thead>
                <tbody id="vicodin-rule-sortable">
				<?php
//                Delete plans stored in rules if not exist
					foreach ( $rules as $rule_id => $rule ) {
						if ( 'default' !== $rule['rule_id'] ) {
							?>
                            <tr data-rule_id="<?php echo esc_attr( $rule['rule_id'] ); ?>">
                                <td><i class="icon arrows alternate"></i></td>
                                <td><?php echo esc_html( $rule['rule_name'] ) ?></td>
                                <td><?php echo esc_html( $rule['rule_apply_for'] ) ?></td>
                                <td><?php echo esc_html( $rule['rule_plan_names'] ) ?></td>
                                <td>
                                    <div class="vi-ui toggle checkbox">
                                        <input type="checkbox"
                                               name="rule_active"
                                               class="vicodin-rule-enable"
                                               data-id="<?php echo esc_attr( $rule['rule_id'] ) ?>"
											<?php echo $rule['rule_active'] ? 'checked' : '' ?>>
                                        <label></label>
                                    </div>
                                </td>
                                <td class="left aligned">
                                    <a href="#/rule/<?php echo esc_attr( $rule['rule_id'] ) ?>"
                                       class="vi-ui circular primary icon button">
                                        <i class="edit icon"></i>
                                    </a>
                                    <button class="vi-ui circular red icon button vicodin-delete-rule"
                                            data-id="<?php echo esc_attr( $rule['rule_id'] ) ?>">
                                        <i class="trash icon"></i>
                                    </button>
                                </td>
                            </tr>
							<?php
						}
					}
//                    Save rules after update;
                update_option( 'vicodin_deposit_rule', $rules );
				?>

                <tfoot>
				<?php
				$rule_default = $rules['default'];
				?>
                <tr>
                    <th></th>
                    <th><?php echo esc_html( $rule_default['rule_name'] ) ?></th>
                    <th><?php echo esc_html( $rule_default['rule_apply_for'] ) ?></th>
                    <th><?php echo esc_html( $rule_default['rule_plan_names'] ) ?></th>
                    <th>
                        <div class="vi-ui toggle checkbox">
                            <input type="checkbox" name="rule_active"
                                   class="vicodin-rule-enable"
								<?php echo $rule_default['rule_active']
									? 'checked' : '' ?>
                                   data-id="<?php echo esc_attr( $rule_default['rule_id'] ) ?>">
                            <label></label>
                        </div>
                    </th>
                    <th class="left aligned">
                        <a href="#/rule/<?php echo esc_attr( $rule_default['rule_id'] ); ?>"
                           class="vi-ui circular primary icon button">
                            <i class="edit icon"></i>
                        </a>
                    </th>
                </tr>
                </tfoot>
            </table>
            <?php }else {
                $text = __( 'Not found any rule in database', 'vico-deposit-and-installment' );
                echo '<h2>'. esc_html( $text ).'</h2>';
            } ?>
		<?php

		wp_die();
	}

	public function update_rule() {
		if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
		         && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
				'vicodin_nonce' ) )
		) {
			return;
		}
		$exist_rules = get_option( 'vicodin_deposit_rule' );
		$data       = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data       = json_decode( $data, true );

		$rule = $exist_rules[ $data['rule_id'] ];

		if ( isset( $rule ) ) {
			$rule['rule_active']            = $data['rule_active'];
			$exist_rules[ $data['rule_id'] ] = $rule;
		}

		update_option( 'vicodin_deposit_rule', $exist_rules );

		wp_send_json_success();

		wp_die();
	}

    public function sort_rule_list() {
	    if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
	             && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
			    'vicodin_nonce' ) )
	    ) {
		    return;
	    }
        $data = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $list = json_decode( $data );
        if ( ! is_array( $list ) ) {
            return;
        }

	    $exist_rules = get_option( 'vicodin_deposit_rule',true );
	    $list       = array_map('absint', $list );
        $default = $exist_rules['default'];
        unset( $exist_rules['default'] );

	    uasort($exist_rules, function ($a, $b) use ($list) {
		    $pos_a = array_search( $a['rule_id'], $list );
		    $pos_b = array_search( $b['rule_id'], $list );

		    if ( false === $pos_a && false === $pos_b ) {
			    return 0;
		    } elseif ( false === $pos_a ) {
			    return 1;
		    } elseif ( false === $pos_b ) {
			    return -1;
		    }

		    return $pos_a - $pos_b;
	    });
        $exist_rules['default'] = $default;

        update_option( 'vicodin_deposit_rule', $exist_rules );

        wp_send_json_success();

        wp_die();
    }

    public function search_products() {
	    if ( ! ( isset( $_POST['nonce'], $_POST['product_name'] )
	             && wp_verify_nonce( sanitize_key( $_POST['nonce'] ),
			    'vicodin_nonce' ) )
	    ) {
		    return;
	    }

        $search_key = sanitize_text_field( wp_unslash( $_POST['product_name'] ) );

	    $products      = wc_get_products( [
		    'status' => 'publish',
		    'limit'  => - 1,
		    's'      => $search_key
	    ] );
        $data = array();
        foreach ( $products as $product ) {
            $data[] = array(
                'id'   => $product->get_id(),
                'name' => $product->get_name(),
            );
        }

        wp_send_json( array( 'data' => $data ) );

        wp_die();
    }
}
