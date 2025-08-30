<?php
/**
 * Defer JavaScript loading.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds defer attributes to scripts.
 */
class AE_SEO_Defer_JS {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
    }

    /**
     * Set up hooks for deferring scripts.
     *
     * @return void
     */
    public function setup() {
        add_filter('script_loader_tag', [ $this, 'add_defer_attribute' ], 10, 2);
    }

    /**
     * Add the defer attribute to script tags.
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @return string
     */
    public function add_defer_attribute($tag, $handle) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WordPress filter signature.
        // Placeholder for defer logic.
        return $tag;
    }
}
