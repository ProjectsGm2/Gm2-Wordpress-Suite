<?php
/**
 * Differential serving of assets.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles differential asset serving.
 */
class AE_SEO_Diff_Serving {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
    }

    /**
     * Set up differential serving hooks.
     *
     * @return void
     */
    public function setup() {
        // Placeholder for differential serving logic.
    }
}
