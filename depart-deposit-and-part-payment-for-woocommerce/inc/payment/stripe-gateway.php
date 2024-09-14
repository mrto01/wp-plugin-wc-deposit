<?php

namespace VicoDIn\inc\payment;

use Exception;
use WC_Gateway_Stripe;
use WC_Gateway_Stripe_Sepa;
use WC_Payment_Tokens;
use WC_Stripe_API;
use WC_Stripe_Customer;
use WC_Stripe_Exception;
use WC_Stripe_Helper;
use WC_Stripe_Logger;
use WC_Stripe_Order_Handler;

defined( 'ABSPATH' ) || exit;

class Stripe_Gateway implements Gateway_Interface {

	public static $instance = null;
	public $retry_interval = 1;

	public function is_available() {
		$available_gateways = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
		if ( array_intersect( $available_gateways, $this->support_methods() ) ) {
			return true;
		}

		return false;
	}

	public function support_methods() {
		return array(
			WC_Gateway_Stripe::ID,
			WC_Gateway_Stripe_Sepa::ID
		);
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function process_part_payment( $suborder, $token, $retry = true ) {

		if ( class_exists( 'WC_Stripe_Order_Handler' ) && is_callable( array( 'WC_Stripe_Order_Handler', 'get_instance' ) ) ) {
			$stripe_order_handler = WC_Stripe_Order_Handler::get_instance();
			try {

				// Get source from order
				$prepared_source = $this->prepare_order_source( $token );

				$stripe_order_handler->check_source( $prepared_source );
				$stripe_order_handler->save_source_to_order( $suborder, $prepared_source );


				$amount = $suborder->get_total();

//				Complete free Order
				if ( 0 >= $amount ) {
					$suborder->payment_complete();
				}

				$stripe_order_handler->validate_minimum_order_amount( $suborder );


				WC_Stripe_Logger::log( "Info: Begin processing payment for order {$suborder->get_order_number} for the amount of {$suborder->get_total()}" );

//				Turn capture method to automatic;
//              Result from Stripe API request.
				$response = null;
				if ( 'stripe_sepa' === $suborder->get_payment_method( 'edit' ) ) {
					$request            = $stripe_order_handler->generate_payment_request( $suborder, $prepared_source );
					$request['capture'] = 'true';
					$response           = WC_Stripe_API::request( $request );
				} else {
					$stripe_order_handler->lock_order_payment( $suborder );
					add_filter( 'wc_stripe_generate_payment_request', array( $this, 'change_capture_method_to_automatic' ), 10, 2 );
					$response = $stripe_order_handler->create_and_confirm_intent_for_off_session( $suborder, $prepared_source, $amount );
					$response = end( $response->charges->data );
				}


				if ( ! empty( $response->error ) ) {
					// We want to retry.
					if ( $stripe_order_handler->is_retryable_error( $response->error ) ) {
						if ( $retry ) {
							// Don't do anymore retries after this.
							if ( 5 <= $this->retry_interval ) {
								$this->process_part_payment( $suborder, $token, false );
							}

							sleep( $this->retry_interval );

							$this->retry_interval ++;

							$this->process_part_payment( $suborder, $token, true );
						} else {
							$localized_message = esc_html__( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'depart-deposit-and-part-payment-for-woocommerce' );
							$suborder->add_order_note( $localized_message );
							throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
						}
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					if ( 'card_error' === $response->error->type ) {
						$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
					} else {
						$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
					}

					$suborder->add_order_note( $localized_message );

					throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
				} else {
					do_action( 'wc_gateway_stripe_process_payment', $response, $suborder );
					// Process valid response.
					$stripe_order_handler->process_response( $response, $suborder );
					$stripe_order_handler->unlock_order_payment( $suborder );
				}
			} catch ( WC_Stripe_Exception $e ) {
				WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
				do_action( 'wc_gateway_stripe_process_payment_error', $e, $suborder );
				$suborder->update_status( 'failed' );
			}
		}
	}

	public function prepare_order_source( \WC_Payment_Token $token ) {
		$stripe_customer = new WC_Stripe_Customer();
		$token_id        = $token->get_id();

		$stripe_customer_id = $this->get_stripe_customer_id( $token );

		if ( $stripe_customer_id ) {
			$stripe_customer->set_id( $stripe_customer_id );
		}

//		Get payment source from token saved
		$source_id     = $token->get_token();
		$source_object = WC_Stripe_API::get_payment_method( $source_id );

		return (object) [
			'token_id'       => $token_id,
			'customer'       => $stripe_customer->get_id(),
			'source'         => $source_id,
			'source_object'  => $source_object,
			'payment_method' => $source_object->id,
		];

	}

	public function get_stripe_customer_id( $token ) {

		$customer = get_user_option( '_stripe_customer_id', $token->get_user_id() );

		return $customer;
	}

	public function change_capture_method_to_automatic( $post_data ) {
		$post_data['capture'] = 'true';

		return $post_data;
	}

}