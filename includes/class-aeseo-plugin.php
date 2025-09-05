<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AESEO_Plugin')) {
    return;
}

/**
 * Loader for AESEO features.
 */
final class AESEO_Plugin {
    /**
     * Initialize the plugin components.
     */
    public static function init(): void {
        if (
            is_admin() ||
            is_feed() ||
            defined('REST_REQUEST') ||
            (function_exists('wp_doing_ajax') && wp_doing_ajax()) ||
            (function_exists('wp_is_json_request') && wp_is_json_request())
        ) {
            return;
        }

        require_once __DIR__ . '/class-aeseo-lcp-optimizer.php';

        add_action(
            'template_redirect',
            static function () {
                if (is_404()) {
                    return;
                }
                AESEO_LCP_Optimizer::boot();
            },
            0
        );
    }
}
