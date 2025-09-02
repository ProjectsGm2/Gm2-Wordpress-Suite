<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Controller')) {
    return;
}

/**
 * Control JavaScript loading based on detector context.
 */
class AE_SEO_JS_Controller {
    /**
     * Bootstrap the controller.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ __CLASS__, 'control_scripts' ], 999);
    }

    /**
     * Dequeue scripts not allowed for current context.
     */
    public static function control_scripts(): void {
        $scripts = wp_scripts();
        if (!$scripts) {
            return;
        }
        $context = AE_SEO_JS_Detector::get_current_context();
        $context_scripts = $context['scripts'] ?? [];
        $has_blocks = false;
        foreach ($context['widgets'] ?? [] as $widget) {
            if (strpos($widget, '/') !== false) {
                $has_blocks = true;
                break;
            }
        }
        $url = self::current_url();
        foreach ($scripts->queue as $handle) {
            $allow = in_array($handle, $context_scripts, true);
            $allow = apply_filters('ae_seo/js/enqueue_decision', $allow, $handle, $context);
            if (!$allow) {
                if ($has_blocks && self::is_core_block_handle($handle)) {
                    continue;
                }
                wp_dequeue_script($handle);
                $reason = in_array($handle, $context_scripts, true) ? 'filter' : 'context';
                ae_seo_js_log('dequeue ' . $handle . ' (' . $reason . ') ' . $url);
            }
        }
    }

    /**
     * Determine whether handle is core block editor script.
     */
    private static function is_core_block_handle(string $handle): bool {
        $core = [
            'wp-block-library',
            'wp-blocks',
            'wp-dom-ready',
            'wp-element',
            'wp-components',
            'wp-editor',
            'wp-edit-post',
            'wp-block-editor',
            'wp-data',
            'wp-i18n',
            'wp-compose',
            'wp-hooks',
            'wp-plugins',
            'wp-polyfill',
        ];
        return in_array($handle, $core, true);
    }

    /**
     * Build current URL.
     */
    private static function current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        return $scheme . '://' . $host . $uri;
    }
}

