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
        if (ae_seo_js_safe_mode()) {
            return;
        }
        add_action('wp_enqueue_scripts', [ __CLASS__, 'control_scripts' ], 999);
        add_action('wp_enqueue_scripts', [ __CLASS__, 'maybe_remove_jquery' ], 1000);
        add_filter('ae_seo/js/enqueue_decision', [ __CLASS__, 'allow_override' ], 5, 3);
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
                AE_SEO_JS_Manager::$dequeued++;
                $reason = in_array($handle, $context_scripts, true) ? 'filter' : 'context';
                ae_seo_js_log('dequeue ' . $handle . ' (' . $reason . ') ' . $url);
            }
        }
    }

    /**
     * Always allow scripts based on saved overrides.
     *
     * @param bool   $allow   Current allow decision.
     * @param string $handle  Script handle.
     * @param array  $context Context data.
     * @return bool
     */
    public static function allow_override(bool $allow, string $handle, array $context): bool {
        if ($allow) {
            return true;
        }
        $overrides = get_option('ae_js_overrides', []);
        if (!isset($overrides[$handle]) || !is_array($overrides[$handle])) {
            return $allow;
        }
        $page = $context['page_type'] ?? '';
        if ($page !== '' && in_array($page, $overrides[$handle], true)) {
            return true;
        }
        return $allow;
    }

    /**
     * Remove jQuery when no scripts depend on it.
     */
    public static function maybe_remove_jquery(): void {
        if (ae_seo_js_safe_mode()) {
            return;
        }
        if (get_option('ae_js_jquery_on_demand', '0') !== '1') {
            return;
        }
        $patterns = get_option('ae_js_jquery_url_allow', '');
        $url = self::current_url();
        if (is_string($patterns) && $patterns !== '') {
            $list = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $patterns)));
            foreach ($list as $pattern) {
                if ($pattern !== '' && strpos($url, $pattern) !== false) {
                    return;
                }
            }
        }
        $scripts = wp_scripts();
        if (!$scripts) {
            return;
        }
        $needs_jquery = in_array('jquery', $scripts->queue, true);
        if (!$needs_jquery) {
            foreach ($scripts->queue as $handle) {
                $registered = $scripts->registered[$handle] ?? null;
                if ($registered && in_array('jquery', $registered->deps, true)) {
                    $needs_jquery = true;
                    break;
                }
            }
        }
        if (!$needs_jquery) {
            wp_dequeue_script('jquery');
            wp_dequeue_script('jquery-migrate');
            AE_SEO_JS_Manager::$jquery++;
            ae_seo_js_log('dequeue jquery (no-deps) ' . $url);
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

