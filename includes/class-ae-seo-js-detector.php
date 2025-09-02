<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Detector')) {
    return;
}

/**
 * Detect page context and required scripts.
 */
class AE_SEO_JS_Detector {
    /**
     * Bootstrap the detector.
     */
    public static function init(): void {
        add_action('wp', [ new self(), 'run' ], 0);
    }

    /**
     * Build dependency map and store context for current URL.
     */
    public function run(): void {
        if (get_option('ae_js_respect_safe_mode', '1') === '1' && isset($_GET['aejs']) && $_GET['aejs'] === 'off') {
            return;
        }

        $map = get_transient('ae_js_dependency_map');
        if (!is_array($map)) {
            $map = $this->build_map();
            set_transient('ae_js_dependency_map', $map, 30 * MINUTE_IN_SECONDS);
        }

        $context = $this->detect_context($map);
        $url     = home_url(add_query_arg([], $_SERVER['REQUEST_URI']));
        set_transient('aejs_ctx:' . md5($url), $context, 30 * MINUTE_IN_SECONDS);

        $usage = get_option('ae_js_script_usage', []);
        if (!is_array($usage)) {
            $usage = [];
        }
        foreach ($context['scripts'] as $handle) {
            $usage[$handle] = ($usage[$handle] ?? 0) + 1;
        }
        update_option('ae_js_script_usage', $usage);
    }

    /**
     * Build dependency map from registered scripts.
     *
     * @return array
     */
    private function build_map(): array {
        $wp_scripts = wp_scripts();
        $map        = [];
        foreach ($wp_scripts->registered as $handle => $obj) {
            $map[$handle] = [
                'src'       => $obj->src,
                'deps'      => $obj->deps,
                'in_footer' => !empty($obj->extra['group']),
            ];
        }
        return $map;
    }

    /**
     * Detect page context and needed scripts.
     *
     * @param array $map Dependency map.
     * @return array
     */
    private function detect_context(array $map): array {
        $needed    = [];
        $widgets   = [];
        $shortcodes = [];
        $blocks    = [];
        $recaptcha = false;
        $elementor = false;
        $woo       = false;

        $page_type = 'other';
        if (is_front_page()) {
            $page_type = 'front_page';
        } elseif (is_singular()) {
            $page_type = 'singular_' . get_post_type();
        } elseif (is_archive()) {
            $page_type = 'archive';
        }

        if (function_exists('is_woocommerce') && (is_woocommerce() || (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()) || (function_exists('is_account_page') && is_account_page()))) {
            $woo     = true;
            $widgets[] = 'woocommerce';
            foreach ($map as $handle => $data) {
                if (strpos($handle, 'wc-') === 0 || strpos($handle, 'woocommerce') !== false) {
                    $needed[] = $handle;
                }
            }
        }

        $post = null;
        if (is_singular()) {
            $post = get_post();
        }
        if ($post) {
            $elementor = (bool) get_post_meta($post->ID, '_elementor_edit_mode', true);
            $content   = $post->post_content;
            if (preg_match_all('/\[(\w+)[^\]]*\]/', $content, $m)) {
                $shortcodes = array_unique($m[1]);
            }
            if (function_exists('has_blocks') && has_blocks($post)) {
                $parsed = parse_blocks($content);
                foreach ($parsed as $block) {
                    if (!empty($block['blockName'])) {
                        $blocks[] = $block['blockName'];
                    }
                }
            }
            if (strpos($content, 'g-recaptcha') !== false || strpos($content, 'data-sitekey') !== false) {
                $recaptcha = true;
                $widgets[] = 'recaptcha';
            }
        }

        if (!$elementor && class_exists('\\Elementor\\Plugin')) {
            $elementor = true;
        }
        if ($elementor) {
            $widgets[] = 'elementor';
            foreach ($map as $handle => $data) {
                if (strpos($handle, 'elementor') !== false) {
                    $needed[] = $handle;
                }
            }
        }

        if (!empty($blocks)) {
            foreach (['wp-block-library', 'wp-block-library-theme', 'wp-editor', 'wp-dom-ready', 'wp-element', 'wp-embed'] as $bh) {
                if (isset($map[$bh])) {
                    $needed[] = $bh;
                }
            }
        }
        if ($recaptcha) {
            foreach ($map as $handle => $data) {
                if (strpos($handle, 'recaptcha') !== false) {
                    $needed[] = $handle;
                }
            }
        }

        return [
            'page_type'  => $page_type,
            'widgets'    => $widgets,
            'shortcodes' => $shortcodes,
            'blocks'     => $blocks,
            'scripts'    => array_values(array_unique($needed)),
        ];
    }
}
