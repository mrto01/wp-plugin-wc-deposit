<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Enqueue {
	protected static $instance = null;
	protected $slug;

	public function __construct() {
		$this->slug = VICODIN_CONST['assets_slug'];
		add_action( 'admin_enqueue_scripts',
			array( $this, 'enqueue_scripts' ) );
	}

	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function enqueue_scripts() {
		$screen_id = get_current_screen()->id;

//		if ( !in_array($screen_id , [ 'toplevel_page_vicodin_menu', 'vico-deposit_page_vicodin_rule', 'vico-deposit_page_vicodin_setting', 'product' ])){
//			return;
//		}

		$this->register_scripts();

		$enqueue_styles = $enqueue_scripts = [];

		$localize_script = '';

		$params = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'vicodin_nonce' ),
		];
		switch ( $screen_id ) {
			case 'toplevel_page_vicodin_menu':
				$enqueue_styles  = [
					'vico-deposit',
					'checkbox',
					'table',
					'button',
					'icon',
					'loader',
					'form',
					'segment',
					'transition',
					'dropdown'
				];
				$enqueue_scripts = [
					'checkbox',
					'plan',
					'transition',
					'dropdown'
				];
				$localize_script = 'plan';
				break;
			case 'vico-deposit_page_vicodin_rule':
				$enqueue_styles  = [
					'vico-deposit',
					'checkbox',
					'table',
					'button',
					'icon',
					'loader',
					'form',
					'segment',
					'transition',
					'dropdown'
				];
				$enqueue_scripts = [
					'checkbox',
					'rule',
					'transition',
					'dropdown'
				];
				$localize_script = 'rule';
				break;
			case 'vico-deposit_page_vicodin_setting':
				$enqueue_styles  = [
					'setting',
					'tab',
					'menu',
					'segment',
					'button',
					'form',
					'icon',
					'table',
					'checkbox',
					'dropdown',
					'transition'
				];
				$enqueue_scripts = [
					'tab',
					'setting',
					'checkbox',
					'transition',
					'dropdown'
				];
				break;
			case 'product':
				$enqueue_styles = [ 'icon', 'woocommerce-backend-product' ];
				$enqueue_scripts = [ 'woocommerce-backend-product' ];
				$localize_script = 'woocommerce-backend-product';
				break;
			case 'shop_order':
			case 'woocommerce_page_wc-orders':
			case 'woocommerce_page_wc-orders--vwcdi_partial_order':
				$enqueue_styles     = [ 'icon', 'woocommerce-backend-order' ];
				$enqueue_scripts    = [ 'woocommerce-backend-order' ];
				$localize_script    = 'woocommerce-backend-order';
				break;

		}

		foreach ( $enqueue_styles as $style ) {
			wp_enqueue_style( $this->slug . $style );
		}

		foreach ( $enqueue_scripts as $script ) {
			wp_enqueue_script( $this->slug . $script );
		}

		if ( $localize_script ) {
			wp_localize_script( $this->slug . $localize_script, 'vicodinParams',
				$params );
		}
	}

	public function register_scripts() {
		$suffix = WP_DEBUG ? '' : '.min';

		$lib_styles = [
			'icon',
			'tab',
			'menu',
			'segment',
			'button',
			'form',
			'icon',
			'table',
			'checkbox',
			'dropdown',
			'transition',
			'modal',
			'loader'
		];

		foreach ( $lib_styles as $style ) {
			wp_register_style( $this->slug . $style,
				VICODIN_CONST['libs_url'] . $style . '.min.css', '',
				VICODIN_CONST['version'] );

		}

		$styles = [ 'vico-deposit', 'setting', 'woocommerce-backend-product', 'woocommerce-backend-order' ];
		foreach ( $styles as $style ) {
			wp_register_style( $this->slug . $style,
				VICODIN_CONST['dist_url'] . $style . '.css', '',
				VICODIN_CONST['version'] );

		}

		$lib_scripts = [
			'tab',
			'checkbox',
			'transition',
			'dimmer',
			'dropdown',
			'modal',
			'jquery-ui-sortable'
		];

		foreach ( $lib_scripts as $script ) {
			wp_register_script( $this->slug . $script, VICODIN_CONST['libs_url'] . $script . '.min.js', array( 'jquery' ), VICODIN_CONST['version'], array( 'in_footer' => true ) );
		}

		$scripts = [
			'setting'                     => [ 'jquery' ],
			'plan'                        => [ 'jquery' ],
			'rule'                        => [ 'jquery', 'jquery-ui-sortable' ],
			'woocommerce-backend-product' => ['jquery'],
			'woocommerce-backend-order'   => ['jquery']
		];
		foreach ( $scripts as $script => $depends ) {
			wp_register_script( $this->slug . $script, VICODIN_CONST['dist_url'] . $script . $suffix . '.js', $depends, VICODIN_CONST['version'], array( 'in_footer' => true) );
		}
	}

}