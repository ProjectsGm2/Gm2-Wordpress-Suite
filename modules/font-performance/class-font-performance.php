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
    }

    /** Load options and set up hooks. */
    public static function bootstrap(): void {
        self::$options = self::get_options();
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
        remove_filter('wp_resource_hints', [__CLASS__, 'resource_hints'], 10);
        remove_action('wp_head', [__CLASS__, 'preload_links']);
        remove_action('wp_head', [__CLASS__, 'fallback_css']);
        remove_filter('wp_headers', [__CLASS__, 'cache_headers']);
        self::$hooks_added = false;
    }

    /** Append display=swap parameter to Google Font URLs. */
    public static function inject_display_swap(string $src, string $handle): string {
        if (str_contains($src, 'fonts.googleapis.com')) {
            $src = add_query_arg('display', 'swap', $src);
        }
        return $src;
    }

    /** Rewrite Google Font URLs to css2 endpoint. */
    public static function rewrite_google_url(string $src, string $handle): string {
        if (str_contains($src, 'fonts.googleapis.com') && !str_contains($src, '/css2')) {
            $src = preg_replace('#fonts\.googleapis\.com/[^?]+#', 'fonts.googleapis.com/css2', $src);
        }
        return $src;
    }

    /** Inject preconnect resource hints. */
    public static function resource_hints(array $urls, string $relation_type): array {
        if ($relation_type !== 'preconnect') {
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
        foreach (self::$options['preload'] as $url) {
            printf("<link rel='preload' href='%s' as='style' />\n", esc_url($url));
        }
    }

    /** Output simple system fallback CSS for specified families. */
    public static function fallback_css(): void {
        if (empty(self::$options['families'])) {
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
        $headers['Cache-Control'] = 'public, max-age=31536000, immutable';
        return $headers;
    }

    /** Toggle the feature and persist the option. */
    public static function set_enabled(bool $enabled): void {
        $opts = self::get_options();
        $opts['enabled'] = $enabled;
        $fn = is_multisite() ? 'update_site_option' : 'update_option';
        $fn(self::OPTION_KEY, $opts, false);
        if ($enabled) {
            self::add_hooks();
        } else {
            self::remove_hooks();
        }
    }
}
