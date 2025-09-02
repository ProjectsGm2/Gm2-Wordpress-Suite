<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Controller')) {
    return;
}

/**
 * Control enqueued scripts based on detected context.
 */
class AE_SEO_JS_Controller {
    /**
     * Bootstrap the controller.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ new self(), 'control' ], 999);
    }

    /**
     * Conditionally dequeue scripts.
     */
    public function control(): void {
        if (get_option('ae_js_auto_dequeue', '0') !== '1') {
            return;
        }
        if (get_option('ae_js_respect_safe_mode', '1') === '1' && isset($_GET['aejs']) && $_GET['aejs'] === 'off') {
            return;
        }

        $url     = home_url(add_query_arg([], $_SERVER['REQUEST_URI']));
        $context = get_transient('aejs_ctx:' . md5($url));
        if (!is_array($context)) {
            return;
        }

        $allow_list     = get_option('ae_js_allow_list', []);
        $deny_list      = get_option('ae_js_deny_list', []);
        $template_allow = get_option('ae_js_template_allow', []);
        $wp_scripts     = wp_scripts();
        foreach ($wp_scripts->queue as $handle) {
            if (is_array($allow_list) && in_array($handle, $allow_list, true)) {
                continue;
            }
            if (is_array($deny_list) && in_array($handle, $deny_list, true)) {
                wp_dequeue_script($handle);
                ae_seo_js_log('deny ' . $handle . ' ' . $url);
                continue;
            }

            $keep = is_array($context['scripts']) && in_array($handle, $context['scripts'], true);

            if (!$keep && isset($template_allow[$handle])) {
                $tpls = $template_allow[$handle];
                if (is_front_page() && in_array('front_page', $tpls, true)) {
                    $keep = true;
                } elseif (is_singular() && in_array('singular', $tpls, true)) {
                    $keep = true;
                } elseif (function_exists('is_product') && is_product() && in_array('product', $tpls, true)) {
                    $keep = true;
                }
            }

            if (!$keep && !empty($context['blocks'])) {
                $core_block_handles = ['wp-block-library', 'wp-block-library-theme', 'wp-editor', 'wp-dom-ready', 'wp-element', 'wp-embed'];
                if (in_array($handle, $core_block_handles, true)) {
                    $keep = true;
                }
            }

            $keep = apply_filters('ae_seo/js/enqueue_decision', $keep, $handle, $context);
            if (!$keep) {
                wp_dequeue_script($handle);
                ae_seo_js_log('dequeue ' . $handle . ' ' . $url);
            }
        }
    }
}
