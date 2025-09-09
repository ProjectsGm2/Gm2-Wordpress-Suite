<?php
namespace Gm2\Font_Performance;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\Font_Performance')) {
    return;
}

class Font_Performance {
    private const OPTION_KEY = 'gm2seo_fonts';

    /**
     * Selected plugin directories to inspect for font usage. Only these
     * directories are scanned to avoid excessive filesystem traversal.
     *
     * @var string[]
     */
    private const PLUGIN_DIRS = [
        'elementor',
        'elementor-pro',
        'woocommerce',
        'contact-form-7',
        'seo-by-rank-math',
    ];

    private static array $defaults = [
        'enabled'             => true,
        'inject_display_swap' => true,
        'google_url_rewrite'  => true,
        'preconnect'          => ['https://fonts.gstatic.com'],
        'preload'             => [],
        'self_host'           => false,
        'families'            => [],
        'limit_variants'      => true,
        'variant_suggestions' => [],
        'system_fallback_css' => true,
        'cache_headers'       => true,
    ];

    private static array $options = [];
    private static bool $hooks_added = false;

    /** Register init hook. */
    public static function init(): void {
        add_action('init', [__CLASS__, 'bootstrap'], 20);
        if (is_admin()) {
            require_once __DIR__ . '/admin/class-font-performance-admin.php';
            Admin\Font_Performance_Admin::init();
            add_action('admin_post_gm2_self_host_fonts', [__CLASS__, 'self_host_fonts']);
        }
    }

    /** Load options and set up hooks. */
    public static function bootstrap(): void {
        self::get_settings();
        if (!empty(self::$options['enabled'])) {
            self::add_hooks();
        } else {
            self::remove_hooks();
        }
    }

    /** Retrieve plugin options respecting multisite. */
    private static function get_options(): array {
        $fn   = is_multisite() ? 'get_site_option' : 'get_option';
        $opts = $fn(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        return wp_parse_args($opts, self::$defaults);
    }

    /** Wrapper to access settings. */
    public static function get_settings(): array {
        if (empty(self::$options)) {
            self::$options = self::get_options();
        }
        return self::$options;
    }

    /**
     * Detect unique font-weight and font-style combinations used across
     * theme and selected plugin stylesheets.
     */
    public static function detect_font_variants(): array {
        $dirs   = [];
        $themes = [get_template_directory(), get_stylesheet_directory()];
        foreach (array_unique(array_filter($themes)) as $dir) {
            if (is_dir($dir)) {
                $dirs[] = $dir;
            }
        }

        if (defined('WP_PLUGIN_DIR')) {
            $plugin_slugs = apply_filters('gm2_font_variant_plugin_dirs', self::PLUGIN_DIRS);
            foreach ((array) $plugin_slugs as $slug) {
                $path = rtrim(WP_PLUGIN_DIR, '/\\') . '/' . $slug;
                if (is_dir($path)) {
                    $dirs[] = $path;
                }
            }
        }

        $variants = [];
        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'css') {
                    continue;
                }
                $css = @file_get_contents($file->getPathname());
                if ($css === false) {
                    continue;
                }

                preg_match_all('/{[^}]*}/', $css, $blocks);
                foreach ($blocks[0] as $block) {
                    $weight = null;
                    $style  = null;

                    if (preg_match('/font-weight\s*:\s*(\d{3}|bold|normal|bolder|lighter)/i', $block, $m)) {
                        $w = strtolower($m[1]);
                        switch ($w) {
                            case 'bold':
                                $weight = '700';
                                break;
                            case 'normal':
                                $weight = '400';
                                break;
                            case 'bolder':
                                $weight = '700';
                                break;
                            case 'lighter':
                                $weight = '300';
                                break;
                            default:
                                $weight = $w;
                        }
                    }

                    if (preg_match('/font-style\s*:\s*(normal|italic|oblique)/i', $block, $m)) {
                        $style = strtolower($m[1]);
                    }

                    if (!$weight && !$style && preg_match('/font\s*:\s*([^;]+);/i', $block, $m)) {
                        $tokens = preg_split('/\s+/', strtolower($m[1]));
                        foreach ($tokens as $token) {
                            if (!$style && in_array($token, ['normal', 'italic', 'oblique'], true)) {
                                $style = $token;
                            } elseif (!$weight && preg_match('/^(\d{3})$/', $token)) {
                                $weight = $token;
                            } elseif (!$weight && $token === 'bold') {
                                $weight = '700';
                            } elseif (!$weight && $token === 'normal') {
                                $weight = '400';
                            }
                        }
                    }

                    if ($weight || $style) {
                        $weight = $weight ?: '400';
                        $style  = $style ?: 'normal';
                        $variants[$weight . ' ' . $style] = true;
                    }
                }
            }
        }

        ksort($variants, SORT_NATURAL);
        return array_keys($variants);
    }

    /**
     * Compute total font size and savings for selected variants.
     */
    public static function compute_variant_savings(array $selected): array {
        $uploads  = wp_upload_dir();
        $base_dir = trailingslashit($uploads['basedir']) . 'gm2seo-fonts/';

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        $totals = ['total' => 0, 'allowed' => 0, 'reduction' => 0];
        if (!$wp_filesystem || !$wp_filesystem->is_dir($base_dir)) {
            return $totals;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base_dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'woff2') {
                continue;
            }
            $path = $file->getPathname();
            $size = (int) $wp_filesystem->size($path);
            $totals['total'] += $size;

            $name = $file->getBasename('.woff2');
            if (preg_match('/-(\d{3})-(normal|italic|oblique)$/i', $name, $m)) {
                $variant = strtolower($m[1] . ' ' . $m[2]);
            } else {
                $variant = '400 normal';
            }
            if (in_array($variant, $selected, true)) {
                $totals['allowed'] += $size;
            }
        }

        $totals['reduction'] = $totals['total'] - $totals['allowed'];
        return $totals;
    }

    /** Add hooks and filters. */
    private static function add_hooks(): void {
        if (self::$hooks_added) {
            return;
        }
        if (!empty(self::$options['google_url_rewrite'])) {
            add_filter('style_loader_src', [__CLASS__, 'rewrite_google_url'], 9, 2);
        }
        if (!empty(self::$options['inject_display_swap'])) {
            add_filter('style_loader_src', [__CLASS__, 'inject_display_swap'], 10, 2);
            add_action('wp_head', [__CLASS__, 'inline_font_display'], 99);
        }
        if (!empty(self::$options['preconnect'])) {
            add_action('wp_head', [__CLASS__, 'preconnect_links']);
        }
        if (!empty(self::$options['preload'])) {
            add_action('wp_head', [__CLASS__, 'preload_links']);
        }
        if (!empty(self::$options['system_fallback_css'])) {
            add_action('wp_head', [__CLASS__, 'fallback_css']);
        }
        if (!empty(self::$options['cache_headers'])) {
            add_action('rest_api_init', [__CLASS__, 'register_font_route']);
            add_filter('style_loader_src', [__CLASS__, 'rewrite_font_src']);
        }
        if (!empty(self::$options['self_host']) && class_exists('Gm2\\AE_SEO_Font_Manager')) {
            \Gm2\AE_SEO_Font_Manager::init();
        }
        self::$hooks_added = true;
    }

    /** Remove hooks when disabled. */
    private static function remove_hooks(): void {
        if (!self::$hooks_added) {
            return;
        }
        remove_filter('style_loader_src', [__CLASS__, 'rewrite_google_url'], 9);
        remove_filter('style_loader_src', [__CLASS__, 'inject_display_swap'], 10);
        remove_action('wp_head', [__CLASS__, 'inline_font_display'], 99);
        remove_action('wp_head', [__CLASS__, 'preconnect_links']);
        remove_action('wp_head', [__CLASS__, 'preload_links']);
        remove_action('wp_head', [__CLASS__, 'fallback_css']);
        remove_action('rest_api_init', [__CLASS__, 'register_font_route']);
        remove_filter('style_loader_src', [__CLASS__, 'rewrite_font_src']);
        if (class_exists('Gm2\\AE_SEO_Font_Manager')) {
            \Gm2\AE_SEO_Font_Manager::disable();
        }
        self::$hooks_added = false;
    }

    /**
     * Append display=swap parameter to Google Font URLs and ensure query params are unique.
     * Adds subset=latin when google_url_rewrite is enabled.
     */
    public static function inject_display_swap(string $src, string $handle): string {
        if (empty(self::$options['enabled']) || !str_contains($src, 'fonts.googleapis.com')) {
            return $src;
        }

        $parts  = parse_url($src);
        $params = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        if (!isset($params['display'])) {
            $params['display'] = 'swap';
        }

        if (!empty(self::$options['google_url_rewrite']) && !isset($params['subset'])) {
            $params['subset'] = 'latin';
        }

        $parts['query'] = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $scheme = '';
        if (isset($parts['scheme'])) {
            $scheme = $parts['scheme'] . '://';
        } elseif (0 === strpos($src, '//')) {
            $scheme = '//';
        }

        $src = $scheme . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        if (!empty($parts['query'])) {
            $src .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $src .= '#' . $parts['fragment'];
        }

        return $src;
    }

    /**
     * Scan enqueued styles for @font-face blocks missing font-display and output overrides.
     */
    public static function inline_font_display(): void {
        if (empty(self::$options['enabled']) || empty(self::$options['inject_display_swap'])) {
            return;
        }

        global $wp_styles;
        if (!($wp_styles instanceof \WP_Styles)) {
            return;
        }

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $families  = [];

        foreach ((array) $wp_styles->queue as $handle) {
            $style = $wp_styles->registered[ $handle ] ?? null;
            if (!$style || empty($style->src)) {
                continue;
            }

            $src = $style->src;
            if (!preg_match('#^(https?:)?//#', $src) && isset($wp_styles->base_url)) {
                if (0 !== strpos($src, '/')) {
                    $src = $wp_styles->base_url . $src;
                }
            }

            $parts = wp_parse_url($src);
            if (!empty($parts['host']) && $parts['host'] !== $home_host) {
                continue;
            }
            $path = $parts['path'] ?? '';
            if (!$path) {
                continue;
            }
            $file = ABSPATH . ltrim($path, '/');
            if (!is_readable($file)) {
                continue;
            }
            $css = file_get_contents($file);
            if ($css === false) {
                continue;
            }

            preg_match_all('/@font-face\s*{[^}]*}/i', $css, $blocks);
            foreach ($blocks[0] as $block) {
                if (stripos($block, 'font-display') !== false) {
                    continue;
                }
                if (preg_match("/font-family\s*:\s*['\"]?([^;'\"}]+)['\"]?/i", $block, $m)) {
                    $family = trim(str_replace(['"', "'"], '', $m[1]));
                    if ($family !== '') {
                        $families[$family] = true;
                    }
                }
            }
        }

        if ($families) {
            $css_out = '';
            foreach (array_keys($families) as $family) {
                $family   = str_replace(['"', "'"], '', $family);
                $css_out .= "@font-face{font-family:'{$family}';font-display:swap;}";
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "<style id='gm2-font-display-swap'>" . $css_out . "</style>";
        }
    }

    /** Rewrite Google Font URLs to css2 endpoint. */
    public static function rewrite_google_url(string $src, string $handle): string {
        if (empty(self::$options['enabled'])) {
            return $src;
        }
        if (str_contains($src, 'fonts.googleapis.com') && !str_contains($src, '/css2')) {
            $src = preg_replace('#fonts\.googleapis\.com/[^?]+#', 'fonts.googleapis.com/css2', $src);
        }
        return $src;
    }

    /** Output preconnect link tags. */
    public static function preconnect_links(): void {
        if (empty(self::$options['enabled']) || empty(self::$options['preconnect'])) {
            return;
        }
        $hosts = array_unique((array) self::$options['preconnect']);
        foreach ($hosts as $host) {
            $host = esc_url($host);
            if (!filter_var($host, FILTER_VALIDATE_URL)) {
                continue;
            }
            printf("<link rel='preconnect' href='%s' crossorigin />\n", $host);
        }
    }

    /** Output preload link tags for up to three unique WOFF2 fonts. */
    public static function preload_links(): void {
        if (empty(self::$options['enabled']) || empty(self::$options['preload'])) {
            return;
        }

        $urls = array_filter(
            self::$options['preload'],
            static function ($url) {
                return filter_var($url, FILTER_VALIDATE_URL)
                    && preg_match('/\.woff2(\?.*)?$/i', $url);
            }
        );

        foreach (array_slice(array_values(array_unique($urls)), 0, 3) as $url) {
            $url = self::endpoint_url($url);
            printf(
                '<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
                esc_url($url)
            );
        }
    }

    /** Output a lightweight system font stack when enabled. */
    public static function fallback_css(): void {
        if (empty(self::$options['enabled']) || empty(self::$options['system_fallback_css'])) {
            return;
        }
        echo "<style id='gm2-font-fallback'>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Ubuntu,\"Helvetica Neue\",Arial,\"Noto Sans\",sans-serif;}</style>\n";
    }

    /** Register REST route for serving font files with cache headers. */
    public static function register_font_route(): void {
        register_rest_route(
            'gm2seo/v1',
            '/font',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [__CLASS__, 'serve_font'],
            ]
        );
    }

    /** Stream the requested font file with long-term cache headers. */
    public static function serve_font(\WP_REST_Request $req) {
        $file = sanitize_text_field($req->get_param('file'));
        $path = wp_normalize_path(ABSPATH . $file);
        $real = realpath($path);
        if (!$real || 0 !== strpos($real, ABSPATH) || !is_readable($real)) {
            return new \WP_Error('not_found', 'Font not found', ['status' => 404]);
        }
        $mime = wp_check_filetype($real);
        $type = $mime['type'] ?: 'font/woff2';
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Cross-Origin-Resource-Policy: cross-origin');
        readfile($real);
        exit;
    }

    /** Rewrite local font URLs to the REST endpoint. */
    public static function rewrite_font_src(string $src): string {
        if (empty(self::$options['enabled'])) {
            return $src;
        }
        return self::endpoint_url($src);
    }

    /** Convert eligible font URLs to the plugin endpoint. */
    private static function endpoint_url(string $src): string {
        if (empty(self::$options['cache_headers'])) {
            return $src;
        }
        $parts = wp_parse_url($src);
        $path  = $parts['path'] ?? '';
        if (!$path || !preg_match('/\.(woff2?|ttf|otf)$/i', $path)) {
            return $src;
        }
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!empty($parts['host']) && $parts['host'] !== $home_host) {
            return $src;
        }
        $path = ltrim($path, '/');
        return rest_url('gm2seo/v1/font?file=' . rawurlencode($path));
    }

    /** Toggle the feature and persist the option. */
    public static function set_enabled(bool $enabled): void {
        $opts = self::get_settings();
        $opts['enabled'] = $enabled;
        self::$options   = $opts;
        $fn = is_multisite() ? 'update_site_option' : 'update_option';
        $fn(self::OPTION_KEY, $opts, false);
        if ($enabled) {
            self::add_hooks();
        } else {
            self::remove_hooks();
        }
    }

    /** Handle font self-hosting request. */
    public static function self_host_fonts(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'gm2-wordpress-suite'));
        }
        check_admin_referer('gm2_self_host_fonts', '_wpnonce_gm2_self_host_fonts');

        global $wp_styles;
        if (!($wp_styles instanceof \WP_Styles)) {
            $wp_styles = wp_styles();
        }

        $handles = [];
        foreach ((array) $wp_styles->registered as $handle => $style) {
            if (!empty($style->src) && str_contains($style->src, 'fonts.googleapis.com')) {
                $handles[$handle] = $style->src;
            }
        }

        $opts             = self::get_settings();
        $allowed_variants = [];
        if (!empty($opts['limit_variants']) && !empty($opts['variant_suggestions'])) {
            foreach ((array) $opts['variant_suggestions'] as $variant) {
                $variant = strtolower(trim((string) $variant));
                if (preg_match('/^\d{3}\s+(normal|italic|oblique)$/', $variant)) {
                    $allowed_variants[$variant] = true;
                }
            }
        }

        $uploads  = wp_upload_dir();
        $base_dir = trailingslashit($uploads['basedir']) . 'gm2seo-fonts/';
        $base_url = trailingslashit($uploads['baseurl']) . 'gm2seo-fonts/';

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        if (!$wp_filesystem) {
            wp_safe_redirect(add_query_arg('gm2_self_host', 'error', admin_url('admin.php?page=gm2-fonts')));
            exit;
        }

        if (!$wp_filesystem->is_dir($base_dir)) {
            $wp_filesystem->mkdir($base_dir);
        }

        $css_out  = '';
        $families = [];
        $error    = false;

        foreach ($handles as $handle => $src) {
            $response = wp_remote_get($src);
            if (is_wp_error($response)) {
                $error = true;
                break;
            }
            $css = wp_remote_retrieve_body($response);
            preg_match_all('/@font-face\s*{[^}]+}/i', $css, $blocks);
            foreach ($blocks[0] as $block) {
                if (preg_match("/font-family\s*:\s*['\"]?([^;'\"}]+)['\"]?/i", $block, $m)) {
                    $family_name = trim(str_replace(['"', "'"], '', $m[1]));
                    $family_slug = sanitize_title($family_name);
                } else {
                    $family_name = 'font';
                    $family_slug = 'font';
                }

                $weight = '400';
                if (preg_match('/font-weight\s*:\s*([0-9]+)/i', $block, $w)) {
                    $weight = trim($w[1]);
                }

                $style = 'normal';
                if (preg_match('/font-style\s*:\s*([a-z]+)/i', $block, $s)) {
                    $style = strtolower(trim($s[1]));
                }

                $variant_key = $weight . ' ' . $style;
                if (!empty($allowed_variants) && !isset($allowed_variants[$variant_key])) {
                    continue;
                }

                $families[$family_name][$weight] = true;

                preg_match_all('/url\(([^)]+\.woff2[^)]*)\)/i', $block, $urls);
                foreach ($urls[1] as $font_url) {
                    $font_url = trim($font_url, "'\"");
                    $filename = sanitize_file_name(wp_basename(parse_url($font_url, PHP_URL_PATH)));
                    if (!$filename) {
                        continue;
                    }
                    $dir = $base_dir . $family_slug . '/';
                    if (!$wp_filesystem->is_dir($dir) && !$wp_filesystem->mkdir($dir)) {
                        $error = true;
                        break 2;
                    }
                    $font_resp = wp_remote_get($font_url);
                    if (is_wp_error($font_resp)) {
                        $error = true;
                        break 2;
                    }
                    $font_body = wp_remote_retrieve_body($font_resp);
                    if (!$wp_filesystem->put_contents($dir . $filename, $font_body, FS_CHMOD_FILE)) {
                        $error = true;
                        break 2;
                    }
                    $local_url = $base_url . $family_slug . '/' . $filename;
                    $block     = str_replace($font_url, $local_url, $block);
                }

                if (stripos($block, 'font-display') === false) {
                    $block = preg_replace('/@font-face\s*{/', '@font-face{font-display:swap;', $block, 1);
                }
                $css_out .= $block . "\n";
            }
        }

        if ($error) {
            if ($wp_filesystem->is_dir($base_dir)) {
                $wp_filesystem->rmdir($base_dir, true);
            }
            wp_safe_redirect(add_query_arg('gm2_self_host', 'error', admin_url('admin.php?page=gm2-fonts')));
            exit;
        }

        $wp_filesystem->put_contents($base_dir . 'fonts-local.css', $css_out, FS_CHMOD_FILE);

        foreach (array_keys($handles) as $h) {
            wp_dequeue_style($h);
            wp_deregister_style($h);
        }
        wp_register_style('gm2seo-fonts-local', $base_url . 'fonts-local.css', [], null);
        wp_enqueue_style('gm2seo-fonts-local');

        $opts['families']  = [];
        foreach ($families as $fam => $weights) {
            $weights = array_filter(array_keys($weights));
            sort($weights);
            $opts['families'][] = $weights ? $fam . ':' . implode(',', $weights) : $fam;
        }
        $opts['self_host'] = true;
        self::$options     = $opts;
        $fn                = is_multisite() ? 'update_site_option' : 'update_option';
        $fn(self::OPTION_KEY, $opts, false);

        wp_safe_redirect(add_query_arg('gm2_self_host', 'success', admin_url('admin.php?page=gm2-fonts')));
        exit;
    }
}
