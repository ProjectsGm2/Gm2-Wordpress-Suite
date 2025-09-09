<?php
namespace Gm2\Font_Performance\Admin;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\Font_Performance_Admin')) {
    return;
}

class Font_Performance_Admin {
    private const OPTION_KEY = 'gm2seo_fonts';
    private static string $pagehook = '';

    /** Bootstrap hooks. */
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_menu', [__CLASS__, 'menu'], 99);
    }

    /** Register settings and fields. */
    public static function register(): void {
        register_setting('gm2_font_performance', self::OPTION_KEY, [__CLASS__, 'sanitize']);

        add_settings_section(
            'gm2_font_performance_main',
            __('Font Performance', 'gm2-wordpress-suite'),
            '__return_false',
            'gm2-fonts'
        );

        $fields = [
            'enabled'             => ['type' => 'checkbox', 'label' => __('Enable', 'gm2-wordpress-suite')],
            'inject_display_swap' => ['type' => 'checkbox', 'label' => __('Inject display=swap', 'gm2-wordpress-suite')],
            'google_url_rewrite'  => ['type' => 'checkbox', 'label' => __('Rewrite Google URLs', 'gm2-wordpress-suite')],
            'preconnect'          => ['type' => 'textarea', 'label' => __('Preconnect URLs (one per line)', 'gm2-wordpress-suite')],
            'preload'             => ['type' => 'textarea', 'label' => __('Preload URLs (one per line)', 'gm2-wordpress-suite')],
            'self_host'           => ['type' => 'checkbox', 'label' => __('Self-host fonts', 'gm2-wordpress-suite')],
            'families'            => ['type' => 'textarea', 'label' => __('Font families (one per line)', 'gm2-wordpress-suite')],
            'limit_variants'      => ['type' => 'checkbox', 'label' => __('Limit variants', 'gm2-wordpress-suite')],
            'system_fallback_css' => ['type' => 'checkbox', 'label' => __('System fallback CSS', 'gm2-wordpress-suite')],
            'cache_headers'       => ['type' => 'checkbox', 'label' => __('Cache headers', 'gm2-wordpress-suite')],
        ];

        foreach ($fields as $key => $args) {
            add_settings_field(
                $key,
                $args['label'],
                [__CLASS__, 'render_field'],
                'gm2-fonts',
                'gm2_font_performance_main',
                ['key' => $key, 'type' => $args['type']]
            );
        }
    }

    /** Sanitize submitted values. */
    public static function sanitize(array $input): array {
        $opts = get_option(self::OPTION_KEY, []);

        $opts['enabled']             = !empty($input['enabled']);
        $opts['inject_display_swap'] = !empty($input['inject_display_swap']);
        $opts['google_url_rewrite']  = !empty($input['google_url_rewrite']);
        $opts['self_host']           = !empty($input['self_host']);
        $opts['limit_variants']      = !empty($input['limit_variants']);
        $opts['system_fallback_css'] = !empty($input['system_fallback_css']);
        $opts['cache_headers']       = !empty($input['cache_headers']);

        $opts['preconnect'] = self::sanitize_lines($input['preconnect'] ?? '');
        $opts['preload']    = self::sanitize_lines($input['preload'] ?? '');
        $opts['families']   = self::sanitize_lines($input['families'] ?? '');

        return $opts;
    }

    /** Convert textarea lines to array. */
    private static function sanitize_lines(string $value): array {
        $lines = array_filter(array_map('trim', explode("\n", $value)));
        return array_values($lines);
    }

    /** Render checkbox or textarea field. */
    public static function render_field(array $args): void {
        $options = get_option(self::OPTION_KEY, []);
        $value   = $options[$args['key']] ?? '';
        switch ($args['type']) {
            case 'checkbox':
                printf(
                    '<input type="checkbox" name="%1$s[%2$s]" value="1" %3$s />',
                    esc_attr(self::OPTION_KEY),
                    esc_attr($args['key']),
                    checked($value, true, false)
                );
                break;
            case 'textarea':
                if (is_array($value)) {
                    $value = implode("\n", $value);
                }
                printf(
                    '<textarea name="%1$s[%2$s]" rows="5" cols="50">%3$s</textarea>',
                    esc_attr(self::OPTION_KEY),
                    esc_attr($args['key']),
                    esc_textarea($value)
                );
                break;
        }
    }

    /** Add submenu page. */
    public static function menu(): void {
        $parent = 'gm2-seo';
        self::$pagehook = add_submenu_page(
            $parent,
            __('Fonts', 'gm2-wordpress-suite'),
            __('Fonts', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-fonts',
            [__CLASS__, 'render']
        );

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** Enqueue assets only on this page. */
    public static function enqueue_assets(string $hook): void {
        if ($hook !== self::$pagehook) {
            return;
        }
        wp_enqueue_style(
            'gm2-seo-style',
            GM2_PLUGIN_URL . 'admin/css/gm2-seo.css',
            [],
            GM2_VERSION
        );
        wp_enqueue_script(
            'gm2-seo',
            GM2_PLUGIN_URL . 'admin/js/gm2-seo.js',
            ['jquery'],
            file_exists(GM2_PLUGIN_DIR . 'admin/js/gm2-seo.js') ? filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-seo.js') : GM2_VERSION,
            true
        );
    }

    /** Render settings page. */
    public static function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Font Performance', 'gm2-wordpress-suite') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('gm2_font_performance');
        do_settings_sections('gm2-fonts');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
