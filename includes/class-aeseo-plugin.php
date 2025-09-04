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
        if (is_admin() || is_feed() || defined('REST_REQUEST')) {
            return;
        }

        require_once __DIR__ . '/class-aeseo-lcp-optimizer.php';
        AESEO_LCP_Optimizer::boot();
    }
}
