<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Public {

    public function run() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_tariff_fees']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('gm2-public-style', GM2_PLUGIN_URL . 'public/css/gm2-public.css');
        wp_enqueue_script('gm2-public-script', GM2_PLUGIN_URL . 'public/js/gm2-public.js', [], false, true);
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
