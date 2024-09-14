<?php

namespace VicoDIn\Admin;

use VicoDIn\Inc\Data;

defined( 'ABSPATH' ) || exit;

class Deposit_Rule {
    
    protected static $instance = null;
    private $data_store;
    
    public function __construct() {
        $this->data_store = Data::load();
        add_action( 'wp_ajax_depart_get_rule_list', array( $this, 'get_home' ) );
        add_action( 'wp_ajax_depart_get_rule', array( $this, 'get_rule' ) );
        add_action( 'wp_ajax_depart_save_rule', array( $this, 'save_rule' ) );
        add_action( 'wp_ajax_depart_delete_rule', array( $this, 'delete_rule' ) );
        add_action( 'wp_ajax_depart_update_rule', array( $this, 'update_rule' ) );
        add_action( 'wp_ajax_depart_sort_rule_list', array( $this, 'sort_rule_list' ) );
        add_action( 'wp_ajax_depart_search_products', array( $this, 'search_products' ) );
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function get_rule_template() {
        return [
            'rule_id'             => '',
            'rule_name'           => '',
            'rule_active'         => true,
            'rule_categories_inc' => array(),
            'rule_categories_exc' => array(),
            'rule_users_inc'      => array(),
            'rule_users_exc'      => array(),
            'rule_products_inc'   => array(),
            'rule_products_exc'   => array(),
            'rule_price_range'    => array(
                'price_start' => '',
                'price_end'   => '',
            ),
            'payment_plans'       => array(),
        ];
    }
    
    public static function page_callback() {
        ?>
        <div class="vico-deposit wrapper"></div>
        <?php
    }
    
    public function get_rule() {
        if ( ! ( isset( $_GET['nonce'], $_GET['rule_id'] )
                 && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        $currency_unit = get_woocommerce_currency_symbol();
        $categories    = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );
        $tags          = get_terms( [
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ] );
        $user_roles    = get_editable_roles();
        $plans         = $this->data_store->get_plans();
        $rules         = $this->data_store->get_rules();
        $rule          = [];
        $rule_id       = false;
        if ( isset( $_GET['rule_id'] ) ) {
            $rule_id = sanitize_text_field( wp_unslash( $_GET['rule_id'] ) );
        }
        if ( $rule_id ) {
            if ( isset ( $rules[ $rule_id ] ) ) {
                $rule = $rules[ $rule_id ];
            } else {
                $message = __( 'Rule not found!', 'depart-deposit-and-part-payment-for-woocommerce' );
                echo wp_kses_post( '<h2>' . esc_html( $message ) . '</h2>' );
                wp_die();
            }
        } else {
            $rule = $this->get_rule_template();
        }
        
        ?>
        <div class="depart-action-bar">
            <a href="#/" class="vi-ui button icon" id="depart-home-rule">
                <i class="angle double left icon"></i>
            </a>
            <?php
            if ( empty( $rule['rule_id'] ) ) {
                ?>
                <h2><?php esc_attr_e( 'Add new rule', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                <?php
            } else {
                ?>
                <h2><?php esc_attr_e( 'Edit rule', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                <?php
            }
            ?>
        </div>
        <div class="vi-ui rule-wrapper segment">
            <table class="vi-ui form form-table">
                <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['rule_id'] ) ?>">
                <tr>
                    <th>
                        <label for="rule_name"><?php esc_html_e( 'Rule name', 'depart-deposit-and-part-payment-for-woocommerce' ); ?><span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="rule_name" id="rule_name" value="<?php echo esc_attr( $rule['rule_name'] ) ?>">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_active"><?php esc_html_e( 'Active rule', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    
                    </th>
                    <td>
                        <div class="vi-ui toggle checkbox">
                            <input type="checkbox" tabindex="0" class="hidden" name="rule_active" id="rule_active" <?php echo esc_attr( $rule['rule_active'] ? 'checked' : '' ) ?> >
                            <label for="rule_active"></label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for=""><?php esc_html_e( 'Price range', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <div class="two fields depart-price-range">
                            <div class="field">
                                <div class="vi-ui labeled input">
                                    <div class="vi-ui label">
                                        <?php esc_html_e( 'Min ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                        <b>(<?php echo esc_html( $currency_unit ) ?>)</b>
                                    </div>
                                    <input
                                        type="number"
                                        placeholder="0"
                                        name="price_start"
                                        value="<?php echo esc_attr( $rule['rule_price_range']['price_start'] ) ?>">
                                </div>
                            </div>
                            <div class="field">
                                <div class="vi-ui labeled input">
                                    <div class="vi-ui label">
                                        <?php esc_html_e( 'Max ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                        <b>(<?php echo esc_html( $currency_unit ) ?>)</b>
                                    </div>
                                    <input type="number"
                                           name="price_end"
                                           placeholder="∞"
                                           value="<?php echo esc_attr( $rule['rule_price_range']['price_end'] ) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="vi-ui message info">
                            <ul class="list">
                                <li class="item"><?php esc_html_e('Products have prices between Min and Max will be applied rule', 'depart-deposit-and-part-payment-for-woocommerce'); ?></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="payment_plans"><?php esc_html_e( 'Payment plans', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    
                    </th>
                    <td>
                        <select multiple="" name="payment_plans"
                                id="payment_plans" class="vi-ui dropdown search fluid">
                            <option value=""><?php esc_html_e( 'Select payment plans', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_categories_inc"><?php esc_html_e( 'Include categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <select multiple name="rule_categories_inc"
                                id="rule_categories_inc"
                                class="vi-ui dropdown search fluid depart_taxonomy"
                                data-text="<?php esc_attr_e( 'Categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value="">  <?php esc_html_e( 'Any categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?> </option>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_categories_exc"><?php esc_html_e( 'Exclude categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    
                    </th>
                    <td>
                        <select multiple name="rule_categories_exc"
                                id="rule_categories_exc"
                                class="vi-ui dropdown search fluid depart_taxonomy"
                                data-text="<?php esc_attr_e( 'Categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value=""> <?php esc_html_e( 'No categories', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_users_inc"><?php esc_html_e( 'Include user roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <select multiple name="rule_users_inc" id="rule_users_inc"
                                class="vi-ui dropdown search fluid depart_taxonomy-one"
                                data-text="<?php esc_attr_e( 'User roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value=""><?php esc_html_e( 'Any user roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_users_exc"><?php esc_html_e( 'Exclude user roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <select multiple name="rule_users_exc" id="rule_users_exc"
                                class="vi-ui dropdown search fluid depart_taxonomy-one"
                                data-text="<?php esc_attr_e( 'User roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value=""><?php esc_html_e( 'No user roles', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></option>
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
                    </td>
                </tr>
                <?php
                $product_inc = array();
                $product_exc = array();
                $args        = array(
                    'post_type'   => 'product',
                    'post_status' => 'publish',
                    'numberposts' => - 1,
                );
                if ( ! empty( $rule['rule_products_inc'] ) ) {
                    $args['include'] = $rule['rule_products_inc'];
                    $product_inc     = wc_get_products( $args );
                } elseif ( ! empty( $rule['rule_products_exc'] ) ) {
                    $args['include'] = $rule['rule_products_exc'];
                    $product_exc     = wc_get_products( $args );
                }
                
                ?>
                <tr>
                    <th>
                        <label for="rule_products_inc"><?php esc_html_e( 'Include products', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    
                    </th>
                    <td>
                        <select multiple name="rule_products_inc"
                                id="rule_products_inc"
                                class="vi-ui search depart_taxonomy-one fluid"
                                data-text="<?php esc_attr_e( 'Products', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value=""><?php esc_html_e( 'Enter 3 characters to search', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></option>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rule_products_exc"><?php esc_html_e( 'Exclude products', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <select multiple name="rule_products_exc"
                                id="rule_products_exc"
                                class="vi-ui search depart_taxonomy-one fluid"
                                data-text="<?php esc_attr_e( 'Products', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                            <option value=""><?php esc_html_e( 'Enter 3 characters to search', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></option>
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
                    </td>
                </tr>
            </table>
        </div>
        <button class="vi-ui button labeled icon primary mt-15" id="depart-save-rule">
            <i class="save icon outline"></i> <?php esc_attr_e( 'Save', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
        </button>
        <?php
        wp_die();
    }
    
    public function save_rule() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Save rule failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        $data        = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $data        = json_decode( $data, true );
        $data        = array_merge( $this->get_rule_template(), $data );
        $rule_id     = $data['rule_id'] ?? null;
        $exist_rules = $this->data_store->get_rules();
        
        if ( $rule_id ) {
            if ( isset( $exist_rules[ $rule_id ] ) ) {
                $exist_rules[ $rule_id ] = $data;
            }
        } else {
            $rule_id         = time();
            $data['rule_id'] = $rule_id;
            $exist_rules     = array( $rule_id => $data ) + $exist_rules;
        }
        
        $this->data_store->update_rules( $exist_rules );
        
        wp_send_json_success( array(
            'message' => esc_html__( 'Rule saved!', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'rule_id' => $rule_id,
        ) );
    }
    
    public function delete_rule() {
        if ( ! ( isset( $_POST['nonce'], $_POST['rule_id'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Deletion failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        $rule_id     = sanitize_text_field( wp_unslash( $_POST['rule_id'] ) );
        $exist_rules = $this->data_store->get_rules();
        if ( isset( $exist_rules[ $rule_id ] ) && count( $exist_rules ) > 1 ) {
            unset( $exist_rules[ $rule_id ] );
        } else {
            wp_send_json_error( esc_html__( 'Deletion failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        $this->data_store->update_rules( $exist_rules );
        wp_send_json_success( esc_html__( 'Deleted successfully!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        self::get_home();
    }
    
    public function get_home() {
        if ( ! ( isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'depart_nonce' ) ) ) {
            wp_die();
        }
        $rules = $this->data_store->get_rules();
        ?>
        <h2><?php esc_attr_e( 'Manage Deposit Rules', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
        <a href="#/new-rule" class="vi-ui button primary" id="depart-new-plan">
            <i class="plus square outline icon"></i>
            <?php esc_attr_e( 'Add new rule', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
        </a>
        <?php if ( ! empty( $rules ) ) { ?>
        <div class="vi-ui info message">
            <div class="header">
                <?php esc_html_e('Deposit rule notice', 'depart-deposit-and-part-payment-for-woocommerce'); ?>
            </div>
            <ul class="list">
                <li><?php esc_html_e( 'Deposit rules will have priority order from top to bottom.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
                <li><?php esc_html_e( 'Product will be applied to the first rule that satisfies the conditions.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
                <li><?php esc_html_e( 'Drag and drop the rule to change the priority order.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
            </ul>
        </div>
            <table class="vi-ui striped table vwcdp-table">
                <thead>
                <tr>
                    <th ><?php esc_attr_e( 'No.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Rule Name', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Apply for', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Plans', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Action', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                </tr>
                </thead>
                <tbody id="depart-rule-sortable">
                <?php
                $number = 0;
                foreach ( $rules as $rule ) {
                    ?>
                    <tr data-rule_id="<?php echo esc_attr( $rule['rule_id'] ); ?>">
                        <td>
                            <?php echo esc_html( str_pad( ++$number, 2, '0', STR_PAD_LEFT ) ); ?>
                        </td>
                        <td class="three wide"><?php echo esc_html( $rule['rule_name'] ) ?></td>
                        <td class="three wide"><?php echo esc_html( $rule['rule_apply_for'] ) ?></td>
                        <td class="three wide"><?php echo esc_html( $rule['rule_plan_names'] ) ?></td>
                        <td class="three wide">
                            <div class="vi-ui toggle checkbox">
                                <input type="checkbox"
                                       name="rule_active"
                                       class="depart-rule-enable"
                                       data-id="<?php echo esc_attr( $rule['rule_id'] ) ?>"
                                    <?php echo esc_attr( $rule['rule_active'] ? 'checked' : '' ) ?>>
                                <label></label>
                            </div>
                        </td>
                        <td class="three wide">
                            <div class="depart-row-actions">
                                <button class="vi-ui circular red icon button basic depart-delete-rule mr-1 ml-1"
                                        data-id="<?php echo esc_attr( $rule['rule_id'] ) ?>">
                                    <i class="trash trash alternate outline icon"></i>
                                </button>
                                <a href="#/rule/<?php echo esc_attr( $rule['rule_id'] ) ?>"
                                   class="vi-ui circular primary icon button depart-edit-rule">
                                    <i class="edit outline icon"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        <?php } else {
            $text = __( 'There are no rules saved. Please add new rules!', 'depart-deposit-and-part-payment-for-woocommerce' );
            echo wp_kses_post( '<h2>' . esc_html( $text ) . '</h2>' );
        } ?>
        <?php
        
        wp_die();
    }
    
    public function update_rule() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
                 && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        $exist_rules = $this->data_store->get_rules();
        $data        = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $data        = json_decode( $data, true );
        
        $rule = $exist_rules[ $data['rule_id'] ];
        
        if ( isset( $rule ) ) {
            $rule['rule_active']             = $data['rule_active'];
            $exist_rules[ $data['rule_id'] ] = $rule;
        }
        
        $this->data_store->update_rules( $exist_rules );
        
        wp_send_json_success();
        
        wp_die();
    }
    
    public function sort_rule_list() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
                 && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $data = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $list = json_decode( $data );
        if ( ! is_array( $list ) ) {
            return;
        }
        
        $exist_rules = $this->data_store->get_rules();
        $list        = array_map( 'absint', $list );
        
        uasort( $exist_rules, function( $a, $b ) use ( $list ) {
            $pos_a = array_search( $a['rule_id'], $list );
            $pos_b = array_search( $b['rule_id'], $list );
            
            if ( false === $pos_a && false === $pos_b ) {
                return 0;
            } elseif ( false === $pos_a ) {
                return 1;
            } elseif ( false === $pos_b ) {
                return - 1;
            }
            
            return $pos_a - $pos_b;
        } );
        
        $this->data_store->update_rules( $exist_rules );
        
        wp_send_json_success();
        
    }
    
    public function search_products() {
        if ( ! ( isset( $_POST['nonce'], $_POST['product_name'] )
                 && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        $search_key = sanitize_text_field( wp_unslash( $_POST['product_name'] ) );
        
        $products = wc_get_products( [
            'status' => 'publish',
            'limit'  => - 1,
            's'      => $search_key,
        ] );
        $data     = array();
        foreach ( $products as $product ) {
            $data[] = array(
                'id'   => $product->get_id(),
                'name' => $product->get_name(),
            );
        }
        
        wp_send_json( array( 'data' => $data ) );
        
    }
    
}
