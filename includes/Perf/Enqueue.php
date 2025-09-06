<?php
namespace Gm2\Perf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue performance bootstrap script.
 */
class Enqueue {
    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 15);
    }

    /**
     * Register and enqueue performance script.
     */
    public static function enqueue(): void {
        if (is_admin()) {
            return;
        }

        $file = GM2_PLUGIN_DIR . 'assets/js/perf/index.js';
        $src  = GM2_PLUGIN_URL . 'assets/js/perf/index.js';
        $ver  = file_exists($file) ? (string) filemtime($file) : GM2_VERSION;

        wp_register_script('ae-perf', $src, [], $ver, true);
        wp_script_add_data('ae-perf', 'type', 'module');
        wp_script_add_data('ae-perf', 'defer', true);
        wp_localize_script('ae-perf', 'AE_PERF_FLAGS', [
            'webWorker'    => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_webworker'), 'webWorker'),
            'longTasks'    => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_longtasks'), 'longTasks'),
            'noThrash'     => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_nothrash'), 'noThrash'),
            'passive'      => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_passive'), 'passive'),
            'passivePatch' => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_passive_patch'), 'passivePatch'),
            'domAudit'     => (bool) apply_filters('ae/perf/flag', get_option('ae_perf_domaudit'), 'domAudit'),
            'isAdmin'      => current_user_can('manage_options'),
            'domAuditThresholds' => ['maxElements'=>600,'maxDepth'=>12],
        ]);
        wp_enqueue_script('ae-perf');
    }
}
