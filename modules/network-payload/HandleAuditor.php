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

        $summary['total'] = $summary['total_js'] + $summary['total_css'];

        $key = 'gm2_np_' . md5($summary['url']);
        set_transient($key, $summary, DAY_IN_SECONDS);

        $stats   = get_option('gm2_netpayload_stats', ['samples' => [], 'average' => 0, 'budget' => 0]);
        $assets  = $stats['assets'] ?? ['js' => 0, 'css' => 0, 'total' => 0];
        $assets['js']  += $summary['total_js'];
        $assets['css'] += $summary['total_css'];
        $assets['total'] += $summary['total'];
        $stats['assets'] = $assets;

        $handles = $stats['handles'] ?? ['scripts' => [], 'styles' => []];
        $ctx     = self::context_tag();
        foreach ($summary['scripts'] as $entry) {
            $h = $entry['handle'];
            $info = $handles['scripts'][$h] ?? ['bytes' => 0, 'deps' => [], 'contexts' => []];
            $info['bytes'] = max(intval($entry['bytes']), intval($info['bytes']));
            $info['deps']  = $wp_scripts->registered[$h]->deps ?? [];
            $info['contexts'][] = $ctx;
            $info['contexts'] = array_values(array_unique($info['contexts']));
            $handles['scripts'][$h] = $info;
        }
        foreach ($summary['styles'] as $entry) {
            $h = $entry['handle'];
            $info = $handles['styles'][$h] ?? ['bytes' => 0, 'deps' => [], 'contexts' => []];
            $info['bytes'] = max(intval($entry['bytes']), intval($info['bytes']));
            $info['deps']  = $wp_styles->registered[$h]->deps ?? [];
            $info['contexts'][] = $ctx;
            $info['contexts'] = array_values(array_unique($info['contexts']));
            $handles['styles'][$h] = $info;
        }
        $stats['handles'] = $handles;

        update_option('gm2_netpayload_stats', $stats, false);

        $opts = Module::get_settings();
        if (!empty($opts['asset_budget']) && !empty($opts['asset_budget_limit'])) {
            $limit = intval($opts['asset_budget_limit']);
            if ($summary['total'] > $limit && current_user_can('manage_options') && is_admin_bar_showing()) {
                add_action('admin_bar_menu', function ($bar) use ($summary, $limit) {
                    $title = sprintf(
                        __('Asset payload %s KB exceeds limit %s KB', 'gm2-wordpress-suite'),
                        number_format_i18n($summary['total'] / 1024, 1),
                        number_format_i18n($limit / 1024, 1)
                    );
                    $bar->add_node([
                        'id'    => 'gm2-asset-budget',
                        'title' => esc_html($title),
                    ]);
                }, 100);
            }
        }
    }

    private static function current_url(): string {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
        return home_url($path);
    }

    private static function context_tag(): string {
        if (is_front_page()) {
            return 'front';
        }
        if (is_home()) {
            return 'home';
        }
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        return 'other';
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
