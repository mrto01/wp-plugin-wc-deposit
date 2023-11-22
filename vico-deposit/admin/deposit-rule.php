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
		$currency_unit = get_woocommerce_currency_symbol();
		$categories    = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false
		] );
		$tags          = get_terms( [
			'taxonomy'   => 'product_tag',
			'hide_empty' => false
		] );
		$products      = wc_get_products( [
			'status' => 'publish',
			'limit'  => - 1
		] );
		$user_roles    = get_editable_roles();
		$plans         = get_option( 'vicodin_payment_plan' );

		$rules   = get_option( 'vicodin_deposit_rule' );
		$rule    = [];
		$rule_id = $_GET['rule-id'] ?? null;
		if ( $rule_id ) {
			if ( isset ( $rules[ $rule_id ] ) ) {
				$rule = $rules[ $rule_id ];
			} else {
				wp_die( 'Rule not found!', 'Error',
					array( 'response' => 404 ) );
			}
		} else {
			$rule = [
				'rule-id'             => '',
				'rule-name'           => '',
				'rule-active'         => true,
				'rule-categories-inc' => array( '' ),
				'rule-categories-exc' => array( '' ),
				'rule-tags-inc'       => array( '' ),
				'rule-tags-exc'       => array( '' ),
				'rule-users-inc'      => array( '' ),
				'rule-users-exc'      => array( '' ),
				'rule-products-inc'   => array( '' ),
				'rule-products-exc'   => array( '' ),
				'rule-price-range'    => array(
					'price-start' => 0,
					'price-end'   => 0,
				),
				'payment-plans'       => array()
			];
		}


		?>
        <div class="vicodin-action-bar">
            <a href="#/" class="vi-ui button icon" id="vicodin-home-rule">
                <i class="angle double left icon"></i>
            </a>
			<?php
			if ( empty( $rule['rule-name'] ) ) {
				?>
                <h2><?php esc_attr_e( 'Add new rule',
						'vico-deposit-and-installment' ); ?></h2>
				<?php
			} else {
				?>
                <h2><?php esc_attr_e( 'Edit rule',
						'vico-deposit-and-installment' ); ?></h2>
				<?php
			}
			?>
            <button class="vi-ui button labeled icon primary"
                    id="vicodin-save-rule">
                <i class="save icon"></i> <?php esc_attr_e( 'Save',
					'vico-deposit-and-installment' ); ?>
            </button>
        </div>
        <form action="" class="vi-ui form segments">
            <input type="hidden" name="rule-id"
                   value="<?php echo esc_attr( $rule['rule-id'] ) ?>">
            <div class="field">
                <label for="rule-name"><?php esc_html_e( 'Rule name',
						'vico-deposit-and-installment' ); ?></label>
                <input type="text"
                       name="rule-name"
                       id="rule-name"
                       value="<?php echo esc_attr( $rule['rule-name'] ) ?>"
                >
            </div>
            <div class="field four wide">
                <label for="rule-active"><?php esc_html_e( 'Active plan',
						'vico-deposit-and-installment' ); ?></label>
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" tabindex="0" class="hidden"
                           name="rule-active"
                           id="rule-active"
						<?php echo $rule['rule-active'] ? 'checked' : '' ?>
                    >
                    <label for="rule-active"></label>
                </div>
            </div>
            <div class="two fields">
                <div class="field">
                    <label for="rule-categories-inc"><?php esc_html_e( 'Include categories',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-categories-inc"
                            id="rule-categories-inc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Categories',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">None</option>
						<?php
						if ( $categories ) {
							foreach ( $categories as $category ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $category->term_id ) ?>"
									<?php echo in_array( $category->term_id,
										$rule['rule-categories-inc'] )
										? 'selected' : '' ?>>
									<?php echo esc_html( $category->name ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule-categories-exc"><?php esc_html_e( 'Exclude categories',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-categories-exc"
                            id="rule-categories-exc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Categories',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $categories ) {
							foreach ( $categories as $category ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $category->term_id ) ?>"
									<?php echo in_array( $category->term_id,
										$rule['rule-categories-exc'] )
										? 'selected' : '' ?>>
									<?php echo esc_html( $category->name ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
            </div>
            <div class="two fields">
                <div class="field">
                    <label for="rule-tags-inc"><?php esc_html_e( 'Include tags',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-tags-inc" id="rule-tags-inc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Tags',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $tags ) {
							foreach ( $tags as $tag ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $tag->term_id ) ?>"
									<?php echo in_array( $tag->term_id,
										$rule['rule-tags-inc'] ) ? 'selected'
										: '' ?>>
									<?php echo esc_html( $tag->name ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule-tags-exc"><?php esc_html_e( 'Exclude tags',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-tags-exc" id="rule-tags-exc"
                            class="vi-ui dropdown search vicodin-taxonomy"
                            data-text="<?php esc_attr_e( 'Tags',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $tags ) {
							foreach ( $tags as $tag ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $tag->term_id ) ?>"
									<?php echo in_array( $tag->term_id,
										$rule['rule-tags-exc'] ) ? 'selected'
										: '' ?>>
									<?php echo esc_html( $tag->name ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
            </div>
            <div class="two fields">
                <div class="field">
                    <label for="rule-users-inc"><?php esc_html_e( 'Include user roles',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-users-inc" id="rule-users-inc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'User roles',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $user_roles ) {
							foreach ( $user_roles as $slug => $user ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $slug ) ?>"
									<?php echo in_array( $slug,
										$rule['rule-users-inc'] ) ? 'selected'
										: '' ?>>
									<?php echo esc_html( $user['name'] ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule-users-exc"><?php esc_html_e( 'Exclude user roles',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-users-exc" id="rule-users-exc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'User roles',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $user_roles ) {
							foreach ( $user_roles as $slug => $user ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $slug ) ?>"
									<?php echo in_array( $slug,
										$rule['rule-users-exc'] ) ? 'selected'
										: '' ?>>
									<?php echo esc_html( $user['name'] ) ?>
                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>

            </div>
            <div class="two fields">
                <div class="field">
                    <label for="rule-products-inc"><?php esc_html_e( 'Include products',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-products-inc"
                            id="rule-products-inc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'Products',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $products ) {
							foreach ( $products as $product ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $product->get_id() ) ?>"
									<?php echo in_array( $product->get_id(),
										$rule['rule-products-inc'] )
										? 'selected' : '' ?>>
									<?php echo esc_html( $product->get_name() ) ?>

                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
                <div class="field">
                    <label for="rule-products-exc"><?php esc_html_e( 'Exclude products',
							'vico-deposit-and-installment' ); ?></label>
                    <select multiple name="rule-products-exc"
                            id="rule-products-exc"
                            class="vi-ui dropdown search vicodin-taxonomy-one"
                            data-text="<?php esc_attr_e( 'Products',
						        'vico-deposit-and-installment' ); ?>">
                        <option value="">none</option>
						<?php
						if ( $products ) {
							foreach ( $products as $product ) {
								?>
                                <option
                                        value="<?php echo esc_attr( $product->get_id() ) ?>"
									<?php echo in_array( $product->get_id(),
										$rule['rule-products-exc'] )
										? 'selected' : '' ?>>
									<?php echo esc_html( $product->get_name() ) ?>

                                </option>
								<?php
							}
						}
						?>
                    </select>
                </div>
            </div>
            <div class="fields">
                <div class="eight wide field ">
                    <label for=""><?php esc_html_e( 'Price range',
							'vico-deposit-and-installment' ); ?></label>
                    <table class="vi-ui table vicodin-price-range">
                        <tbody>
                        <tr>
                            <td><?php esc_html_e( 'From',
									'vico-deposit-and-installment' ); ?></td>
                            <td>
                                <div>
                                    <input type="number" name="price-start"
                                           value="<?php echo esc_attr( $rule['rule-price-range']['price-start'] ) ?>">
                                    <b><?php echo esc_html( $currency_unit ) ?></b>
                                </div>
                            </td>
                            <td><?php esc_html_e( 'To',
									'vico-deposit-and-installment' ); ?></td>
                            <td>
                                <div>
                                    <input type="number"
                                           name="price-end"
                                           value="<?php echo esc_attr( $rule['rule-price-range']['price-end'] ) ?>">
                                    <b><?php echo esc_html( $currency_unit ) ?></b>
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
            <div class="field">
                <label for="payment-plans"><?php esc_html_e( 'Payment plans',
						'vico-deposit-and-installment' ); ?></label>
                <select multiple="" name="payment-plans"
                        id="payment-plans" class="vi-ui dropdown search">
                    <option value=""><?php esc_html_e( 'Select payment plans',
							'vico-deposit-and-installment' ); ?></option>
					<?php
					if ( $plans ) {
						foreach ( $plans as $plan ) {
							?>
                            <option
                                    value="<?php echo esc_attr( $plan['plan-id'] ) ?>"
								<?php echo in_array( $plan['plan-id'],
									$rule['payment-plans'] ) ? 'selected'
									: '' ?>
                                    data-text="<?php esc_attr_e( $plan['plan-name'],
										'vico-deposit-and-installment' ); ?>"
                            >

								<?php echo esc_html( $plan['plan-name'] ) ?>
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
		$rule_id = $data['rule-id'] ?? null;

		$exist_rules = get_option( 'vicodin_deposit_rule', array() );

		if ( $rule_id ) {
			$exist_rules[ $rule_id ] = $data;
		} else {
			$rule_id = get_option( 'vicodin_id' ) ?? 3;
			$rule_id ++;
			$data['rule-id']          = $rule_id;
			$exist_rules  = array( $rule_id => $data ) + $exist_rules;
			update_option( 'vicodin_id', $rule_id );
		}

		update_option( 'vicodin_deposit_rule', $exist_rules );

		echo json_encode( $rule_id );

		wp_die();
	}

	public function delete_rule() {
		$existRules = get_option( 'vicodin_deposit_rule' );
		if ( $_POST['rule-id'] ) {
			unset( $existRules[ $_POST['rule-id'] ] );
			update_option( 'vicodin_deposit_rule', $existRules );
		}
		self::get_home();
	}

	public function get_home() {
		$rules = get_option( 'vicodin_deposit_rule' );
		?>
        <div class="vico-deposit wrapper">
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
					foreach ( $rules as $rule_id => $rule ) {
						if ( $rule['rule-id'] !== 'default' ) {
							?>
                            <tr data-rule_id="<?php esc_attr_e( $rule['rule-id'] ); ?>">
                                <td><i class="icon arrows alternate"></i></td>
                                <td><?php echo esc_html( $rule['rule-name'] ) ?></td>
                                <td><?php echo esc_html( $rule['rule-apply-for'] ) ?></td>
                                <td><?php echo esc_html( $rule['rule-plan-names'] ) ?></td>
                                <td>
                                    <div class="vi-ui toggle checkbox">
                                        <input type="checkbox"
                                               name="rule-active"
                                               class="vicodin-rule-enable"
                                               data-id="<?php echo esc_attr( $rule['rule-id'] ) ?>"
											<?php echo $rule['rule-active'] ? 'checked' : '' ?>>
                                        <label></label>
                                    </div>
                                </td>
                                <td class="left aligned">
                                    <a href="#/rule/<?php echo esc_attr( $rule['rule-id'] ) ?>"
                                       class="vi-ui circular primary icon button">
                                        <i class="edit icon"></i>
                                    </a>
                                    <button class="vi-ui circular red icon button vicodin-delete-rule"
                                            data-id="<?php echo $rule['rule-id'] ?>">
                                        <i class="trash icon"></i>
                                    </button>
                                </td>
                            </tr>
							<?php
						}
					}

				?>

                <tfoot>
				<?php
				$rule_default = $rules['default'];
				?>
                <tr>
                    <th></th>
                    <th><?php echo esc_html( $rule_default['rule-name'] ) ?></th>
                    <th><?php echo esc_html( $rule_default['rule-apply-for'] ) ?></th>
                    <th><?php echo esc_html( $rule_default['rule-plan-names'] ) ?></th>
                    <th>
                        <div class="vi-ui toggle checkbox">
                            <input type="checkbox" name="rule-active"
                                   class="vicodin-rule-enable"
								<?php echo $rule_default['rule-active']
									? 'checked' : '' ?>
                                   data-id="<?php echo esc_attr( $rule_default['rule-id'] ) ?>">
                            <label></label>
                        </div>
                    </th>
                    <th class="left aligned">
                        <a href="#/rule/<?php echo esc_attr( $rule_default['rule-id'] ); ?>"
                           class="vi-ui circular primary icon button">
                            <i class="edit icon"></i>
                        </a>
                    </th>
                </tr>
                </tfoot>
            </table>
            <?php }else {
                $text = __( 'Not found any rule in database', 'vico-deposit-and-installment' );
                echo '<h2>'.print_r( $text ,true).'</h2>';
            } ?>
        </div>
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
		$existRules = get_option( 'vicodin_deposit_rule' );
		$data       = sanitize_text_field( wp_unslash( $_POST['data'] ) );
		$data       = json_decode( $data, true );

		$rule = $existRules[ $data['rule-id'] ];

		if ( isset( $rule ) ) {
			$rule['rule-active']            = $data['rule-active'];
			$existRules[ $data['rule-id'] ] = $rule;
		}

		update_option( 'vicodin_deposit_rule', $existRules );

		echo json_encode( 'update rule success!' );

		wp_die();
	}

    public function sort_rule_list() {

        if ( !isset( $_POST['data'] ) ) {
            return;
        }

        $list = json_decode( $_POST['data'] );
        if ( !is_array( $list ) ) {
            return;
        }

	    $existRules = get_option( 'vicodin_deposit_rule',true );
	    $list       = array_map('absint', $list );
        $default = $existRules['default'];
        unset( $existRules['default'] );

	    uasort($existRules, function ($a, $b) use ($list) {
		    $positionA = array_search($a['rule-id'], $list);
		    $positionB = array_search($b['rule-id'], $list);

		    if ($positionA === false && $positionB === false) {
			    return 0;
		    } elseif ($positionA === false) {
			    return 1;
		    } elseif ($positionB === false) {
			    return -1;
		    }

		    return $positionA - $positionB;
	    });
        $existRules['default'] = $default;

        update_option( 'vicodin_deposit_rule', $existRules );

        echo 'sort rules success!';

        wp_die();
    }
}
