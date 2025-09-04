<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_Font_Manager')) {
    return;
}

/**
 * Cache external font stylesheets and files locally.
 */
class AE_SEO_Font_Manager {
    private const OPTION_ENABLE = 'ae_seo_local_fonts';
    private const OPTION_CACHE  = 'ae_seo_font_cache';
    private const CRON_HOOK     = 'ae_seo_font_manager_sync';

    /**
     * Bootstrap the manager.
     */
    public static function init(): void {
        if (is_admin()) {
            add_action('admin_init', [__CLASS__, 'register_setting']);
        }
        if (get_option(self::OPTION_ENABLE, '0') !== '1') {
            return;
        }
        add_filter('style_loader_src', [__CLASS__, 'intercept_style'], 10, 2);
        add_filter('wp_resource_hints', [__CLASS__, 'filter_hints'], 10, 2);
        if (!is_admin()) {
            add_action('wp_head', [__CLASS__, 'start_head_buffer'], 0);
            add_action('wp_head', [__CLASS__, 'end_head_buffer'], PHP_INT_MAX);
        }
        add_action(self::CRON_HOOK, [__CLASS__, 'sync_cached_fonts']);
        self::schedule_event();
    }

    /**
     * Register the enable setting on the Reading settings screen.
     */
    public static function register_setting(): void {
        register_setting('reading', self::OPTION_ENABLE);
        add_settings_field(
            self::OPTION_ENABLE,
            __('Local Font Caching', 'gm2-wordpress-suite'),
            [__CLASS__, 'render_setting'],
            'reading'
        );
    }

    /**
     * Render the checkbox for enabling the feature.
     */
    public static function render_setting(): void {
        $value = get_option(self::OPTION_ENABLE, '0');
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ENABLE) . '" value="1"' . checked('1', $value, false) . '/> ';
        echo esc_html__( 'Download and serve fonts locally', 'gm2-wordpress-suite' );
        echo '</label>';
    }

    /**
     * Intercept enqueued styles and replace remote font CSS with local copies.
     *
     * @param string $src    Original source URL.
     * @param string $handle Handle.
     * @return string
     */
    public static function intercept_style(string $src, string $handle): string {
        $host = wp_parse_url($src, PHP_URL_HOST);
        if (!$host) {
            return $src;
        }
        $site = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($host === $site) {
            return $src;
        }
        $cached = self::cache_stylesheet($src);
        return $cached ?: $src;
    }

    /**
     * Cache a remote stylesheet and referenced font files locally.
     */
    private static function cache_stylesheet(string $url): ?string {
        $cache = get_option(self::OPTION_CACHE, []);
        $dir   = wp_upload_dir();
        $base_dir = trailingslashit($dir['basedir']) . 'fonts';
        $base_url = trailingslashit($dir['baseurl']) . 'fonts';
        if (!is_dir($base_dir)) {
            wp_mkdir_p($base_dir);
        }
        if (isset($cache[$url]) && file_exists($cache[$url]['css'])) {
            return $base_url . '/' . basename($cache[$url]['css']);
        }
        $resp = wp_remote_get($url);
        if (is_wp_error($resp)) {
            return null;
        }
        $css = wp_remote_retrieve_body($resp);
        if (!is_string($css) || $css === '') {
            return null;
        }
        $css = preg_replace_callback('/url\(([^)]+)\)/', function ($m) use ($base_dir, $base_url) {
            $font = trim($m[1], '\"\'');
            if (strpos($font, 'data:') === 0) {
                return $m[0];
            }
            $fname = basename(parse_url($font, PHP_URL_PATH));
            $file  = $base_dir . '/' . $fname;
            $resp  = wp_remote_get($font);
            if (!is_wp_error($resp)) {
                $body = wp_remote_retrieve_body($resp);
                if ($body !== '') {
                    file_put_contents($file, $body);
                }
            }
            return 'url(' . $base_url . '/' . rawurlencode($fname) . ')';
        }, $css);
        $css_file = $base_dir . '/' . md5($url) . '.css';
        file_put_contents($css_file, $css);
        $cache[$url] = ['css' => $css_file, 'time' => time()];
        update_option(self::OPTION_CACHE, $cache, false);
        return $base_url . '/' . basename($css_file);
    }

    /**
     * Filter resource hints to remove external font hosts.
     *
     * @param array  $urls URLs.
     * @param string $rel  Relation type.
     * @return array
     */
    public static function filter_hints(array $urls, string $rel): array {
        if ($rel !== 'preconnect') {
            return $urls;
        }
        $blocked = ['fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit.net', 'fonts.bunny.net'];
        return array_filter($urls, function ($u) use ($blocked) {
            foreach ($blocked as $b) {
                if (str_contains($u, $b)) {
                    return false;
                }
            }
            return true;
        });
    }

    /** Start buffering wp_head output to strip preconnect tags. */
    public static function start_head_buffer(): void {
        ob_start();
    }

    /** End buffering and remove external font preconnects. */
    public static function end_head_buffer(): void {
        $html = ob_get_clean();
        $html = preg_replace('#<link[^>]+rel=["\']preconnect["\'][^>]+fonts[^>]*>#i', '', $html);
        echo $html;
    }

    /**
     * Schedule the cron event.
     */
    private static function schedule_event(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Refresh all cached fonts.
     */
    public static function sync_cached_fonts(): void {
        $cache = get_option(self::OPTION_CACHE, []);
        if (!$cache) {
            return;
        }
        foreach (array_keys($cache) as $remote) {
            self::cache_stylesheet($remote);
        }
    }
}
