<?php
/**
 * Render optimizer bootstrap.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps render optimization features.
 */
class AE_SEO_Render_Optimizer {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('init', [ $this, 'maybe_bootstrap' ]);
    }

    /**
     * Check context and load features when appropriate.
     *
     * @return void
     */
    public function maybe_bootstrap() {
        if ($this->should_skip() || $this->has_conflicts()) {
            return;
        }

        $this->load_features();
    }

    /**
     * Determine if optimization should be skipped.
     *
     * @return bool
     */
    private function should_skip() {
        if (is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return true;
        }

        return function_exists('is_preview') && is_preview();
    }

    /**
     * Check for conflicting optimization plugins.
     *
     * @return bool
     */
    private function has_conflicts() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $targets = [
            'WP Rocket',
            'Autoptimize',
            'Perfmatters',
        ];

        $plugins = get_plugins();
        foreach ($plugins as $file => $data) {
            $name = isset($data['Name']) ? $data['Name'] : '';
            if (in_array($name, $targets, true) && is_plugin_active($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load feature classes when enabled.
     *
     * @return void
     */
    private function load_features() {
        if (get_option('ae_seo_critical_css', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-critical-css.php';
            new AE_SEO_Critical_CSS();
        }

        if (get_option('ae_seo_defer_js', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-defer-js.php';
            new AE_SEO_Defer_JS();
        }

        if (get_option('ae_seo_diff_serving', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-diff-serving.php';
            new AE_SEO_Diff_Serving();
        }

        if (get_option('ae_seo_combine_minify', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-combine-minify.php';
            new AE_SEO_Combine_Minify();
        }
    }
}

new AE_SEO_Render_Optimizer();
