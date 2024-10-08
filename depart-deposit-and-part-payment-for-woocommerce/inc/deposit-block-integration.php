<?php
namespace VicoDIn\Inc;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class Deposit_Block_Integration implements IntegrationInterface {
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'depart-deposit-and-part-payment-for-woocommerce';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$script_path = 'wc-checkout-block/build/index.js';
		$style_path = 'wc-checkout-block/build/style-index.css';
		$style_rtl_path = 'wc-checkout-block/build/style-index-rtl.css';

		/**
		 * The assets linked below should be a path to a file, for the sake of brevity
		 */
		$script_url = plugins_url( $script_path, __FILE__ );
		$style_url = plugins_url( $style_path, __FILE__ );
		$style_rtl_url = plugins_url( $style_rtl_path, __FILE__ );

		$script_asset_path = dirname( __FILE__ ) . 'wc-checkout-block/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
			);

		wp_enqueue_style(
			'depart-blocks-integration',
			$style_url,
			[],
			$this->get_file_version( $style_path )
		);
        wp_enqueue_style(
			'depart-blocks-integration-rtl',
            $style_rtl_url,
			[],
			$this->get_file_version( $style_rtl_path )
		);

		wp_register_script(
			'depart-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'depart-blocks-integration',
			'depart-deposit-and-part-payment-for-woocommerce',
			dirname( __FILE__ ) . '/languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'depart-blocks-integration' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'depart-blocks-integration' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [
			'expensive_data_calculation' => 'somethings'
		];
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
  
		return DEPART_CONST['version'];
	}
}
