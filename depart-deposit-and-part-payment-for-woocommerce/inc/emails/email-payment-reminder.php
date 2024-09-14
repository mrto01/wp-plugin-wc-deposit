<?php

namespace VicoDIn\Inc\Emails;

class Email_Payment_Reminder extends \WC_Email {
    
    use Email_Trait;
    
    public function __construct() {
        $this->id             = 'depart_payment_reminder';
        $this->title          = __( 'DEPART - Partial payment reminder', 'depart-deposit-and-part-payment-for-woocommerce' );
        $this->description    = __( 'Part payment reminder emails are sent to customer when an Suborder due.', 'depart-deposit-and-part-payment-for-woocommerce' );
        $this->customer_email = true;
        $this->template_html  = 'emails/payment-reminder.php';
        $this->template_plain = 'emails/plain/payment-reminder.php';
        $this->declare_placeholders();
        $this->template_base = DEPART_CONST['plugin_dir'] . '/templates/';
        
        parent::__construct();
    }
    
    public function trigger( $order_id, $order = false ) {
        $this->setup_locale();
        
        if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order_id );
        }
        
        if ( is_a( $order, 'WC_Order' ) ) {
            if ( $order->get_type() === 'shop_order' ) {
                $parent_order = $order;
                $order        = depart_get_suborder_needs_payment( $order );
            } elseif ( $order->get_type() === DEPART_CONST['order_type'] ) {
                $parent_order = wc_get_order( $order->get_parent_id() );
            }
            
            if ( ! $parent_order || ! $order ) {
                return false;
            }
            
            $this->init_placeholder_values( $order, $parent_order );
            $parent_order->suborder = $order;
            
            /* Recommend sending email to the customer instead of billing email, because they need accounts to make part payment */
            $customer = $this->object->get_user();
            if ( $customer ) {
                $recipient = $customer->data->user_email;
            } else {
                return false;
            }
            
            $this->recipient = apply_filters( 'depart_get_email_recipient', $recipient, $this );
            
            if ( $this->is_enabled() && $this->get_recipient() ) {
                $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
                $order->update_meta_data( '_depart_reminder_email_sent', true );
                $order->save();
            }
            
            $this->restore_locale();
            
            return true;
        }
    }
    
    public function get_default_subject() {
        return esc_html__( '[{site_title}]: Payment reminder for order #{wdp_parent_order_number}', 'depart-deposit-and-part-payment-for-woocommerce' );
    }
    
    public function get_default_heading() {
        return esc_html__( 'Payment reminder for order #{wdp_parent_order_number}', 'depart-deposit-and-part-payment-for-woocommerce' );
    }
    
    public function get_default_email_text() {
        return esc_html__( 'Your partial payment for order #{wdp_parent_order_number} [Order #{wdp_suborder_number}] is due on {wdp_payment_due_date}.', 'depart-deposit-and-part-payment-for-woocommerce' );
    }
    
    public function get_default_payment_text() {
        return esc_html__( 'To make the payment, please visit following link {wdp_payment_link}', 'depart-deposit-and-part-payment-for-woocommerce' );
    }
    
    function get_email_text() {
        $text = $this->get_option( 'email_text', $this->get_default_email_text() );
        
        return $this->format_string( $text );
    }
    
    function get_payment_text() {
        $text = $this->get_option( 'payment_text', $this->get_default_payment_text() );
        
        return $this->format_string( $text );
    }
    
    public function init_form_fields() {
        /* translators: Email placeholders */
        $placeholder_text  = sprintf( wp_kses( __( 'Placeholders available : %s', 'depart-deposit-and-part-payment-for-woocommerce' ), array( 'code' => array() ) ), '<code>' . esc_html( implode( ', ', array_keys( $this->placeholders ) ) ) . '</code>' );
        $this->form_fields = array(
            'enabled'      => array(
                'title'   => esc_html__( 'Enable/disable', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => esc_html__( 'Enable this email notification', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'default' => 'yes',
            ),
            'subject'      => array(
                'title'       => esc_html__( 'Subject', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'text',
                'description' => $placeholder_text,
                'desc_tip'    => true,
                'placeholder' => $this->get_default_subject(),
                'default'     => $this->get_default_subject(),
            ),
            'heading'      => array(
                'title'       => esc_html__( 'Email heading', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'text',
                /* translators: Placeholder text */
                'description' => sprintf( wp_kses( __( 'Main heading contained within the email. <code>%s</code>.', 'depart-deposit-and-part-payment-for-woocommerce' ), array( 'code' => array() ) ), $placeholder_text ),
                'desc_tip'    => true,
                'placeholder' => $this->get_default_heading(),
                'default'     => $this->get_default_heading(),
            ),
            'email_text'   => array(
                'title'       => esc_html__( 'Email text', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => $placeholder_text,
                'desc_tip'    => true,
                'placeholder' => $this->get_default_email_text(),
                'default'     => $this->get_default_email_text(),
                'css'         => 'width:400px; height: 50px;',
            ),
            'payment_text' => array(
                'title'       => __( 'Payment text', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => $placeholder_text,
                'desc_tip'    => true,
                'placeholder' => $this->get_default_payment_text(),
                'default'     => $this->get_default_payment_text(),
                'css'         => 'width:400px; height: 50px;',
            ),
            'email_type'   => array(
                'title'       => __( 'Email type', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'depart-deposit-and-part-payment-for-woocommerce' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
    
    function get_content_html() {
        ob_start();
        wc_get_template( $this->template_html, array(
            'order'              => $this->object,
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'email_text'         => $this->get_email_text(),
            'payment_text'       => $this->get_payment_text(),
            'plain_text'         => false,
            'sent_to_admin'      => false,
            'email'              => $this,
            'current_payment'    => '{wdp_partial_amount}',
        ), '', $this->template_base );
        
        return $this->format_string( ob_get_clean() );
    }
    
    function get_content_plain() {
        ob_start();
        wc_get_template( $this->template_plain, array(
            'order'              => $this->object,
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'email_text'         => $this->get_email_text(),
            'payment_text'       => $this->get_payment_text(),
            'plain_text'         => true,
            'sent_to_admin'      => false,
            'email'              => $this,
            'current_payment'    => '{wdp_partial_amount}',
            'schedule'           => depart_get_schedule_payments_summary( $this->object ),
        ), '', $this->template_base );
        
        return $this->format_string( ob_get_clean() );
    }
    
}