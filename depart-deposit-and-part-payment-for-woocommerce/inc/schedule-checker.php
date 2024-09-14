<?php

namespace VicoDIn\Inc;

use VicoDIn\Inc\Payment\Auto_Payment;

defined( 'ABSPATH' ) || exit;

class Schedule_Checker {
    
    static $instance = null;
    
    public function __construct() {
        add_action( 'depart_order_check_event', [ $this, 'check_orders_for_payment' ] );
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public static function remove_schedule() {
        $timestamp = wp_next_scheduled( 'depart_order_check_event' );
        if ( $timestamp ) {
            wp_unschedule_hook( 'depart_order_check_event' );
        }
    }
    
    public static function schedule_order_check_event( $time = '' ) {
        if ( $time ) {
            self::remove_schedule();
            wp_schedule_event( self::strtotime( $time ), 'daily', 'depart_order_check_event' );
        } else {
            if ( ! wp_next_scheduled( 'depart_order_check_event' ) ) {
                wp_schedule_event( self::strtotime( '6:00:00' ), 'daily', 'depart_order_check_event' );
            }
        }
    }
    
    public function check_orders_for_payment() {
        global $depart_settings;
        
        $days_interval_mail = absint( $depart_settings['days_interval_mail'] );
        $days_send_mail     = absint( $depart_settings['days_send_mail'] ) * 86400;
        $date_limit         = self::strtotime( '23:59:59' ) + $days_send_mail;
        
        $args = [
            'type'         => DEPART_CONST['order_type'],
            'status'       => [ 'wc-pending', 'wc-failed' ],
            'limit'        => - 1,
            'meta_key'     => '_depart_partial_payment_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'   => $date_limit, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_compare' => '<=',
        ];
        
        $orders         = wc_get_orders( $args );
        $parent_ids     = [];
        $overdue_orders = [];
        foreach ( $orders as $order ) {
            $due_date = $order->get_meta( '_depart_partial_payment_date' );
            
            // Check if the due date is less than this date
            if ( gmdate( 'Ymd', $due_date ) < gmdate( 'Ymd', time() ) ) {
                // Get overdue partial orders to consider sending reminder emails again
                $overdue_orders[] = [
                    'id'       => $order->get_id(),
                    'order'    => $order,
                    'due_date' => strtotime( gmdate( 'Ymd', $due_date ) ),
                ];
                
                // Get partial order's parents to change it to overdue status
                $parent_ids[] = $order->get_parent_id();
            }else if (gmdate( 'Ymd', $due_date ) == gmdate( 'Ymd', time() )){
                // Auto charge payment if Automatic Payments option is enabled
                if ( Auto_Payment::is_available() ) {
                    Auto_Payment::process_payment( $order );
                }
            } elseif ( gmdate( 'Ymd', $due_date ) === gmdate( 'Ymd', $date_limit ) ) {
                if ( ! $order->get_meta( '_depart_reminder_email_sent' ) && $depart_settings['auto_send_mail'] ) {
                    $emails = WC()->mailer()->get_emails();
                    if ( isset( $emails['depart_email_payment_reminder'] ) ) {
                        $emails['depart_email_payment_reminder']->trigger( $order->get_id(), $order );
                    }
                }
            }
        }
        
        // Change order's statuses to overdue
        $parent_ids = array_unique( $parent_ids );
        if ( ! empty( $parent_ids ) ) {
            $parent_orders = wc_get_orders( [
                'post__in' => $parent_ids,
                'limit'    => - 1,
            ] );
            
            foreach ( $parent_orders as $order ) {
                if ( 'overdue' !== $order->get_status() ) {
                    $order->set_status( 'overdue' );
                    $order->save();
                }
            }
        }
        
        // Send reminder email again if reminder interval is set
        if ( ! empty( $overdue_orders ) && $days_interval_mail > 0 ) {
            $today = strtotime( gmdate( 'Ymd', time() ) );
            foreach ( $overdue_orders as $order ) {
                // Calculate whether the email repetition interval is appropriate or not
                $interval_days  = ( $today - $order['due_date'] ) / 86400;
                $days_remaining = $interval_days % ( $days_interval_mail + 1 );
                if ( 0 === $days_remaining && $depart_settings['auto_send_mail'] ) {
                    $emails = WC()->mailer()->get_emails();
                    if ( isset( $emails['depart_email_payment_reminder'] ) ) {
                        $emails['depart_email_payment_reminder']->trigger( $order['id'], $order );
                        wp_send_json_success();
                    }
                }
            }
        }
    }
    
    public static function strtotime( $str ) {
        // This function behaves a bit like PHP's StrToTime() function, but taking into account the WordPress site's timezone
        
        $tz_string = get_option( 'timezone_string' );
        $tz_offset = get_option( 'gmt_offset', 0 );
        
        if ( ! empty( $tz_string ) ) {
            // If site timezone option string exists, use it
            $timezone = $tz_string;
        } elseif ( 0 == $tz_offset ) {
            // get UTC offset, if it isn’t set then return UTC
            $timezone = 'UTC';
        } else {
            $timezone = $tz_offset;
            
            if ( substr( $tz_offset, 0, 1 ) != "-" && substr( $tz_offset, 0, 1 ) != "+" && substr( $tz_offset, 0, 1 ) != "U" ) {
                $timezone = "+" . $tz_offset;
            }
        }
        
        $datetime = new \DateTime( $str, new \DateTimeZone( $timezone ) );
        
        return intval( $datetime->format( 'U' ) );
    }
    
}