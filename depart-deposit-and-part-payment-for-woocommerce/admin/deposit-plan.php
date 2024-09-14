<?php

namespace VicoDIn\Admin;

use VicoDIn\Inc\Data;

defined( 'ABSPATH' ) || exit;

class Deposit_Plan {
    
    protected static $instance = null;
    
    public function __construct() {
        $this->data_store = Data::load();
        add_action( 'wp_ajax_depart_get_plan_list', [ $this, 'get_home' ] );
        add_action( 'wp_ajax_depart_get_plan', [ $this, 'get_plan' ] );
        add_action( 'wp_ajax_depart_save_plan', [ $this, 'save_plan' ] );
        add_action( 'wp_ajax_depart_delete_plan', [ $this, 'delete_plan' ] );
        add_action( 'wp_ajax_depart_update_plan', [ $this, 'update_plan' ] );
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
    
    public function get_plan_template() {
        return [
            'plan_id'          => '',
            'plan_name'        => '',
            'plan_active'      => true,
            'plan_description' => '',
            'deposit'          => 50,
            'deposit_fee'      => 0,
            'plan_schedule'    => [
                [
                    'partial'   => 50,
                    'after'     => 1,
                    'date_type' => 'month',
                    'fee'       => 0,
                ],
            ],
            'duration'         => '1 Month',
            'total'            => 100,
        ];
    }
    
    public static function show_multi_languages( $plan, $field ) {
        if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
            $languages = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );
            
            if ( count( $languages ) ) {
                foreach ( $languages as $key => $language ) {
                    if ( $language['active'] ) {
                        continue;
                    }
                    $option_value = isset( $plan[ $field . '_' . $key ] ) ? $plan[ $field . '_' . $key ] : '';
                    if ( ! $option_value ) {
                        $option_value = $plan[ $field ];
                    }
                    ?>
                    <h4><?php echo esc_html( $language['native_name'] ) ?></h4>
                    <input id="<?php echo esc_attr( $field . '_' . $key ) ?>"
                           type="text"
                           tabindex="0"
                           value="<?php echo esc_attr( $option_value ) ?>"
                           name="<?php echo esc_attr( $field . '_' . $key ) ?>"/>
                    <?php
                }
            }
        } elseif ( class_exists( 'Polylang' ) ) {
            $languages = pll_languages_list();
            foreach ( $languages as $language ) {
                $default_lang = pll_default_language( 'slug' );
                
                if ( $language == $default_lang ) {
                    continue;
                }
                $option_value = isset( $plan[ $field . '_' . $language ] ) ? $plan[ $field . '_' . $language ] : '';
                if ( ! $option_value ) {
                    $option_value = $plan[ $field ];
                }
                ?>
                <h4><?php echo esc_html( $language ) ?></h4>
                <input id="<?php echo esc_attr( $field . '_' . $language ) ?>"
                       type="text"
                       tabindex="0"
                       value="<?php echo esc_attr( $option_value ) ?>"
                       name="<?php echo esc_attr( $field . '_' . $language ) ?>"/>
                <?php
            }
        }
    }
    
    public function get_plan() {
        if ( ! ( isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'depart_nonce' ) ) ) {
            wp_die();
        }
        $payment_plans = $this->data_store->get_plans();
        $plan_id       = '';
        if ( isset( $_GET['plan_id'] ) ) {
            $plan_id = sanitize_text_field( wp_unslash( $_GET['plan_id'] ) );
        }
        $plan = [];
        if ( $plan_id ) {
            if ( isset( $payment_plans[ $plan_id ] ) ) {
                $plan = $payment_plans[ $plan_id ];
            } else {
                $message = __( 'Plan not found!', 'depart-deposit-and-part-payment-for-woocommerce' );
                echo wp_kses_post( '<h2>' . esc_html( $message ) . '</h2>' );
                wp_die();
            }
        } else {
            $plan = $this->get_plan_template();
        }
        
        ?>
        <div class="depart-action-bar">
            <a href="#/" class="vi-ui button icon" id="depart-home-plan">
                <i class="angle double left icon"></i>
            </a>
            <?php
            if ( empty( $plan['plan_name'] ) ) {
                ?>
                <h2><?php esc_attr_e( 'ADD NEW PLAN', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                <?php
            } else {
                ?>
                <h2><?php esc_attr_e( 'EDIT PLAN', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
                <?php
            }
            ?>
        </div>
        <form action="" class="vi-ui form segments">
            <input type="hidden" name="plan_id"
                   value="<?php echo esc_attr( $plan['plan_id'] ) ?>">
            <div class="field">
                <label for="plan_name"><?php esc_html_e( 'Plan name', 'depart-deposit-and-part-payment-for-woocommerce' ); ?><span class="required">*</span></label>
                <input type="text"
                       name="plan_name"
                       id="plan_name"
                       value="<?php echo esc_attr( $plan['plan_name'] ) ?>"
                >
                <?php self::show_multi_languages( $plan, 'plan_name' ); ?>
            </div>
            <div class="field four wide">
                <label for="plan_active"><?php esc_html_e( 'Active plan', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" tabindex="0" class="hidden"
                           name="plan_active"
                           id="plan_active"
                        <?php echo esc_attr( $plan['plan_active'] ? 'checked' : '' ); ?>
                    >
                    <label for="plan_active"></label>
                </div>
            </div>
            <div class="field">
                <label for="plan_description"><?php esc_html_e( 'Plan description', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                <textarea
                    name="plan_description"
                    id="plan_description" cols="30"
                    rows="5"><?php esc_html( $plan['plan_description'] ) ?></textarea>
            </div>
            <div class="field">
                <label> <?php esc_html_e( 'Plan schedule', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
            </div>
            <div class="field">
                <table class="vi-ui table segments depart-schedule">
                    <thead>
                    <tr>
                        <th>
                            <?php esc_html_e( 'Payment Amount (%)', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                            <span data-tooltip="<?php esc_html_e( 'The payment amount will be based on the product price', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                                <i class="question circle outline red icon"></i>
                            </span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Payment Interval Each Time', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                            <span data-tooltip="<?php esc_html_e( 'Payment due date for each payment.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                                <i class="question circle outline red icon"></i>
                            </span>
                        </th>
                        <th>
                            <?php esc_html_e( 'Fee (%)', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                            <span data-tooltip="<?php esc_html_e( 'Payment fee for each payment. Fees will be calculated based on the amount of each payment', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>">
                                <i class="question circle outline red icon"></i>
                            </span>
                        </th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <div class="partial-payment">
                                <input type="number" name="partial-payment"
                                       value="<?php echo esc_attr( $plan['deposit'] ) ?>">
                            </div>
                        </td>
                        <td>
                            <div class="partial-day">
                                <label for=""><?php esc_html_e( 'Immediately', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label
                            </div>
                        </td>
                        <td>
                            <div class="partial-fee">
                                <input type="number"
                                       name="partial-fee"
                                       value="<?php echo esc_attr( $plan['deposit_fee'] ) ?>">
                            </div>
                        </td>
                        <td></td>
                    </tr>
                    <?php
                    $date_types = [
                        'day'   => __( 'Day(s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
                        'month' => __( 'Month(s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
                        'year'  => __( 'Year(s)', 'depart-deposit-and-part-payment-for-woocommerce' ),
                    ];
                    foreach ( $plan['plan_schedule'] as $pos => $partial ) {
                        ?>
                        <tr>
                            <td>
                                <div class="partial-payment">
                                    <input type="number" name="partial-payment"
                                           value="<?php echo esc_attr( $partial['partial'] ) ?>">
                                </div>
                            </td>
                            <td>
                                <div class="partial-day vi-ui right labeled input">
                                    <input type="number" name="partial-day"
                                           value="<?php echo esc_attr( $partial['after'] ) ?>"
                                    >
                                    <select name="partial-date"
                                            class="vi-ui dropdown label"
                                    >
                                        <?php
                                        foreach ( $date_types as $key => $type ) {
                                            ?>
                                            <option value="<?php echo esc_attr( $key ) ?>"
                                                <?php echo esc_attr( ( $key == $partial['date_type'] ) ? 'selected' : '' ) ?> >
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
                                    <input type="number" name="partial-fee"
                                           value="<?php echo esc_attr( $partial['fee'] ) ?>">
                                </div>
                            </td>
                            <td>
                                <div class="vi-ui circular button red basic icon decrease-field <?php echo esc_attr( ( count( $plan['plan_schedule'] ) === 1 ) ? 'hidden' : '' ) ?>">
                                    <i class="trash alternate outline icon"></i>
                                </div>
                                <div class="vi-ui circular button primary icon increase-field <?php echo esc_attr( ( count( $plan['plan_schedule'] ) === ++ $pos ? '' : 'hidden' ) ) ?>">
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
                        <th><?php esc_html_e( 'Total: ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?><span
                                id="partial-total"><?php echo esc_html( $plan['total'] ); ?></span>
                            %
                        </th>
                        <th colspan="3"><?php esc_html_e( 'Duration: ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?><span
                                id="partial-duration"><?php echo esc_html( $plan['duration'] ) ?></span>
                        </th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </form>
        <button class="vi-ui button labeled icon primary" id="depart-save-plan">
            <i class="save outline icon"></i> <?php esc_attr_e( 'Save', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
        </button>
        <?php
        wp_die();
    }
    
    public function save_plan() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Save plan failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        $data    = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $data    = json_decode( $data, true );
        $data    = array_merge( $this->get_plan_template(), $data );
        $plan_id = $data['plan_id'] ?? null;
        
        $exist_plans = $this->data_store->get_plans();
        
        if ( $plan_id ) {
            $exist_plans[ $plan_id ] = $data;
        } else {
            $plan_id                 = time();
            $data['plan_id']         = $plan_id;
            $exist_plans[ $plan_id ] = $data;
        }
        
        $this->data_store->update_plans( $exist_plans );
        
        wp_send_json_success( array(
            'message' => esc_html__( 'Plan saved!', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'plan_id' => $plan_id,
        ) );
    }
    
    public function delete_plan() {
        if ( ! ( isset( $_POST['nonce'], $_POST['plan_id'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Deletion failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        
        $exist_plans = $this->data_store->get_plans();
        $plan_id     = sanitize_text_field( wp_unslash( $_POST['plan_id'] ) );
        if ( isset( $exist_plans[ $plan_id ] ) && count( $exist_plans ) > 1 ) {
            unset( $exist_plans[ $plan_id ] );
        } else {
            wp_send_json_error( esc_html__( 'Deletion failed!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
        $this->data_store->update_plans( $exist_plans );
        $rules = $this->data_store->get_rules();
        foreach ( $rules as $key => $rule ) {
            $index = array_search( $plan_id, $rule['payment_plans'] );
            
            if ( false !== $index ) {
                array_splice( $rule['payment_plans'], $index, 1 );
                $rules[ $key ]['payment_plans'] = $rule['payment_plans'];
                $rule_plan_names                = [];
                
                foreach ( $rule['payment_plans'] as $plan ) {
                    $rule_plan_names[] = $exist_plans[ $plan ]['plan_name'];
                }
                $rules[ $key ]['rule_plan_names'] = implode( ', ', $rule_plan_names );
            }
        }
        $this->data_store->update_rules( $rules );
        wp_send_json_success( esc_html__( 'Deleted successfully!', 'depart-deposit-and-part-payment-for-woocommerce' ) );
    }
    
    public function get_home() {
        if ( ! ( isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'depart_nonce' ) ) ) {
            wp_die();
        }
        ?>
        <h2><?php esc_attr_e( 'Manage Payment Plans', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></h2>
        <a href="#/plan-new" class="vi-ui button primary" id="depart-new-plan">
            <i class="plus square outline icon"></i>
            <?php esc_attr_e( 'Add new plan', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
        </a>
        <?php
        $payment_plans = $this->data_store->get_plans();
        if ( ! empty ( $payment_plans ) ) {
            ?>
            <table class="vi-ui striped table vwcdp-table">
                <thead>
                <tr class="grey">
                    <th><?php esc_attr_e( 'No.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="four wide"><?php esc_attr_e( 'Plan name', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="four wide"><?php esc_attr_e( 'Duration', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="four wide"><?php esc_attr_e( 'Status', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                    <th class="three wide"><?php esc_attr_e( 'Action', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $number = 1;
                foreach ( $payment_plans as $plan ) {
                    ?>
                    <tr>
                        <td><?php echo esc_html( str_pad( $number, 2, '0', STR_PAD_LEFT ) ); ?></td>
                        <td><?php echo esc_html( $plan['plan_name'] ); ?></td>
                        <td><?php echo esc_html( $plan['duration'] ); ?></td>
                        <td>
                            <div class="vi-ui toggle checkbox">
                                <input type="checkbox" name="plan_active"
                                       id="depart-enable" <?php echo esc_attr( $plan['plan_active'] ? 'checked' : '' ) ?>
                                       data-id="<?php echo esc_attr( $plan['plan_id'] ) ?>"
                                       class="depart-plan-enable">
                                <label></label>
                            </div>
                        </td>
                        <td class="center aligned">
                            <div class="depart-row-actions">
                                <button class="vi-ui circular red icon button basic depart-delete-plan mr-1 ml-1"
                                        data-id="<?php echo esc_attr( $plan['plan_id'] ) ?>">
                                    <i class="trash trash alternate outline icon"></i>
                                </button>
                                <a href="#/plan/<?php echo esc_attr( $plan['plan_id'] ) ?>"
                                   class="vi-ui circular primary icon button depart-edit-plan">
                                    <i class="edit outline icon"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                    $number ++;
                }
                ?>
                </tbody>
            </table>
        <?php } else {
            $text = __( 'There are no plans saved. Please add new plans!', 'depart-deposit-and-part-payment-for-woocommerce' );
            echo wp_kses_post( '<h2>' . esc_html( $text ) . '</h2>' );
        }
        wp_die();
    }
    
    public function update_plan() {
        if ( ! ( isset( $_POST['nonce'], $_POST['data'] )
                 && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        $exist_plans = $this->data_store->get_plans();
        $data        = sanitize_text_field( wp_unslash( $_POST['data'] ) );
        $data        = json_decode( $data, true );
        
        $plan = $exist_plans[ $data['plan_id'] ];
        
        if ( isset( $plan ) ) {
            $plan['plan_active']             = $data['plan_active'];
            $exist_plans[ $data['plan_id'] ] = $plan;
        }
        
        $this->data_store->update_plans( $exist_plans );
        
        wp_send_json_success();
        
        wp_die();
    }
    
}
