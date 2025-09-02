<?php
/**
 * Render optimizer bootstrap.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-ae-seo-critical-css.php';

/**
 * Bootstraps render optimization features.
 */
class AE_SEO_Render_Optimizer {
    /**
     * Option defaults.
     *
     * @var array
     */
    private $defaults = [
        AE_SEO_Critical_CSS::OPTION_ENABLE       => '0',
        AE_SEO_Critical_CSS::OPTION_STRATEGY     => 'per_home_archive_single',
        AE_SEO_Critical_CSS::OPTION_CSS_MAP      => [],
        AE_SEO_Critical_CSS::OPTION_ASYNC_METHOD => 'preload_onload',
        AE_SEO_Critical_CSS::OPTION_EXCLUSIONS   => [],
        'ae_seo_ro_enable_diff_serving'          => '1',
        'ae_seo_ro_enable_defer_js'              => '0',
        'ae_seo_ro_enable_combine_css'          => '0',
        'ae_seo_ro_enable_combine_js'           => '0',
        'ae_seo_ro_defer_allow_handles'          => '',
        'ae_seo_ro_defer_deny_handles'           => '',
        'ae_seo_ro_defer_allow_domains'          => '',
        'ae_seo_ro_defer_deny_domains'           => '',
        'ae_seo_ro_defer_respect_in_footer'      => '1',
        'ae_seo_ro_defer_preserve_jquery'        => '1',
    ];
    /**
     * Names of detected conflicting plugins.
     *
     * @var array
     */
    private $conflicts = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_option_defaults();
        add_action('init', [ $this, 'maybe_bootstrap' ]);
    }

    /**
     * Register default options if they do not exist.
     *
     * @return void
     */
    private function register_option_defaults() {
        foreach ($this->defaults as $option => $value) {
            add_option($option, $value);
        }
    }

    /**
     * Retrieve an option value.
     *
     * @param string $option  Option name.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get_option($option, $default = false) {
        return get_option($option, $default);
    }

    /**
     * Update an option value.
     *
     * @param string $option Option name.
     * @param mixed  $value  Option value.
     * @return bool
     */
    public static function update_option($option, $value) {
        return update_option($option, $value);
    }

    /**
     * Add an option value.
     *
     * @param string $option Option name.
     * @param mixed  $value  Option value.
     * @return bool
     */
    public static function add_option($option, $value) {
        return add_option($option, $value);
    }

    /**
     * Delete an option.
     *
     * @param string $option Option name.
     * @return bool
     */
    public static function delete_option($option) {
        return delete_option($option);
    }

    /**
     * Check context and load features when appropriate.
     *
     * @return void
     */
    public function maybe_bootstrap() {
        if ($this->should_skip() || $this->has_conflicts()) {
            if (!empty($this->conflicts)) {
                $this->disable_features();
                add_action('admin_notices', [ $this, 'conflict_notice' ]);
            }
            return;
        }

        if (
            self::get_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '0') !== '1' &&
            get_option('ae_seo_ro_enable_defer_js', '0') !== '1' &&
            get_option('ae_seo_ro_enable_diff_serving', '0') !== '1' &&
            get_option('ae_seo_ro_enable_combine_css', '0') !== '1' &&
            get_option('ae_seo_ro_enable_combine_js', '0') !== '1'
        ) {
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

        $plugins = [
            'wp-rocket/wp-rocket.php'       => 'WP Rocket',
            'autoptimize/autoptimize.php'   => 'Autoptimize',
            'perfmatters/perfmatters.php'   => 'Perfmatters',
        ];

        foreach ($plugins as $file => $name) {
            if (is_plugin_active($file)) {
                $this->conflicts[] = $name;
            }
        }

        return !empty($this->conflicts);
    }

    /**
     * Disable overlapping optimization features.
     *
     * @return void
     */
    private function disable_features() {
        $options = [
            AE_SEO_Critical_CSS::OPTION_ENABLE,
            'ae_seo_ro_enable_defer_js',
            'ae_seo_ro_enable_diff_serving',
            'ae_seo_ro_enable_combine_css',
            'ae_seo_ro_enable_combine_js',
        ];

        foreach ($options as $option) {
            if (self::get_option($option, '0') !== '0') {
                self::update_option($option, '0');
            }
        }
    }

    /**
     * Display admin notice about detected conflicts.
     *
     * @return void
     */
    public function conflict_notice() {
        if (!current_user_can('manage_options') || empty($this->conflicts)) {
            return;
        }

        $names = implode(', ', $this->conflicts);
        echo '<div class="notice notice-warning"><p>' .
            esc_html(
                sprintf(
                    /* translators: list of conflicting plugin names */
                    __('Render optimizer features disabled due to active plugin(s): %s.', 'gm2-wordpress-suite'),
                    $names
                )
            ) .
            '</p></div>';
    }

    /**
     * Load feature classes when enabled.
     *
     * @return void
     */
    private function load_features() {
        if (self::get_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-critical-css.php';
            new AE_SEO_Critical_CSS();
        }

        if (get_option('ae_seo_ro_enable_defer_js', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-defer-js.php';
            new AE_SEO_Defer_JS();
        }

        if (get_option('ae_seo_ro_enable_diff_serving', '0') === '1') {
            require_once __DIR__ . '/class-ae-seo-diff-serving.php';
            new AE_SEO_Diff_Serving();
        }

        if (
            get_option('ae_seo_ro_enable_combine_css', '0') === '1' ||
            get_option('ae_seo_ro_enable_combine_js', '0') === '1'
        ) {
            require_once __DIR__ . '/class-ae-seo-combine-minify.php';
            new AE_SEO_Combine_Minify();
        }
    }
}

new AE_SEO_Render_Optimizer();
