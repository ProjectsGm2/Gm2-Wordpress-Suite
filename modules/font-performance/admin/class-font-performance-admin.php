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
        add_action('wp_ajax_gm2_detect_font_variants', [__CLASS__, 'ajax_detect_variants']);
        add_action('wp_ajax_gm2_font_size_diff', [__CLASS__, 'ajax_font_size_diff']);
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
            'preload'             => ['type' => 'textarea', 'label' => __('Preload font URLs (.woff2, one per line)', 'gm2-wordpress-suite')],
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

        add_settings_section(
            'gm2_font_performance_variants',
            __('Detected Variants', 'gm2-wordpress-suite'),
            '__return_false',
            'gm2-fonts'
        );

        add_settings_field(
            'variant_suggestions',
            __('Variants', 'gm2-wordpress-suite'),
            [__CLASS__, 'render_field'],
            'gm2-fonts',
            'gm2_font_performance_variants',
            ['key' => 'variant_suggestions', 'type' => 'variants']
        );
    }

    /** Sanitize submitted values. */
    public static function sanitize(array $input): array {
        $opts = \Gm2\Font_Performance\Font_Performance::get_settings();

        $opts['enabled']             = !empty($input['enabled']);
        $opts['inject_display_swap'] = !empty($input['inject_display_swap']);
        $opts['google_url_rewrite']  = !empty($input['google_url_rewrite']);
        $opts['self_host']           = !empty($input['self_host']);
        $opts['limit_variants']      = !empty($input['limit_variants']);
        $opts['system_fallback_css'] = !empty($input['system_fallback_css']);
        $opts['cache_headers']       = !empty($input['cache_headers']);

        $opts['preconnect'] = self::sanitize_lines($input['preconnect'] ?? '');
        $opts['preload']    = self::sanitize_lines($input['preload'] ?? '');
        $opts['families'] = self::sanitize_lines($input['families'] ?? '');

        $variants = $input['variant_suggestions'] ?? [];
        if (!is_array($variants)) {
            $variants = [];
        }
        $opts['variant_suggestions'] = array_values(
            array_map('sanitize_text_field', $variants)
        );

        return $opts;
    }

    /** Convert textarea lines to array. */
    private static function sanitize_lines(string $value): array {
        $lines = array_filter(array_map('trim', explode("\n", $value)));
        return array_values($lines);
    }

    /** Render checkbox or textarea field. */
    public static function render_field(array $args): void {
        $options = \Gm2\Font_Performance\Font_Performance::get_settings();
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
            case 'variants':
                echo '<p><button type="button" class="button" id="gm2-detect-variants">' . esc_html__('Detect Font Variants', 'gm2-wordpress-suite') . '</button></p>';
                echo '<div id="gm2-variant-suggestions">';
                if (!empty($value) && is_array($value)) {
                    foreach ($value as $variant) {
                        $id = 'gm2-variant-' . esc_attr(sanitize_title($variant));
                        printf(
                            '<div><label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s][]" value="%4$s" checked="checked" /> %4$s</label></div>',
                            esc_attr($id),
                            esc_attr(self::OPTION_KEY),
                            esc_attr($args['key']),
                            esc_html($variant)
                        );
                    }
                }
                echo '</div>';
                echo '<p id="gm2-variant-savings"></p>';
                break;
        }
    }

    /** AJAX handler to detect variants. */
    public static function ajax_detect_variants(): void {
        check_ajax_referer('gm2_font_variants', 'nonce');
        $variants = \Gm2\Font_Performance\Font_Performance::detect_font_variants();
        wp_send_json_success($variants);
    }

    /** AJAX handler to compute font size savings. */
    public static function ajax_font_size_diff(): void {
        check_ajax_referer('gm2_font_variants', 'nonce');
        $selected = $_POST['variants'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_map('sanitize_text_field', $selected);
        $sizes    = \Gm2\Font_Performance\Font_Performance::compute_variant_savings($selected);
        $data     = [
            'total'     => round($sizes['total'] / 1024, 2),
            'selected'  => round($sizes['allowed'] / 1024, 2),
            'reduction' => round($sizes['reduction'] / 1024, 2),
        ];
        wp_send_json_success($data);
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

        wp_enqueue_style(
            'gm2-font-performance-admin',
            plugin_dir_url(__FILE__) . '../assets/admin.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/admin.css')
        );

        wp_enqueue_script(
            'gm2-font-performance-admin',
            plugin_dir_url(__FILE__) . '../assets/admin.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . '../assets/admin.js'),
            true
        );

        wp_localize_script(
            'gm2-font-performance-admin',
            'GM2FontPerf',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_font_variants'),
                'selected' => \Gm2\Font_Performance\Font_Performance::get_settings()['variant_suggestions'] ?? [],
            ]
        );
    }

    /** Render settings page. */
    public static function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Font Performance', 'gm2-wordpress-suite') . '</h1>';

        if (isset($_GET['gm2_self_host'])) {
            $type  = $_GET['gm2_self_host'] === 'success' ? 'success' : 'error';
            $class = 'notice notice-' . $type;
            $msg   = $type === 'success'
                ? __('Fonts were self-hosted.', 'gm2-wordpress-suite')
                : __('Font self-hosting failed.', 'gm2-wordpress-suite');
            printf('<div class="%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($class), esc_html($msg));
        }

        echo '<form method="post" action="options.php">';
        settings_fields('gm2_font_performance');
        do_settings_sections('gm2-fonts');
        submit_button();
        echo '</form>';

        echo '<h2>' . esc_html__('Server Font Caching', 'gm2-wordpress-suite') . '</h2>';
        echo '<p>' . esc_html__('Configure your web server to cache fonts for one year. Add one of the snippets below to your server configuration.', 'gm2-wordpress-suite') . '</p>';
        echo '<h3>' . esc_html__('Apache', 'gm2-wordpress-suite') . '</h3>';
        echo '<pre><code>' . esc_html("<FilesMatch \"\\.(woff2?|ttf|otf)\$\">\n  Header set Cache-Control \"public, max-age=31536000, immutable\"\n</FilesMatch>") . '</code></pre>';
        echo '<h3>' . esc_html__('Nginx', 'gm2-wordpress-suite') . '</h3>';
        echo '<pre><code>' . esc_html("location ~* \\.(woff2?|ttf|otf)\$ {\n  add_header Cache-Control \"public, max-age=31536000, immutable\";\n}") . '</code></pre>';
        echo '<p>' . esc_html__('If server access is not available, enable “Cache headers” above to serve fonts through the plugin with the same caching headers.', 'gm2-wordpress-suite') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gm2_self_host_fonts', '_wpnonce_gm2_self_host_fonts');
        echo '<input type="hidden" name="action" value="gm2_self_host_fonts" />';
        submit_button(__('Self-host Google Fonts', 'gm2-wordpress-suite'), 'secondary');
        echo '</form>';

        echo '</div>';
    }
}
