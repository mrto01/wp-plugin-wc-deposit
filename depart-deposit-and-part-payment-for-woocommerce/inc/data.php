<?php

namespace VicoDIn\Inc;

defined( 'ABSPATH' ) || exit;

class Data {
    
    protected static $instance = null;
    private $settings;
    private $plans;
    private $rules;
    
    private function __construct() {
        $this->plans    = $this->default_plan();
        $this->rules    = $this->default_rule();
        $this->settings = $this->sample_setting();
    }
    
    public static function load() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function default_plan() {
        $_plans = get_option( 'depart_payment_plan' );
        if ( false === $_plans ) {
            $plan   = [
                'plan_id'          => 1,
                'plan_name'        => 'Sample plan',
                'plan_type'        => 'global',
                'plan_active'      => true,
                'plan_description' => 'sample description',
                'deposit'          => 50,
                'deposit_fee'      => 0,
                'plan_schedule'    => [
                    [
                        'partial'   => 50,
                        'after'     => 7,
                        'date_type' => 'day',
                        'fee'       => '',
                    ],
                ],
                'duration'         => '7 Days',
                'total'            => 100,
                'fee_total'        => 0,
            ];
            $_plans = [ '1' => $plan ];
            update_option( 'depart_payment_plan', $_plans );
        }
        
        return $_plans;
    }
    
    public function default_rule() {
        $_rules = get_option( 'depart_deposit_rule' );
        if ( false === $_rules ) {
            $rule   = [
                'rule_id'             => '2',
                'rule_name'           => 'Default rule',
                'rule_active'         => true,
                'rule_categories_inc' => [],
                'rule_categories_exc' => [],
                'rule_tags_inc'       => [],
                'rule_tags_exc'       => [],
                'rule_users_inc'      => [],
                'rule_users_exc'      => [],
                'rule_products_inc'   => [],
                'rule_products_exc'   => [],
                'rule_price_range'    => [
                    'price_start' => '',
                    'price_end'   => '',
                ],
                'payment_plans'       => [ '1' ],
                'rule_apply_for'      => 'All',
                'rule_plan_names'     => 'Sample plan',
            ];
            $_rules = [ '2' => $rule ];
            update_option( 'depart_deposit_rule', $_rules );
        }
        
        return $_rules;
    }
    
    public function sample_setting() {
        $depart_settings = get_option( 'depart_deposit_setting', [] );
        $args            = [
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
            'show_fees'               => '1',
            'auto_charge'             => '0',
            
            /* Advanced */
            'deposit_on_checkout'     => '0',
            'force_deposit'           => '0',
            'rewrite_suborder_number' => '1',
            'reduce_stock_status'     => 'deposit',
            'paid_full_status'        => 'processing',
            'tax_display_shop'        => 'default',
            'tax_display_cart'        => 'default',
            
            /* Text and labels */
            'add_to_cart_text'        => 'Add deposit',
            'select_plan_text'        => 'Select plan',
            'deposit_checkbox_text'   => 'Deposit payment',
            'deposit_payment_text'    => 'Deposit',
            'future_payments_text'    => 'Future payments',
            'fees_text'               => 'Fees',
            'payment_date_text'       => 'Payment date',
            'payment_amount_text'     => 'Amount',
            'select_mark_text'        => 'Select plan',
            
            /* Reminder */
            'time_check_orders'       => '06:00',
            'auto_send_mail'          => '1',
            'days_send_mail'          => 0,
            'days_interval_mail'      => 0,
        ];
        if ( empty( $depart_settings ) ) {
            update_option( 'depart_deposit_setting', $args );
        }
        
        return array_merge( $args, $depart_settings );
    }
    
    private function get_option( $key, $options ) {
        if ( isset( $options[ $key ] ) ) {
            return $options[ $key ];
        } else {
            return '';
        }
    }
    
    public function get_plan( $plan_id ) {
        $plan = $this->get_option( $plan_id, $this->plans );
        
        return apply_filters( 'depart_get_plan_singular', $plan );
    }
    
    public function get_plans() {
        return apply_filters( 'depart_get_payment_plans', $this->plans );
    }
    
    public function update_plans( $plans ) {
        $plans = apply_filters( 'depart_update_payment_plans', $plans );
        update_option( 'depart_payment_plan', $plans );
    }
    
    public function get_rule( $rule_id ) {
        $rule = $this->get_option( $rule_id, $this->rules );
        
        return apply_filters( 'depart_get_deposit_rule_singular', $rule );
    }
    
    public function get_rules() {
        return apply_filters( 'depart_get_deposit_rules', $this->rules );
    }
    
    public function update_rules( $rules ) {
        $rules = apply_filters( 'depart_update_payment_plans', $rules );
        update_option( 'depart_deposit_rule', $rules );
    }
    
    public function get_setting( $key ) {
        $setting = $this->get_option( $key, $this->settings );
        
        return apply_filters( 'depart_get_deposit_setting', $setting );
    }
    
    public function get_settings() {
        return apply_filters( 'depart_get_deposit_settings', $this->settings );
    }
    
}