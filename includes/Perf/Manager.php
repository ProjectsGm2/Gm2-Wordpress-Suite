<?php
namespace Gm2\Perf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap performance helpers.
 */
class Manager {
    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ __CLASS__, 'enqueue' ]);
    }

    /**
     * Enqueue performance bootstrap script.
     */
    public static function enqueue(): void {
        if (is_admin()) {
            return;
        }
        $file = GM2_PLUGIN_DIR . 'assets/js/perf/index.js';
        $src  = GM2_PLUGIN_URL . 'assets/js/perf/index.js';
        $ver  = file_exists($file) ? (string) filemtime($file) : GM2_VERSION;
        wp_enqueue_script('ae-perf', $src, ['wp-hooks'], $ver, true);
        wp_script_add_data('ae-perf', 'type', 'module');
        wp_script_add_data('ae-perf', 'defer', true);
        wp_localize_script('ae-perf', 'AE_PERF_FLAGS', self::get_flags());
    }

    /**
     * Retrieve flags.
     *
     * @return array<string,bool> Performance flags keyed by feature.
     */
    private static function get_flags(): array {
        $map = [
            'webWorker'         => 'ae_perf_webworker',
            'worker'            => 'ae_perf_webworker', // Legacy key for compatibility.
            'longTasks'         => 'ae_perf_longtasks',
            'noThrash'          => 'ae_perf_nothrash',
            'passive_listeners' => 'ae_perf_passive',
            'dom_audit'         => 'ae_perf_domaudit',
        ];
        $flags = [];
        foreach ($map as $feature => $option) {
            $enabled = get_option($option, '0') === '1';
            /**
             * Filter a performance flag.
             *
             * @param bool   $enabled Whether the feature is enabled.
             * @param string $feature Feature identifier.
             */
            $flags[$feature] = (bool) apply_filters('ae/perf/flag', $enabled, $feature);
        }
        // Include flag indicating whether a user is logged in.
        $flags['isAdmin'] = (bool) is_user_logged_in();

        /**
         * Filter whether the passive listeners patch is allowed.
         *
         * @param bool $allow_patch Whether the passive listeners patch is allowed.
         */
        $allow_patch           = get_option('ae_perf_passive_patch', '0') === '1';
        $flags['passivePatch'] = $flags['passive_listeners'] && $allow_patch && apply_filters('ae/perf/passive_allow_patch', true);

        return $flags;
    }
}

Manager::init();
