<?php

namespace VicoDIn\Admin;

defined( 'ABSPATH' ) || exit;

class Enqueue {
    
    protected static $instance = null;
    protected $slug;
    
    public function __construct() {
        $this->slug = DEPART_CONST['assets_slug'];
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
    
    public static function instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function enqueue_scripts() {
        $screen_id = get_current_screen()->id;
        
        $this->register_scripts();
        
        $enqueue_styles = $enqueue_scripts = [];
        
        $localize_script = '';
        
        $params = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'depart_nonce' ),
        ];
        
        switch ( $screen_id ) {
            case 'toplevel_page_depart_menu':
                $enqueue_styles  = [
                    'depart',
                    'checkbox',
                    'table',
                    'button',
                    'icon',
                    'loader',
                    'form',
                    'segment',
                    'transition',
                    'dropdown',
                    'input',
                    'popup',
                    'label',
                    'villatheme-show-message',
                ];
                $enqueue_scripts = [
                    'checkbox',
                    'plan',
                    'transition',
                    'dropdown',
                    'popup',
                    'villatheme-show-message',
                ];
                $localize_script = 'plan';
                break;
            case 'depart_page_depart_rule':
                $enqueue_styles  = [
                    'depart',
                    'checkbox',
                    'table',
                    'button',
                    'icon',
                    'loader',
                    'form',
                    'segment',
                    'transition',
                    'dropdown',
                    'input',
                    'popup',
                    'label',
                    'message',
                    'villatheme-show-message',
                ];
                $enqueue_scripts = [
                    'checkbox',
                    'rule',
                    'transition',
                    'dropdown',
                    'popup',
                    'villatheme-show-message',
                ];
                $localize_script = 'rule';
                break;
            case 'depart_page_depart_setting':
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
                    'transition',
                    'message',
                    'timepicker',
                    'input',
                    'popup',
                    'label',
                    'grid',
                    'card',
                    'header',
                    'loader',
                    'dimmer',
                ];
                $enqueue_scripts = [
                    'tab',
                    'setting',
                    'checkbox',
                    'transition',
                    'dropdown',
                    'address-1.6',
                    'timepicker',
                    'popup',
                    'dimmer',
                ];
                $localize_script = 'setting';
                break;
            case 'product':
                $enqueue_styles  = [ 'icon', 'woocommerce-backend-product' ];
                $enqueue_scripts = [ 'woocommerce-backend-product' ];
                $localize_script = 'woocommerce-backend-product';
                break;
            case 'shop_order':
            case 'woocommerce_page_wc-orders':
            case 'woocommerce_page_wc-orders--depart_partial_order':
            case 'edit-shop_order':
            case 'edit-depart_partial_order':
                $enqueue_styles  = [
                    'icon',
                    'woocommerce-backend-order',
                    'villatheme-show-message'
                ];
                $enqueue_scripts = [
                    'woocommerce-backend-order',
                    'villatheme-show-message'
                ];
                $localize_script = 'woocommerce-backend-order';
                break;
        }
        
        foreach ( $enqueue_styles as $style ) {
            wp_enqueue_style( $this->slug . $style );
        }
        
        foreach ( $enqueue_scripts as $script ) {
            wp_enqueue_script( $this->slug . $script );
        }
        
        if ( $localize_script ) {
            wp_localize_script( $this->slug . $localize_script, 'vicodinParams', $params );
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
            'loader',
            'message',
            'timepicker',
            'input',
            'popup',
            'label',
            'grid',
            'card',
            'header',
        ];
        
        foreach ( $lib_styles as $style ) {
            wp_register_style( $this->slug . $style, DEPART_CONST['libs_url'] . $style . '.min.css', '', DEPART_CONST['version'] );
        }
        
        $styles = [ 'depart', 'setting', 'woocommerce-backend-product', 'woocommerce-backend-order', 'villatheme-show-message' ];
        foreach ( $styles as $style ) {
            wp_register_style( $this->slug . $style, DEPART_CONST['dist_url'] . $style . $suffix . '.css', '', DEPART_CONST['version'] );
        }
        
        $lib_scripts = [
            'tab',
            'checkbox',
            'transition',
            'dimmer',
            'dropdown',
            'modal',
            'jquery-ui-sortable',
            'address-1.6',
            'timepicker',
            'popup',
        ];
        
        foreach ( $lib_scripts as $script ) {
            wp_register_script( $this->slug . $script, DEPART_CONST['libs_url'] . $script . '.min.js', array( 'jquery' ), DEPART_CONST['version'], array( 'in_footer' => true ) );
        }
        
        $scripts = [
            'setting'                     => [ 'jquery' ],
            'plan'                        => [ 'jquery' ],
            'rule'                        => [ 'jquery', 'jquery-ui-sortable' ],
            'woocommerce-backend-product' => [ 'jquery' ],
            'woocommerce-backend-order'   => [ 'jquery' ],
            'villatheme-show-message'     => [ 'jquery' ],
        ];
        foreach ( $scripts as $script => $depends ) {
            wp_register_script( $this->slug . $script, DEPART_CONST['dist_url'] . $script . $suffix . '.js', $depends, DEPART_CONST['version'], array( 'in_footer' => true ) );
        }
    }
    
}