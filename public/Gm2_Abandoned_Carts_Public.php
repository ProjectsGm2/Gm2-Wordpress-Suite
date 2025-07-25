<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Public {
    public function run() {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
    }

    public function enqueue_scripts() {
        // Placeholder for guest email capture or exit intent
    }
}
