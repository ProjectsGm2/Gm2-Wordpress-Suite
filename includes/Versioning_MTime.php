<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Replaces asset version query parameters with file modification times.
 *
 * Local CSS and JS URLs are automatically versioned using filemtime() so
 * browsers receive updated files whenever they change.
 *
 * To opt out for a specific handle pass `['no_auto_version' => true]` as the
 * final argument to wp_register_script() or wp_register_style().
 */
class Versioning_MTime {
    /**
     * Register hooks.
     */
    public static function init() : void {
        add_filter('style_loader_src', [__CLASS__, 'update_src'], 10, 2);
        add_filter('script_loader_src', [__CLASS__, 'update_src'], 10, 2);
    }

    /**
     * Replace the `ver` query argument with filemtime for local files.
     *
     * @param string $src    Asset URL.
     * @param string $handle Handle of the asset.
     * @return string Updated URL.
     */
    public static function update_src(string $src, string $handle) : string {
        if (self::is_opted_out($handle)) {
            return $src;
        }

        $parts = wp_parse_url($src);
        if (!$parts) {
            return $src;
        }

        $asset_host = $parts['host'] ?? '';
        $site_host  = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($asset_host && !self::hosts_match($asset_host, (string) $site_host)) {
            // Third-party URL.
            return $src;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            return $src;
        }

        $file_path = ABSPATH . ltrim($path, '/');
        if (!file_exists($file_path)) {
            return $src;
        }

        $mtime = filemtime($file_path);
        if (!$mtime) {
            return $src;
        }

        $src = remove_query_arg('ver', $src);
        $src = add_query_arg('ver', (string) $mtime, $src);
        return $src;
    }

    /**
     * Determine if a handle has opted out of automatic versioning.
     *
     * @param string $handle Asset handle.
     * @return bool
     */
    private static function is_opted_out(string $handle) : bool {
        global $wp_scripts, $wp_styles;

        $registered = null;
        if ($wp_scripts instanceof \WP_Scripts && isset($wp_scripts->registered[$handle])) {
            $registered = $wp_scripts->registered[$handle];
        } elseif ($wp_styles instanceof \WP_Styles && isset($wp_styles->registered[$handle])) {
            $registered = $wp_styles->registered[$handle];
        }

        if ($registered && !empty($registered->extra['no_auto_version'])) {
            return true;
        }

        return false;
    }

    /**
     * Compare two hosts for equality in a case-insensitive manner.
     */
    private static function hosts_match(string $a, string $b) : bool {
        return strcasecmp($a, $b) === 0;
    }
}
