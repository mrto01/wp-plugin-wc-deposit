<?php

namespace VicoDIn\Inc\Payment;

use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class PPCP_Gateway implements Gateway_Interface {
	use RequestTrait;

	static $instance = null;

	private $host;

	private $bearer;

	private $logger;


	public function support_methods() {
		return array(
			PayPalGateway::ID,
			CreditCardGateway::ID
		);
	}

	public function is_available() {
		$available_gateways = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
		if ( array_intersect( $available_gateways, $this->support_methods() ) ) {
			return true;
		}

		return false;
	}

	public function __construct() {
		$this->host   = PPCP::container()->get( 'api.host' );
		$this->bearer = PPCP::container()->get( 'api.bearer' );
		$this->logger = PPCP::container()->get( 'woocommerce.logger.woocommerce' );
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function process_part_payment( $suborder, $token ) {
		$method = '';

		// change payment source type if payment method was card
		if ( $token->get_gateway_id( 'edit' ) === CreditCardGateway::ID ) {
			$method = 'card';
		} elseif ( $token->get_gateway_id( 'edit' ) === $method = PayPalGateway::ID ) {
			$method = 'paypal';
		}
		if ( empty( $method ) ) {
			return;
		}

		$data = array(
			'intent'         => "CAPTURE",
			'payment_source' => array(
				$method => array(
					'vault_id' => $token->get_token( 'edit' )
				)
			),
			'purchase_units' => array(
				array(
					'amount' => array(
						'currency_code' => $suborder->get_currency(),
						'value'         => $suborder->get_total()
					)
				)
			),
		);
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request($url, $args);

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not capture order.', 'depart-deposit-and-part-payment-for-woocommerce' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);

			$this->logger->log(
				'warning',
				sprintf(
					'Failed to capture order. PayPal API response: %1$s',
					$error->getMessage()
				),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$suborder->payment_complete();
	}


}
