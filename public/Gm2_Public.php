<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Public {

    public function run() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        if (get_option('gm2_enable_tariff', '1') === '1') {
            add_action('woocommerce_cart_calculate_fees', [$this, 'add_tariff_fees']);
        }
    }

    public function enqueue_scripts() {
        $in_edit_mode = (
            ( defined( 'ELEMENTOR_EDITOR' ) && ELEMENTOR_EDITOR ) ||
            ( class_exists( '\Elementor\Plugin' )
              && ( ( $p = \Elementor\Plugin::instance() )
                   && ( ( isset( $p->editor ) && method_exists( $p->editor, 'is_edit_mode' ) && $p->editor->is_edit_mode() )
                        || ( isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode() ) ) ) )
            || ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] )
            || isset( $_GET['elementor-preview'] )
        );

        if ( is_admin() && ! $in_edit_mode && ! wp_doing_ajax() ) {
            return;
        }

        wp_enqueue_style(
            'gm2-public-style',
            GM2_PLUGIN_URL . 'public/css/gm2-public.css',
            [],
            GM2_VERSION
        );
        wp_enqueue_script(
            'gm2-public-script',
            GM2_PLUGIN_URL . 'public/js/gm2-public.js',
            [],
            GM2_VERSION,
            true
        );

        wp_register_style(
            'gm2-login-widget',
            GM2_PLUGIN_URL . 'public/css/gm2-login-widget.css',
            [],
            GM2_VERSION
        );
        wp_register_script(
            'gm2-login-widget',
            GM2_PLUGIN_URL . 'public/js/gm2-login-widget.js',
            [ 'jquery' ],
            GM2_VERSION,
            true
        );

        if ( ( function_exists( 'is_product' ) && is_product() ) || $in_edit_mode ) {
            if (get_option('gm2_enable_quantity_discounts', '1') === '1') {
                wp_enqueue_style(
                    'gm2-qd-widget',
                    GM2_PLUGIN_URL . 'public/css/gm2-qd-widget.css',
                    [],
                    GM2_VERSION
                );
                wp_enqueue_script(
                    'gm2-qd-widget',
                    GM2_PLUGIN_URL . 'public/js/gm2-qd-widget.js',
                    [ 'jquery' ],
                    GM2_VERSION,
                    true
                );
            }
        }
    }

    public function add_tariff_fees($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            return;
        }

        $manager = new Gm2_Tariff_Manager();
        $tariffs = $manager->get_tariffs();

        if ($tariffs) {
            foreach ($tariffs as $tariff) {
                if ($tariff['status'] === 'enabled') {
                    $amount = $cart->get_subtotal() * ($tariff['percentage'] / 100);
                    $cart->add_fee($tariff['name'], $amount, false);
                }
            }
        }
    }
}
