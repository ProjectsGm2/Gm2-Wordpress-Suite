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

    private static array $defaults = [
        'enabled'             => true,
        'inject_display_swap' => true,
        'google_url_rewrite'  => true,
        'preconnect'          => ['https://fonts.gstatic.com'],
        'preload'             => [],
        'self_host'           => false,
        'families'            => [],
        'limit_variants'      => true,
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
            add_filter('style_loader_tag', [__CLASS__, 'inject_font_display'], 10, 4);
        }
        if (!empty(self::$options['preconnect'])) {
            add_filter('wp_resource_hints', [__CLASS__, 'resource_hints'], 10, 2);
        }
        if (!empty(self::$options['preload'])) {
            add_action('wp_head', [__CLASS__, 'preload_links']);
        }
        if (!empty(self::$options['system_fallback_css'])) {
            add_action('wp_head', [__CLASS__, 'fallback_css']);
        }
        if (!empty(self::$options['cache_headers'])) {
            add_filter('wp_headers', [__CLASS__, 'cache_headers']);
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
        remove_filter('style_loader_tag', [__CLASS__, 'inject_font_display'], 10);
        remove_filter('wp_resource_hints', [__CLASS__, 'resource_hints'], 10);
        remove_action('wp_head', [__CLASS__, 'preload_links']);
        remove_action('wp_head', [__CLASS__, 'fallback_css']);
        remove_filter('wp_headers', [__CLASS__, 'cache_headers']);
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

    /** Inject font-display: swap into @font-face rules within enqueued styles. */
    public static function inject_font_display(string $html, string $handle, string $href, string $media): string {
        if (empty(self::$options['enabled']) || empty(self::$options['inject_display_swap'])) {
            return $html;
        }

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $parts     = wp_parse_url($href);

        if (!empty($parts['host']) && $parts['host'] !== $home_host) {
            return $html;
        }

        $path = $parts['path'] ?? '';
        if (empty($path)) {
            return $html;
        }

        $file = ABSPATH . ltrim($path, '/');
        if (!file_exists($file)) {
            return $html;
        }

        $css = file_get_contents($file);
        if ($css === false) {
            return $html;
        }

        $modified = preg_replace_callback('/@font-face\s*{[^}]*}/i', function (array $matches) {
            $block = $matches[0];
            if (stripos($block, 'font-display') === false) {
                $block = rtrim($block, '}') . 'font-display: swap;}';
            }
            return $block;
        }, $css);

        if ($modified === $css) {
            return $html;
        }

        $media_attr = $media && 'all' !== $media ? sprintf(" media='%s'", esc_attr($media)) : '';

        return sprintf("<style id='%s'%s>\n%s\n</style>", esc_attr($handle) . '-css', $media_attr, $modified);
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

    /** Inject preconnect resource hints. */
    public static function resource_hints(array $urls, string $relation_type): array {
        if (empty(self::$options['enabled']) || $relation_type !== 'preconnect') {
            return $urls;
        }
        foreach (self::$options['preconnect'] as $url) {
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /** Output preload link tags. */
    public static function preload_links(): void {
        if (empty(self::$options['enabled'])) {
            return;
        }
        foreach (self::$options['preload'] as $url) {
            printf("<link rel='preload' href='%s' as='style' />\n", esc_url($url));
        }
    }

    /** Output simple system fallback CSS for specified families. */
    public static function fallback_css(): void {
        if (empty(self::$options['enabled']) || empty(self::$options['families'])) {
            return;
        }
        echo "<style id='gm2-font-fallback'>\n";
        foreach (self::$options['families'] as $family) {
            $family = esc_html($family);
            echo "body{font-family:'{$family}',system-ui,sans-serif;}\n";
        }
        echo "</style>\n";
    }

    /** Add long cache headers. */
    public static function cache_headers(array $headers): array {
        if (empty(self::$options['enabled'])) {
            return $headers;
        }
        $headers['Cache-Control'] = 'public, max-age=31536000, immutable';
        if (is_multisite()) {
            $headers['Vary'] = isset($headers['Vary']) ? $headers['Vary'] . ', Host' : 'Host';
        }
        return $headers;
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
}
