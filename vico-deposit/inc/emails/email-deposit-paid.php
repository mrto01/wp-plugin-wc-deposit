<?php

namespace VicoDIn\Inc\Emails;

defined( 'ABSPATH' ) || exit;

class Email_Deposit_paid extends \WC_Email {
	public function __construct() {
		$this->id               = 'vicodin_deposit_paid';
		$this->title            = __( 'Deposit paid', 'vico-deposit-and-installment' );
		$this->description      = __( 'Deposit paid emails are sent to custom when Deposit is paid', 'vico-deposit-and-installment' );
		$this->customer_email   = true;
		$this->template_html    = 'emails/deposit-paid.php';
		$this->template_plain   = 'emails/plain/deposit-paid.php';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		$this->template_base = VICODIN_CONST['plugin_dir'] . '/templates/';

		add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$parent_order = wc_get_order( $order->get_parent_id() );

			if ( ! is_a( $parent_order, 'WC_Order' ) ) return;
			$this->object                         = $parent_order;
			$this->recipient                      = $this->object->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}'] = $this->object->get_order_number();

			if ( $order->get_type() != 'vwcdi_partial_order' ) {
				return;
			}

			$partial_type = $order->get_meta( '_vicodin_partial_payment_type' );
			if ( 'deposit' != $partial_type ) {
				return;
			}

			$payment_text = __( 'Payment link', 'vico-deposit-and-installment' );
			$this->placeholders['{vicodin_payment_link}'] = '<a href="' . esc_url( $this->object->get_view_order_url() ) . '">' . $payment_text . '</a>';
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

	}

	public function get_default_subject() {
		return __('Your {site_title} order receipt from {order_date}', 'vico-deposit-and-installment');
	}

	public function get_default_heading() {
		return __('Thank you for your order (deposit)', 'vico-deposit-and-installment');
	}
	public function get_default_email_text() {
		return __('Your deposit has been received and your order is now in installments.', 'vico-deposit-and-installment');
	}
	public function get_default_payment_text() {
		return esc_html__('To pay the remaining amount, please visit following link {vicodin_payment_link}', 'vico-deposit-and-installment');
	}
	function get_email_text(){
		$text = $this->get_option('email_text', $this->get_default_email_text());
		return $this->format_string($text);
	}

	function get_payment_text(){
		$text = $this->get_option('payment_text', $this->get_default_payment_text());
		return $this->format_string($text);
	}

	public function init_form_fields() {
		$placeholder_text  = sprintf( wp_kses( __( 'Placeholders available : %s', 'vico-deposit-and-installment' ), array( 'code' => array() ) ), '<code>' . esc_html( implode( ', ', array_keys( $this->placeholders ) ) ) . '</code>' );
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => esc_html__( 'Enable/disable',
					'vico-deposit-and-installment' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable this email notification',
					'vico-deposit-and-installment' ),
				'default' => 'yes'
			),
			'subject'      => array(
				'title'       => esc_html__( 'Subject',
					'vico-deposit-and-installment' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'desc_tip'    => true,
				'placeholder' => $this->get_default_subject(),
				'default'     => $this->get_default_subject(),
			),
			'heading'      => array(
				'title'       => esc_html__( 'Email heading',
					'vico-deposit-and-installment' ),
				'type'        => 'text',
				'description' => sprintf( wp_kses( __( 'Main heading contained within the email. <code>%s</code>.', 'vico-deposit-and-installment' ), array( 'code' => array() ) ), $placeholder_text ),
				'desc_tip'    => true,
				'placeholder' => $this->get_default_heading(),
				'default'     => $this->get_default_heading(),
			),
			'email_text'   => array(
				'title'       => esc_html__( 'Email text',
					'vico-deposit-and-installment' ),
				'type'        => 'textarea',
				'description' => $placeholder_text,
				'desc_tip'    => true,
				'placeholder' => $this->get_default_email_text(),
				'default'     => $this->get_default_email_text(),
				'css'         => 'width:400px; height: 50px;',
			),
			'payment_text' => array(
				'title'       => __( 'Payment text', 'vico-deposit-and-installment'),
				'type'        => 'textarea',
				'description' => $placeholder_text,
				'desc_tip'    => true,
				'placeholder' => $this->get_default_payment_text(),
				'default'     => $this->get_default_payment_text(),
				'css'         => 'width:400px; height: 50px;',
			),
			'email_type'   => array(
				'title'       => __( 'Email type', 'woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'vico-deposit-and-installment' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}


	function get_content_html(){
		ob_start();
		wc_get_template( $this->template_html ,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email_text'         => $this->get_email_text(),
				'payment_text'       => $this->get_payment_text(),
				'plain_text'         => false,
				'sent_to_admin'      => false,
				'email'              => $this,
			),
			'',
			$this->template_base );
		return ob_get_clean();
	}
	function get_content_plain(){

		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email_text'         => $this->get_email_text(),
				'payment_text'       => $this->get_payment_text(),
				'plain_text'         => true,
				'sent_to_admin'      => false,
				'email'              => $this,
				'schedule'           => $this->object->get_meta( 'vicodin_deposit_payment_schedule' )
			),
			'',
			$this->template_base
		);

		return ob_get_clean();

	}
}
