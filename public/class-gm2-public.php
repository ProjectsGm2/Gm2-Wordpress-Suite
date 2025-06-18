<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Public {

    public function run() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('gm2-public-style', GM2_PLUGIN_URL . 'public/css/gm2-public.css');
        wp_enqueue_script('gm2-public-script', GM2_PLUGIN_URL . 'public/js/gm2-public.js', [], false, true);
    }
}
