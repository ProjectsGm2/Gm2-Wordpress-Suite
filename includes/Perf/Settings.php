<?php
namespace Gm2\Perf;

use Gm2\Admin\Performance\SettingsPage as PerformanceSettingsPage;
use Gm2\Performance\AutoloadManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register performance settings.
 */
class Settings {
    /**
     * Bootstrap hooks.
     */
    public static function init(): void {
        add_action('admin_init', [ __CLASS__, 'register' ]);
        add_action('admin_menu', [ __CLASS__, 'menu' ], 99);
    }

    /**
     * Register options and fields.
     */
    public static function register(): void {
        foreach (self::get_flag_options() as $option => $label) {
            add_option($option, '0', '', AutoloadManager::get_autoload_flag($option));
            register_setting('gm2_performance', $option, [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => '0',
            ]);
        }

        foreach (self::get_cache_options() as $option => $config) {
            $default = $config['default'];
            add_option($option, $default, '', AutoloadManager::get_autoload_flag($option));
            register_setting('gm2_performance', $option, [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => $default,
            ]);
        }

        add_settings_section(
            'gm2_performance_main',
            __( 'Performance', 'gm2-wordpress-suite' ),
            '__return_false',
            'gm2-perf'
        );

        foreach (self::get_flag_options() as $option => $label) {
            add_settings_field(
                $option,
                $label,
                [ __CLASS__, 'render_checkbox' ],
                'gm2-perf',
                'gm2_performance_main',
                [ 'option' => $option ]
            );
        }
    }

    /**
     * Add a menu page or attach to existing settings page.
     */
    public static function menu(): void {
        $parent = 'gm2-seo';
        if (self::menu_exists($parent)) {
            add_submenu_page(
                $parent,
                __( 'Performance', 'gm2-wordpress-suite' ),
                __( 'Performance', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-perf',
                [ __CLASS__, 'render' ]
            );
        } else {
            add_options_page(
                __( 'Performance', 'gm2-wordpress-suite' ),
                __( 'Performance', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-perf',
                [ __CLASS__, 'render' ]
            );
        }
    }

    /**
     * Check if a menu slug exists.
     */
    private static function menu_exists(string $slug): bool {
        global $menu;
        foreach ((array) $menu as $item) {
            if ($item[2] === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render checkbox input.
     */
    public static function render_checkbox(array $args): void {
        $option = $args['option'];
        $value  = get_option($option, '0');
        echo '<input type="checkbox" name="' . esc_attr($option) . '" value="1" ' . checked($value, '1', false) . ' />';
    }

    /**
     * Sanitize checkbox value.
     */
    public static function sanitize_checkbox($value): string {
        return $value === '1' ? '1' : '0';
    }

    /**
     * Render settings page.
     */
    public static function render(): void {
        PerformanceSettingsPage::render(self::get_flag_options(), self::get_cache_options());
    }

    /**
     * Retrieve flag options keyed by option name.
     *
     * @return array<string, string>
     */
    public static function get_flag_options(): array {
        return [
            'ae_perf_webworker'     => __( 'Enable Web Worker offloading', 'gm2-wordpress-suite' ),
            'ae_perf_longtasks'     => __( 'Break up long tasks', 'gm2-wordpress-suite' ),
            'ae_perf_nothrash'      => __( 'Batch DOM reads & writes', 'gm2-wordpress-suite' ),
            'ae_perf_passive'       => __( 'Passive scroll/touch listeners', 'gm2-wordpress-suite' ),
            'ae_perf_passive_patch' => __( 'Allow passive listeners patch', 'gm2-wordpress-suite' ),
            'ae_perf_domaudit'      => __( 'DOM size audit', 'gm2-wordpress-suite' ),
        ];
    }

    /**
     * Query cache toggle definitions.
     *
     * @return array<string, array<string, string>>
     */
    public static function get_cache_options(): array {
        return [
            'gm2_perf_query_cache_enabled' => [
                'label'       => __( 'Deterministic query cache', 'gm2-wordpress-suite' ),
                'description' => __( 'Disable to bypass cached WP_Query payloads, forcing live database reads.', 'gm2-wordpress-suite' ),
                'default'     => '1',
            ],
            'gm2_perf_query_cache_use_transients' => [
                'label'       => __( 'Force transient fallback', 'gm2-wordpress-suite' ),
                'description' => __( 'When enabled cached payloads persist via transients for hosts without persistent object caches.', 'gm2-wordpress-suite' ),
                'default'     => '0',
            ],
        ];
    }
}
