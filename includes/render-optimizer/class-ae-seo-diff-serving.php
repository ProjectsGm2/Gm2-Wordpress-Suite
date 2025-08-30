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
        add_filter('script_loader_tag', [ $this, 'script_loader_tag' ], 20, 3);

        $modern = GM2_PLUGIN_URL . 'assets/dist/optimizer-modern.js';
        $legacy = GM2_PLUGIN_URL . 'assets/dist/optimizer-legacy.js';
        $ver    = defined('GM2_VERSION') ? GM2_VERSION : false;

        wp_enqueue_script('ae-seo-optimizer-modern', $modern, [], $ver, true);
        wp_enqueue_script('ae-seo-optimizer-legacy', $legacy, [], $ver, true);
    }

    /**
     * Add module and nomodule attributes to optimizer scripts.
     *
     * @param string $tag    The script tag.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     *
     * @return string
     */
    public function script_loader_tag($tag, $handle, $src) {
        if ($handle === 'ae-seo-optimizer-modern') {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        } elseif ($handle === 'ae-seo-optimizer-legacy') {
            $tag = str_replace('<script ', '<script nomodule ', $tag);
        }
        return $tag;
    }
}
