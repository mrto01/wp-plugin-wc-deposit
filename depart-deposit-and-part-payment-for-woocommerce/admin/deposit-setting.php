<?php

namespace VicoDIn\Admin;

use VicoDIn\Inc\Data;
use VicoDIn\Inc\Deposit_Samples;
use VicoDIn\Inc\Payment\Auto_Payment;
use VicoDIn\Inc\Payment\Stripe_Gateway;
use VicoDIn\Inc\Schedule_Checker;

defined( 'ABSPATH' ) || exit;

class Deposit_Setting {
    
    static $settings;
    
    protected static $instance = null;
    
    public function __construct() {
        add_action( 'admin_init', [ $this, 'save_setting' ] );
        add_action( 'wp_ajax_depart_change_email_status', [ $this, 'change_email_status' ] );
        $this->setup();
    }
    
    public static function instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function setup() {
        global $depart_settings;
        $depart_settings = Data::load()->get_settings();
        self::$settings  = $depart_settings;
    }
    
    public function save_setting() {
        if ( ! ( isset( $_POST['_depart_nonce'], $_POST['depart_setting_params'] )
                 && wp_verify_nonce( sanitize_key( $_POST['_depart_nonce'] ), 'depart_settings' ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $data = wp_unslash( $_POST['depart_setting_params'] );
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = array_map( 'sanitize_text_field', $value );
            } else {
                $value = sanitize_text_field( $value );
            }
            $data[ $key ] = $value;
        }
        
        if ( isset( $data['check_key'] ) ) {
            unset( $data['check_key'] );
            delete_transient( 'update_plugins' );
//            delete_transient( 'villatheme_item_' ); Missing item_id
            delete_option( 'woocommerce-notification_messages' );
            do_action( 'villatheme_save_and_check_key_depart-deposit-and-part-payment-for-woocommerce', $data['key'] );
        }
        
        $args = [
            'enabled'                 => '0',
            'exclude_payment_methods' => [],
            'coupon'                  => 'deposit',
            'tax'                     => 'deposit',
            'fee'                     => 'deposit',
            'shipping'                => 'deposit',
            'shipping_tax'            => 'deposit',
            'free_partial_charge'     => '0',
            'show_plans'              => 'modal',
            'show_email_column'       => '0',
            'show_fees'               => '0',
            'auto_charge'             => '0',
            'force_deposit'           => '0',
            'deposit_on_checkout'     => '0',
            'rewrite_suborder_number' => '0',
            'reduce_stock_status'     => 'full',
            'paid_full_status'        => 'processing',
            'tax_display_shop'        => 'default',
            'tax_display_cart'        => 'default',
            
            /* Reminder */
            'time_check_orders'       => '06:00',
            'auto_send_mail'          => '1',
            'days_send_mail'          => 0,
            'days_interval'           => 0,
        
        ];
        global $depart_settings;
        self::$settings = array_merge( $args, $data );
        update_option( 'depart_deposit_setting', self::$settings );
        
        if ( $depart_settings['time_check_orders'] !== self::$settings['time_check_orders'] ) {
            Schedule_Checker::schedule_order_check_event( self::$settings['time_check_orders'] );
        }
        
        $depart_settings = self::$settings;
    }
    
    public static function get_depart_emails() {
        $wc_emails        = wc()->mailer()->get_emails();
        $sent_to_customer = __( 'Email sent to customers', 'depart-deposit-and-part-payment-for-woocommerce' );
        $viwec_enable     = false;
        
        if ( is_plugin_active( 'email-template-customizer-for-woo/email-template-customizer-for-woo.php' ) || is_plugin_active( 'woocommerce-email-template-customizer/woocommerce-email-template-customizer.php' ) ) {
            $viwec_enable = true;
        }
        
        $emails[ $sent_to_customer ] = array(
            'depart_email_deposit_payment_received' => array(
                'title'       => __( 'Deposit payment received', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'deposit-payment-received.png',
                'description' => __( 'After placing a deposit for the product, customers will receive a confirmation email notifying them that their order has been successfully placed with a deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_deposit_paid' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_deposit_payment_received' ),
                'enabled'     => $wc_emails['depart_email_deposit_payment_received']->enabled,
            ),
            'depart_email_partial_payment_received' => array(
                'title'       => __( 'Partial payment received', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'partial-payment-received.png',
                'description' => __( 'Customers will receive this email after they have made a partial payment to cover the remaining balance for the product they previously placed a deposit on', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_partial_paid' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_partial_payment_received' ),
                'enabled'     => $wc_emails['depart_email_partial_payment_received']->enabled,
            ),
            'depart_email_full_payment_received'    => array(
                'title'       => __( 'Full payment received', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'full-payment-received.png',
                'description' => __( 'Customers will receive this email after they have made a full payment for the product they previously placed a deposit on', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_full_payment' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_full_payment_received' ),
                'enabled'     => $wc_emails['depart_email_full_payment_received']->enabled,
            ),
            'depart_email_payment_reminder'         => array(
                'title'       => __( 'Payment reminder', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'payment-reminder.png',
                'description' => __( 'If customers forget or fail to pay the remaining balance after placing a deposit, a reminder email will be sent to notify of the situation', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_payment_reminder' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_payment_reminder' ),
                'enabled'     => $wc_emails['depart_email_payment_reminder']->enabled,
            ),
        );
        $sent_to_admin               = __( 'Email sent to administrators', 'depart-deposit-and-part-payment-for-woocommerce' );
        $emails[ $sent_to_admin ]    = array(
            'depart_email_partial_payment' => array(
                'title'       => __( 'Partial paid Admin received', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'partial-paid-admin-received.png',
                'description' => __( 'The administrator will be notified via email when the customer completes a partial payment for the remaining balance after making a deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_admin_partial_paid' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_partial_payment' ),
                'enabled'     => $wc_emails['depart_email_partial_payment']->enabled,
            ),
            'depart_email_full_payment'    => array(
                'title'       => __( 'Full payment Admin received', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'img'         => DEPART_CONST['img_url'] . 'full-payment-admin-received.png',
                'description' => __( 'The administrator will receive an email notification confirming that the customer has fully paid for the product they previously placed a deposit on', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'url'         => $viwec_enable ? admin_url( 'edit.php?post_type=viwec_template&viwec_template_filter=depart_admin_full_payment' ) : admin_url( 'admin.php?page=wc-settings&tab=email&section=depart_email_full_payment' ),
                'enabled'     => $wc_emails['depart_email_full_payment']->enabled,
            ),
        );
        
        return $emails;
    }
    
    public function change_email_status() {
        if ( ! ( isset( $_POST['nonce'], $_POST['email_class'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'depart_nonce' ) ) ) {
            return;
        }
        
        $email_class = sanitize_text_field( wp_unslash( $_POST['email_class'] ) );
        $wc_emails   = wc()->mailer()->get_emails();
        if ( ! empty ( $wc_emails[ $email_class ] ) ) {
            $enabled = $wc_emails[ $email_class ]->enabled === 'yes' ? 'no' : 'yes';
            $wc_emails[ $email_class ]->update_option( 'enabled', $enabled );
            $status_text = __( 'Enable', 'depart-deposit-and-part-payment-for-woocommerce' );
            if ( 'yes' === $enabled ) {
                $status_text = __( 'Disable', 'depart-deposit-and-part-payment-for-woocommerce' );
            }
            wp_send_json_success( [ 'status' => $status_text ] );
        } else {
            wp_send_json_error( __( 'Email type invalid', 'depart-deposit-and-part-payment-for-woocommerce' ) );
        }
    }
    
    public static function set_field( $field, $multi = false ) {
        if ( $field ) {
            if ( $multi ) {
                return esc_attr( 'depart_setting_params[' . $field . '][]' );
            } else {
                return esc_attr( 'depart_setting_params[' . $field . ']' );
            }
        } else {
            return '';
        }
    }
    
    public static function get_extra_options() {
        return [
            'coupon'       => [
                'title' => __( 'Coupon Handling.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'desc'  => __( 'How coupon will be handled.', 'depart-deposit-and-part-payment-for-woocommerce' ),
            ],
            'tax'          => [
                'title' => __( 'Tax Collection', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'desc'  => __( 'How tax will be charged.', 'depart-deposit-and-part-payment-for-woocommerce' ),
            ],
            'fee'          => [
                'title' => __( 'Fee Collection', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'desc'  => __( 'How fee will be charged.', 'depart-deposit-and-part-payment-for-woocommerce' ),
            ],
            'shipping'     => [
                'title' => __( 'Shipping Handling', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'desc'  => __( 'How shipping will be charged.', 'depart-deposit-and-part-payment-for-woocommerce' ),
            ],
            'shipping_tax' => [
                'title' => __( 'Shipping Tax Handling', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'desc'  => __( 'How shipping tax will be handled.', 'depart-deposit-and-part-payment-for-woocommerce' ),
            ],
        ];
    }
    
    public static function show_multi_languages( $option ) {
        if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
            $languages = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );
            if ( count( $languages ) ) {
                foreach ( $languages as $key => $language ) {
                    if ( $language['active'] ) {
                        continue;
                    }
                    $option_value = self::get_field( $option . '_' . $key );
                    if ( ! $option_value ) {
                        $option_value = self::get_field( $option );
                    }
                    ?>
                    <h4><?php echo esc_html( $language['native_name'] ) ?></h4>
                    <input id="<?php echo esc_attr( self::set_field( $option . '_' . $key ) ) ?>"
                           type="text"
                           tabindex="0"
                           value="<?php echo esc_attr( $option_value ) ?>"
                           name="<?php echo esc_attr( self::set_field( $option . '_' . $key ) ) ?>"/>
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
                $option_value = self::get_field( $option . '_' . $language );
                if ( ! $option_value ) {
                    $option_value = self::get_field( $option );
                }
                ?>
                <h4><?php echo esc_html( $language ) ?></h4>
                <input id="<?php echo esc_attr( self::set_field( $option . '_' . $language ) ) ?>"
                       type="text"
                       tabindex="0"
                       value="<?php echo esc_attr( $option_value ) ?>"
                       name="<?php echo esc_attr( self::set_field( $option . '_' . $language ) ) ?>"/>
                <?php
            }
        }
    }
    
    public static function get_field( $field, $default = '' ) {
        $params = self::$settings;
        if ( $params ) {
            if ( isset( $params[ $field ] ) ) {
                return $params[ $field ];
            } else {
                return $default;
            }
        } else {
            return $default;
        }
    }
    
    public static function get_extra_selects() {
        $extra_selects = [
            'deposit' => __( 'With Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'future'  => __( 'With Future Payment', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'split'   => __( 'Split', 'depart-deposit-and-part-payment-for-woocommerce' ),
        ];
        
        return $extra_selects;
    }
    
    public static function page_callback() {
        $payment_gateways      = WC()->payment_gateways()->get_available_payment_gateways();
        $extra_options         = self::get_extra_options();
        $extra_selects         = self::get_extra_selects();
        $reduce_stock_selects  = [
            'deposit' => __( 'Deposit Payment', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'full'    => __( 'Full payment', 'depart-deposit-and-part-payment-for-woocommerce' ),
        ];
        $full_paid_selects     = [
            'processing' => __( 'Processing', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'completed'  => __( 'Completed', 'depart-deposit-and-part-payment-for-woocommerce' ),
        ];
        $include_tax_selects   = [
            'default' => __( 'Same as Woocommerce', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'incl'    => __( 'Include', 'depart-deposit-and-part-payment-for-woocommerce' ),
            'excl'    => __( 'Exclude', 'depart-deposit-and-part-payment-for-woocommerce' ),
        ]
        ?>
        <div class="wrapper vico-deposit">
            <h2><?php esc_html_e( 'Deposit and part payment setting', 'depart-deposit-part-payment-for-woocommerce' ); ?></h2>
            <form method="post" action="" class="vi-ui form">
                <?php wp_nonce_field( 'depart_settings', '_depart_nonce' ) ?>
                <div class="vi-ui top attached tabular menu">
                    <div class="item active" data-tab="general">
                        <a href="#general"><?php esc_html_e( 'General', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                    <div class="item" data-tab="advanced">
                        <a href="#advanced"><?php esc_html_e( 'Advanced', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                    <div class="item" data-tab="reminder">
                        <a href="#reminder"><?php esc_html_e( 'Reminder', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                    <div class="item" data-tab="text_label">
                        <a href="#text_label"><?php esc_html_e( 'Text & Labels', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                    <div class="item" data-tab="email">
                        <a href="#email"><?php esc_html_e( 'Email', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                    <div class="item" data-tab="update">
                        <a href="#update"><?php esc_html_e( 'Update', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></a>
                    </div>
                </div>
                <div class="vi-ui bottom attached tab segment active" data-tab="general">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="depart-enable"><?php esc_html_e( 'Enable', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'enabled' ) ); ?>"
                                           id="depart-enable"
                                        <?php echo esc_attr( self::get_field( 'enabled' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e( 'Turn on Deposit feature.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-enable">
                                    <?php esc_html_e( 'Partial payments disorder', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'free_partial_charge' ) ) ?>"
                                           id="depart-enable"
                                        <?php echo esc_attr( self::get_field( 'free_partial_charge' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Allow customers to pay without a predetermined schedule.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-enable">
                                    <?php esc_html_e( 'Show reminder email column', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'show_email_column' ) ) ?>"
                                           id="depart-enable"
                                        <?php echo esc_attr( self::get_field( 'show_email_column' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Display Reminder email column in the Orders list of Woocommerce dashboard.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-enable">
                                    <?php esc_html_e( 'Show fees in frontend', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'show_fees' ) ) ?>"
                                           id="depart-enable"
                                        <?php echo esc_attr( self::get_field( 'show_fees' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Displays any fees alongside the deposit amount.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-auto-charge"><?php esc_html_e( 'Automatic Payments', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'auto_charge' ) ); ?>"
                                           id="depart-auto-charge"
                                        <?php echo esc_attr( self::get_field( 'auto_charge' ) ? 'checked' : '' ) ?>
                                        <?php echo esc_attr( ! Auto_Payment::is_switchable() ? 'disabled' : '' ) ?>
                                    >
                                    <label></label>
                                </div>
                                <?php if ( ! Auto_Payment::is_switchable() ) { ?>
                                    <div class="vi-ui red message">
                                        <?php esc_html_e( 'Missing required payment gateway(s) to enable this option.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                    </div>
                                <?php } ?>
                                <div class="vi-ui info message">
                                    <ul class="list">
                                        <li><?php esc_html_e( 'Allow customers choose auto payment for Deposit order.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
                                        <li><?php esc_html_e( 'Payment methods supported: Stripe credit card, Sepa (WooCommerce Stripe Gateway), Credit card (WooCommerce Square), PayPal( WooCommerce PayPal Payment ).', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="depart-payment"><?php esc_html_e( 'Exclude Payment method', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <select multiple class="vi-ui dropdown fluid"
                                        name="<?php echo esc_attr( self::set_field( 'exclude_payment_methods', true ) ) ?>"
                                        id="depart-payment">
                                    <option value=""><?php esc_html_e( 'Select payment methods', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></option>
                                    <?php
                                    foreach ( $payment_gateways as $payment_gateway ) {
                                        ?>
                                        <option value="<?php echo esc_attr( $payment_gateway->id ); ?>"
                                            <?php echo esc_attr( ( in_array( $payment_gateway->id, self::get_field( 'exclude_payment_methods' ) ) ) ? 'selected' : '' ) ?>><?php echo esc_html( $payment_gateway->title ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'The selected payment methods will not be available.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <?php foreach ( $extra_options as $id => $option ) { ?>
                            <tr>
                                <th>
                                    <label for="depart-<?php echo esc_attr( $id ) ?>"><?php echo esc_html( $option['title'] ); ?></label>
                                </th>
                                <td>
                                    <select class="vi-ui dropdown fluid"
                                            name="<?php echo esc_attr( self::set_field( $id ) ) ?>"
                                            id="depart-<?php echo esc_attr( $id ) ?>">
                                        <?php
                                        foreach (
                                            $extra_selects as $value => $text
                                        ) {
                                            ?>
                                            <option value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( self::get_field( $id ) == $value ? 'selected' : '' ) ?>><?php echo esc_html( $text ); ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php echo esc_html( $option['desc'] ); ?></p>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="advanced">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="depart-force-deposit">
                                    <?php esc_html_e( 'Force Deposit', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'force_deposit' ) ) ?>"
                                           id="depart-force-deposit"
                                        <?php echo esc_attr( self::get_field( 'force_deposit' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Products whose deposit rules are active must be purchased via deposit. This is overridden by deposit settings at product level.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-rewrite-suborder-number">
                                    <?php esc_html_e( 'Rewrite suborder number', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'rewrite_suborder_number' ) ) ?>"
                                           id="depart-rewrite-suborder-number"
                                        <?php echo esc_attr( self::get_field( 'rewrite_suborder_number' ) ? 'checked' : '' ) ?>>
                                    <label></label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e( 'Change suborder number according to the parent order (eg: 124 to 123-1 with 123 is the parent order).', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-reduce-stock"><?php esc_html_e( 'Reduce stocks on', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></label>
                            </th>
                            <td>
                                <select class="vi-ui dropdown fluid"
                                        name="<?php echo esc_attr( self::set_field( 'reduce_stock_status' ) ) ?>"
                                        id="depart-reduce-stock">
                                    <?php
                                    foreach ( $reduce_stock_selects as $value => $text ) {
                                        ?>
                                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( self::get_field( 'reduce_stock_status' ) == $value ? 'selected' : '' ) ?>><?php echo esc_html( $text ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Inventory decreases when orders are placed with a deposit if "Deposit Payment" is selected, or when orders are paid in full upfront with "Full payment"', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-paid-full-status"><?php esc_html_e( 'Order fully paid status', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></label>
                            </th>
                            <td>
                                <select class="vi-ui dropdown fluid"
                                        name="<?php echo esc_attr( self::set_field( 'paid_full_status' ) ) ?>"
                                        id="depart-paid-full-status">
                                    <?php
                                    foreach ( $full_paid_selects as $value => $text ) {
                                        ?>
                                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( self::get_field( 'paid_full_status' ) == $value ? 'selected' : '' ) ?>><?php echo esc_html( $text ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Change order to the selected status when it is fully paid.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-tax-in-product-detail">
                                    <?php esc_html_e( 'Show taxes in product detail', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <select class="vi-ui dropdown fluid"
                                        name="<?php echo esc_attr( self::set_field( 'tax_display_shop' ) ) ?>"
                                        id="depart-tax-in-product-detail">
                                    <?php
                                    foreach ( $include_tax_selects as $value => $text ) {
                                        ?>
                                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( self::get_field( 'tax_display_shop' ) == $value ? 'selected' : '' ) ?>><?php echo esc_html( $text ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Calculate taxes as part of deposits to display to customers on the product details.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-tax-in-cart-item">
                                    <?php esc_html_e( 'Show taxes in cart item details', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </label>
                            </th>
                            <td>
                                <select class="vi-ui dropdown fluid"
                                        name="<?php echo esc_attr( self::set_field( 'tax_display_cart' ) ) ?>"
                                        id="depart-tax-in-cart-item">
                                    <?php
                                    foreach ( $include_tax_selects as $value => $text ) {
                                        ?>
                                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( self::get_field( 'tax_display_cart' ) == $value ? 'selected' : '' ) ?>><?php echo esc_html( $text ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Calculate taxes as part of deposits to display to customers on the cart item details.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="reminder">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="depart-time-check-orders"><?php esc_html_e( 'Time to check due orders', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input class="depart-time-check-orders" type="time" name="<?php echo esc_attr( self::set_field( 'time_check_orders' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'time_check_orders' ) ) ?>">
                                <p class="description"><?php esc_html_e( 'Set the daily time to check if deposit orders are overdue.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-auto-send-mail"><?php esc_html_e( 'Auto send reminder email', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( self::set_field( 'auto_send_mail' ) ); ?>"
                                           id="depart-auto-send-mail"
                                        <?php echo esc_attr( self::get_field( 'auto_send_mail' ) ) ? 'checked' : '' ?>>
                                    <label></label>
                                </div>
                                <p class="description"><?php esc_html_e( 'Automatically send reminder mail based on the settings below.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-days-send-mail"><?php esc_html_e( 'Days to send reminder email', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="<?php echo esc_attr( self::set_field( 'days_send_mail' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'days_send_mail' ) ) ?>">
                                <div class="description message info vi-ui">
                                    <div class="header">
                                        <?php esc_html_e( 'Reminder emails will be sent \'X\' days before the payments are due.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                    </div>
                                    <ul class="list">
                                        <li><?php esc_html_e( 'Use the number 0 to indicate that on the due date', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-days-interval-mail"><?php esc_html_e( 'Reminder interval', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <p class="description">
                                    <input type="number" name="<?php echo esc_attr( self::set_field( 'days_interval_mail' ) ) ?>"
                                           value="<?php echo esc_attr( self::get_field( 'days_interval_mail' ) ) ?>">
                                    <?php esc_html_e( 'Reminder emails will be sent repeatedly every \'X\' days after partial payments are overdue. ( Set number 0 to not send )', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="text_label">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <td colspan="2">
                                <p class="vi-ui info message">
                                    <?php esc_html_e( 'You can use multiple languages if you are using WPML or Polylang. Make sure you have translated Text & Labels in all your languages before changing them below. ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-add-to-cart-text"><?php esc_html_e( 'Add to cart', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-add-to-cart-text"
                                       name="<?php echo esc_attr( self::set_field( 'add_to_cart_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'add_to_cart_text' ) ) ?>">
                                <?php self::show_multi_languages( 'add_to_cart_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Label of the "Add to Cart" button with Deposit/Installment Plans.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-select-plan-text"><?php esc_html_e( 'Select plan label', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-select-plan-text"
                                       name="<?php echo esc_attr( self::set_field( 'select_plan_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'select_plan_text' ) ) ?>">
                                <?php self::show_multi_languages( 'select_plan_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text in the button (button click to see the installment plans).', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-deposit-check-text"><?php esc_html_e( 'Deposit checkbox label', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-deposit-checkbox-text"
                                       name="<?php echo esc_attr( self::set_field( 'deposit_checkbox_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'deposit_checkbox_text' ) ) ?>">
                                <?php self::show_multi_languages( 'deposit_checkbox_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Label of the checkbox user needs to check to be able to pay in deposit.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-deposit-payment-text"><?php esc_html_e( 'Deposit payment', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-deposit-payment-text"
                                       name="<?php echo esc_attr( self::set_field( 'deposit_payment_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'deposit_payment_text' ) ) ?>">
                                <?php self::show_multi_languages( 'deposit_payment_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text indicating the amount of deposit required when selecting a plan in product details.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-future-payments-text"><?php esc_html_e( 'Future payments', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-future-payments-text"
                                       name="<?php echo esc_attr( self::set_field( 'future_payments_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'future_payments_text' ) ) ?>">
                                <?php self::show_multi_languages( 'future_payments_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text indicating the amount of deposit required in cart item and check out.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-fees-text"><?php esc_html_e( 'Fees ', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-fees-text"
                                       name="<?php echo esc_attr( self::set_field( 'fees_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'fees_text' ) ) ?>">
                                <?php self::show_multi_languages( 'fees_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text indicating the additional amount added when paying in installments (total fee of deposit and all partial payments).', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-payment-date-text"><?php esc_html_e( 'Payment date', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-payment-date-text"
                                       name="<?php echo esc_attr( self::set_field( 'payment_date_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'payment_date_text' ) ) ?>">
                                <?php self::show_multi_languages( 'payment_date_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text shown in the payment plan details, the date by which the installment or full payment is due. It indicates when the customer needs to complete their payment for the order.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-payment-amount-text"><?php esc_html_e( 'Payment amount', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-payment-amount-text"
                                       name="<?php echo esc_attr( self::set_field( 'payment_amount_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'payment_amount_text' ) ) ?>">
                                <?php self::show_multi_languages( 'payment_amount_text' ); ?>
                                <p class="description"><?php esc_html_e( 'Text shown in the payment plan details, represents the specific amount that the customer needs to pay at each installment or for the entire order.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="depart-select-mark-text"><?php esc_html_e( 'Select plan button', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="depart-select-mark-text"
                                       name="<?php echo esc_attr( self::set_field( 'select_mark_text' ) ) ?>"
                                       value="<?php echo esc_attr( self::get_field( 'select_mark_text' ) ) ?>">
                                <?php self::show_multi_languages( 'select_mark_text' ); ?>
                                <p class="description"><?php esc_html_e( 'The text that is displayed in the button to the right of the payment plan details.', 'depart-deposit-and-part-payment-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="email">
                    <div class="vi-ui depart-email-links form">
                        <?php
                        foreach ( self::get_depart_emails() as $context => $emails ) {
                            ?>
                            <div class="field">
                                <h5 class="vi-ui medium header"><?php echo esc_html( $context ) ?></h5>
                                <div class="vi-ui cards">
                                    <?php foreach ( $emails as $class_name => $email ) {
                                        ?>
                                        <div class="vi-ui card">
                                            <div class="image">
                                                <img src="<?php echo esc_url( $email['img'] ) ?>">
                                            </div>
                                            <div class="content">
                                                <div class="header"><?php echo esc_html( $email['title'] ) ?></div>
                                                <div class="description"><?php echo esc_html( $email['description'] ) ?></div>
                                            </div>
                                            <div class="extra content">
                                                <div class="vi-ui button basic blue depart-email-status" data-email_class="<?php echo esc_attr( $class_name ) ?>">
                                                    <?php if ( 'yes' === $email['enabled'] ) {
                                                        esc_html_e( 'Disable', 'depart-deposit-and-part-payment-for-woocommerce' );
                                                    } else {
                                                        esc_html_e( 'enable', 'depart-deposit-and-part-payment-for-woocommerce' );
                                                    }
                                                    ?>
                                                </div>
                                                <a href="<?php echo esc_url( $email['url'] ) ?>"
                                                   class="vi-ui button icon primary"
                                                >
                                                    <i class="edit outline icon "></i>
                                                    <?php esc_html_e( 'Edit email', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="update">
                    <!-- Tab Content !-->
                    <table class="optiontable form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label><?php esc_html_e( 'Auto Update Key', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></label>
                            </th>
                            <td>
                                <div class="fields">
                                    <div class="ten wide field">
                                        <input class="villatheme-autoupdate-key-field" type="text"
                                               name="<?php echo esc_attr( self::set_field( 'key' ) ) ?>"
                                               value="<?php echo esc_attr( self::get_field( 'key' ) ) ?>"/>
                                    </div>
                                    <div class="six wide field">
                                        <span class="vi-ui button green villatheme-get-key-button"
                                              data-href="https://api.envato.com/authorization?response_type=code&client_id=villatheme-download-keys-6wzzaeue&redirect_uri=https://villatheme.com/update-key"
                                              data-id=""><?php echo esc_html__( 'Get Key', 'depart-deposit-and-part-payment-for-woocommerce' ) ?></span>
                                    </div>
                                </div>
                                <?php do_action( 'depart-deposit-and-part-payment-for-woocommerce_key' ) ?>
                                <p class="description">
                                    <?php
                                        printf( "%s <a target='_blank' href='https://villatheme.com/my-download'>https://villatheme.com/my-download</a>. %s <a href='https://villatheme.com/knowledge-base/how-to-use-auto-update-feature/' target='_blank'>%s</a>", esc_html__( 'Please fill your key what you get from', 'depart-deposit-and-part-payment-for-woocommerce' ), esc_html__( 'You can auto update DEPART - Deposit and Part payment for WooCommerce plugin.', 'depart-deposit-and-part-payment-for-woocommerce' ), esc_html__( 'See guide', 'depart-deposit-and-part-payment-for-woocommerce' ) );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui fluid depart-sticky">
                    <button class="vi-ui button depart-save-setting labeled icon primary">
                        <i class="save outline icon"></i> <?php esc_html_e( 'Save', 'depart-deposit-and-part-payment-for-woocommerce' ); ?>
                    </button>
                    <button class="vi-ui button depart-check-key labeled icon"
                            name="<?php echo esc_attr( self::set_field( 'check_key' ) ) ?>">
                        <i class="send outline icon"></i> <?php esc_html_e( 'Save & Check Key', 'woocommerce-notification' ) ?>
                    </button>
                </div>
                <p></p>
            </form>
        </div>
        <?php do_action( 'villatheme_support_depart-deposit-and-part-payment-for-woocommerce' ) ?>
        <?php
    }
    
}