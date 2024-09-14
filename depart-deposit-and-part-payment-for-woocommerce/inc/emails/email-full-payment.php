<?php

namespace VicoDIn\Inc\Emails;

class Email_Full_Payment extends \WC_Email {
	use Email_Trait;

	protected $partial_amount = 0;

	public function __construct() {
		$this->id             = 'depart_full_payment';
		$this->title          = __( 'DEPART - Full payment received', 'depart-deposit-and-part-payment-for-woocommerce' );
		$this->description    = __( 'Full payment emails are sent to customer when installment plan is paid in full', 'depart-deposit-and-part-payment-for-woocommerce' );
		$this->customer_email = true;
		$this->template_html  = 'emails/full-payment.php';
		$this->template_plain = 'emails/plain/full-payment.php';
		$this->declare_placeholders();

		$this->template_base = DEPART_CONST['plugin_dir'] . '/templates/';

		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
		add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $this, 'trigger' ), 10, 2 );

		add_action( 'woocommerce_order_status_failed_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
		add_action( 'woocommerce_order_status_failed_to_completed_notification', array( $this, 'trigger' ), 10, 2 );

		add_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
		add_action( 'woocommerce_order_status_on-hold_to_completed_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {

			if ( ! is_a( $order, 'WC_Order' ) ) {
				return false;
			}

			if ( $order->get_type() != DEPART_CONST['order_type'] ) {
				return false;
			}

//			if ( 'partial' !== $order->get_meta( '_depart_partial_payment_type' ) ) {
//				return false;
//			}

			$parent_order           = wc_get_order( $order->get_parent_id() );
			$parent_order->suborder = $order;
			// Assign values for placeholders
			$this->init_placeholder_values( $order, $parent_order );
            
            /* Recommend sending email to the customer instead of billing email, because they need accounts to make part payment */
            $customer               = $this->object->get_user();
            if ( $customer ) {
                $recipient = $customer->data->user_email;
            } else {
                return false;
            }
            
            $this->recipient = apply_filters( 'depart_get_email_recipient', $recipient, $this );
            
            // Check if installment plan is completed
            $suborder_needs_payment = depart_get_suborder_needs_payment( $parent_order );
            /* Because emails are sent before the remaining amount is calculated by Depart */
            $real_order_remaining = (int)( $this->remaining_amount - $order->get_total() );
			if ( null != $suborder_needs_payment || $real_order_remaining > 0 ) {
				return false;
			}
   
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
            
            return true;
		}

	}

	public function get_default_subject() {
		return esc_html__( '[{site_title}]: Your installment plan has been completed!', 'depart-deposit-and-part-payment-for-woocommerce' );
	}

	public function get_default_heading() {
		return esc_html__( 'Installment plan completed', 'depart-deposit-and-part-payment-for-woocommerce' );
	}

	public function get_default_email_text() {
		return esc_html__( 'Your last payment [ #{wdp_suborder_number} ] for order #{wdp_parent_order_number} has been received.', 'depart-deposit-and-part-payment-for-woocommerce' );
	}

	public function get_default_payment_text() {
		return esc_html__( 'Your installment plan has been fully paid. Your order is now being processed.', 'depart-deposit-and-part-payment-for-woocommerce' );
	}

	function get_email_text() {
		$text = $this->get_option( 'email_text', $this->get_default_email_text() );

		return $text;
	}

	function get_payment_text() {

		$text = $this->get_option( 'payment_text', $this->get_default_payment_text() );

		return $text;
	}

	public function init_form_fields() {
		/* translators: Email placeholders */
		$placeholder_text  = sprintf( wp_kses( __( 'Placeholders available : %s', 'depart-deposit-and-part-payment-for-woocommerce' ), array( 'code' => array() ) ), '<code>' . esc_html( implode( ', ', array_keys( $this->placeholders ) ) ) . '</code>' );
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => esc_html__( 'Enable/disable', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable this email notification', 'depart-deposit-and-part-payment-for-woocommerce' ),
				'default' => 'yes'
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
		wc_get_template( $this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email_text'         => $this->get_email_text(),
				'payment_text'       => $this->get_payment_text(),
				'plain_text'         => false,
				'sent_to_admin'      => false,
				'email'              => $this,
				'current_payment'    => '{wdp_partial_amount}'
			),
			'',
			$this->template_base );
		$html = ob_get_clean();

		return $this->format_string( $html );
	}

	function get_content_plain() {

		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email_text'         => $this->get_email_text(),
				'payment_text'       => $this->get_payment_text(),
				'payment_link'       => $this->object->get_view_order_url(),
				'plain_text'         => true,
				'sent_to_admin'      => false,
				'email'              => $this,
				'current_payment'    => '{wdp_partial_amount}',
				'schedule'           => depart_get_schedule_payments_summary( $this->object )
			),
			'',
			$this->template_base
		);

		return $this->format_string( ob_get_clean() );

	}
}