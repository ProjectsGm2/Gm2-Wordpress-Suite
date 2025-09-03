<?php
/**
 * Differential serving for main front-end script.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

use Gm2\AE_SEO_JS_Manager;

/**
 * Handle loading of modern/legacy bundles and polyfills.
 */
class AE_SEO_Main_Diff_Serving {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
        add_filter('script_loader_tag', [ $this, 'script_loader_tag' ], 20, 3);
    }

    /**
     * Register and enqueue scripts.
     *
     * @return void
     */
    public function setup() {
        $load_legacy = get_option('ae_js_nomodule_legacy', '0') === '1';

        // Register scripts.
        ae_seo_register_asset('ae-main-modern', 'ae-main.modern.js');

        if ($load_legacy) {
            ae_seo_register_asset('ae-main-legacy', 'ae-main.legacy.js');
        }

        // Conditionally enqueue polyfills.
        if (ae_seo_needs_polyfills()) {
            ae_seo_register_asset('ae-polyfills', 'polyfills.js');
            wp_enqueue_script('ae-polyfills');
            AE_SEO_JS_Manager::$polyfills++;
        }

        // Enqueue main scripts.
        wp_enqueue_script('ae-main-modern');
        if ($load_legacy) {
            wp_enqueue_script('ae-main-legacy');
        }
    }

    /**
     * Add module/nomodule attributes.
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     * @param string $src    Script source.
     * @return string
     */
    public function script_loader_tag($tag, $handle, $src) {
        if ($handle === 'ae-main-modern') {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        if ($handle === 'ae-main-legacy') {
            return str_replace('<script ', '<script nomodule ', $tag);
        }
        return $tag;
    }
}

if (!function_exists('ae_seo_needs_polyfills')) {
    /**
     * Determine if polyfills are required for the current browser.
     *
     * Uses a cookie set via feature detection to avoid repeated checks.
     *
     * @return bool
     */
    function ae_seo_needs_polyfills(): bool {
        if (isset($_COOKIE['ae_js_polyfills'])) {
            return $_COOKIE['ae_js_polyfills'] === '1';
        }

        $inline = "(function(){var n=!('IntersectionObserver' in window)||!('fetch' in window)||!('Promise' in window)||!('classList' in document.createElement('div'));document.cookie='ae_js_polyfills='+(n?1:0)+';path=/';})();";
        wp_add_inline_script('ae-main-modern', $inline, 'before');
        return true;
    }
}

// Bootstrap.
new AE_SEO_Main_Diff_Serving();
