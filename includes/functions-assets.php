<?php
/**
 * Helper for registering built assets using the manifest.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ae_seo_register_asset')) {
    /**
     * Register a script or style from the compiled assets.
     *
     * @param string $handle       Asset handle.
     * @param string $logical_path Logical path in the manifest.
     * @return void
     */
    function ae_seo_register_asset(string $handle, string $logical_path): void {
        static $manifest = null;

        if ($manifest === null) {
            $manifest_file = GM2_PLUGIN_DIR . 'assets/build/manifest.json';
            if (file_exists($manifest_file)) {
                $json = file_get_contents($manifest_file);
                $manifest = json_decode($json, true);
                if (!is_array($manifest)) {
                    $manifest = [];
                }
            } else {
                $manifest = [];
            }
        }

        $file = $manifest[$logical_path] ?? $logical_path;

        $version = null;
        if (preg_match('/\.([a-f0-9]{8})\.(?:js|css)$/', $file, $m)) {
            $version = $m[1];
        }

        $src = GM2_PLUGIN_URL . 'assets/dist/' . $file;

        if (str_ends_with($file, '.js')) {
            wp_register_script($handle, $src, [], $version, true);
        } elseif (str_ends_with($file, '.css')) {
            wp_register_style($handle, $src, [], $version);
        }
    }
}

if (!function_exists('ae_seo_js_safe_mode')) {
    /**
     * Determine if safe mode is enabled for JavaScript features.
     *
     * @return bool
     */
    function ae_seo_js_safe_mode(): bool {
        return get_option('ae_js_respect_safe_mode') === '1' && (($_GET['aejs'] ?? '') === 'off');
    }
}

if (!function_exists('ae_seo_script_loader_tag')) {
    /**
     * Add module or nomodule attributes to AE scripts.
     *
     * @param string $tag    The original script tag.
     * @param string $handle Script handle.
     * @param string $src    Script source.
     * @return string
     */
    function ae_seo_script_loader_tag(string $tag, string $handle, string $src): string {
        if (ae_seo_js_safe_mode()) {
            return $tag;
        }

        $send_modern = get_option('ae_js_send_modern', '1') === '1';
        $send_legacy = get_option('ae_js_send_nomodule_legacy', '0') === '1';

        if (!str_starts_with($handle, 'ae-')) {
            return $tag;
        }

        if ($send_modern && str_contains($src, '.modern.')) {
            return sprintf('<script type="module" src="%s" id="%s-js" defer></script>', $src, $handle);
        }

        if ($send_legacy && str_contains($src, '.legacy.')) {
            return sprintf('<script nomodule src="%s" id="%s-js" defer></script>', $src, $handle);
        }

        return $tag;
    }
}

add_filter('script_loader_tag', 'ae_seo_script_loader_tag', 10, 3);
