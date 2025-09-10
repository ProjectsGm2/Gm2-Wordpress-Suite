<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Audit enqueued scripts and styles with byte estimates.
 */
class HandleAuditor {
    private static bool $done = false;

    /** Register hooks. */
    public static function boot(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'audit'], PHP_INT_MAX);
        add_action('wp_print_scripts', [__CLASS__, 'audit'], PHP_INT_MAX);
    }

    /**
     * Collect script/style handles and store summary per URL.
     */
    public static function audit(): void {
        if (self::$done || is_admin()) {
            return;
        }
        self::$done = true;

        global $wp_scripts, $wp_styles;
        $wp_scripts = $wp_scripts ?: wp_scripts();
        $wp_styles  = $wp_styles ?: wp_styles();

        $summary = [
            'url'        => self::current_url(),
            'scripts'    => [],
            'styles'     => [],
            'total_js'   => 0,
            'total_css'  => 0,
        ];

        foreach ((array) $wp_scripts->queue as $handle) {
            $src = self::resolve_src($wp_scripts->registered[$handle]->src ?? '', $wp_scripts);
            $bytes = self::estimate_bytes($src);
            $entry = ['handle' => $handle, 'src' => $src, 'bytes' => $bytes];
            if ($path = self::local_path($src)) {
                $entry['path'] = $path;
            }
            $summary['scripts'][] = $entry;
            $summary['total_js'] += $bytes;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            $src = self::resolve_src($wp_styles->registered[$handle]->src ?? '', $wp_styles);
            $bytes = self::estimate_bytes($src);
            $entry = ['handle' => $handle, 'src' => $src, 'bytes' => $bytes];
            if ($path = self::local_path($src)) {
                $entry['path'] = $path;
            }
            $summary['styles'][] = $entry;
            $summary['total_css'] += $bytes;
        }

        $key = 'gm2_np_' . md5($summary['url']);
        set_transient($key, $summary, DAY_IN_SECONDS);

        $stats   = get_option('gm2_netpayload_stats', ['samples' => [], 'average' => 0]);
        $assets  = $stats['assets'] ?? ['js' => 0, 'css' => 0];
        $assets['js']  += $summary['total_js'];
        $assets['css'] += $summary['total_css'];
        $stats['assets'] = $assets;
        update_option('gm2_netpayload_stats', $stats, false);
    }

    private static function current_url(): string {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
        return home_url($path);
    }

    private static function resolve_src(string $src, \WP_Dependencies $deps): string {
        if ($src === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#', $src)) {
            return $src;
        }
        return $deps->base_url . $src;
    }

    private static function local_path(string $url): ?string {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $site = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$host || $host === $site) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            $file = ABSPATH . ltrim($path ?? '', '/');
            return file_exists($file) ? $file : null;
        }
        return null;
    }

    private static function estimate_bytes(string $url): int {
        if (!$url) {
            return 0;
        }
        if ($file = self::local_path($url)) {
            $size = @filesize($file);
            return $size ? (int) $size : 0;
        }
        $cache = 'gm2_np_size_' . md5($url);
        $cached = get_transient($cache);
        if (false !== $cached) {
            return (int) $cached;
        }
        $resp = wp_remote_head($url, ['timeout' => 5]);
        if (!is_wp_error($resp)) {
            $len = (int) wp_remote_retrieve_header($resp, 'content-length');
            if ($len > 0) {
                set_transient($cache, $len, DAY_IN_SECONDS);
                return $len;
            }
        }
        return 0;
    }
}
