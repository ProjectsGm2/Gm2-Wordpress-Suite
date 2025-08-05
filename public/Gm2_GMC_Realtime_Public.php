<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end integration for real-time Google Merchant Centre data.
 */
class Gm2_GMC_Realtime_Public {
    /**
     * Bootstraps front-end scripts.
     */
    public function run() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_script']);
    }

    /**
     * Enqueues the polling script and passes config.
     */
    public function enqueue_script() {
        wp_enqueue_script(
            'gm2-gmc-realtime',
            GM2_PLUGIN_URL . 'public/js/gm2-gmc-realtime.js',
            [],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-gmc-realtime',
            'gm2GmcRealtime',
            [
                'url'    => rest_url('gm2/v1/gmc/realtime'),
                'nonce'  => wp_create_nonce('wp_rest'),
                'fields' => Gm2_GMC_Realtime::get_fields(),
            ]
        );
    }
}
