<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional tiny utility CSS.
 */
class AE_Utility_CSS {
    /**
     * Hook into enqueue.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 1);
    }

    /**
     * Conditionally enqueue the utility stylesheet.
     */
    public static function enqueue(): void {
        $settings = get_option('ae_css_settings', []);
        $enabled = isset($settings['utility_css']) && $settings['utility_css'] === '1';
        $enabled = apply_filters('ae/css/utility_enabled', $enabled);
        if (!$enabled) {
            return;
        }

        $file = GM2_PLUGIN_DIR . 'assets/css/ae-utility.css';
        $src  = GM2_PLUGIN_URL . 'assets/css/ae-utility.css';
        $ver  = file_exists($file) ? (string) filemtime($file) : GM2_VERSION;

        wp_register_style('ae-utility', $src, [], $ver);
        wp_enqueue_style('ae-utility');
    }
}
