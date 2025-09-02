<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Detector')) {
    return;
}

/**
 * Detect JavaScript dependencies and page context.
 */
class AE_SEO_JS_Detector {
    /**
     * Bootstrap the detector.
     */
    public static function init(): void {
        add_action('init', [ __CLASS__, 'build_map' ]);
        add_action('shutdown', [ __CLASS__, 'store_context' ]);
    }

    /**
     * Build and cache script dependency map.
     */
    public static function build_map(): void {
        if (get_transient('aejs_map') !== false) {
            return;
        }
        $scripts = wp_scripts();
        if (!$scripts) {
            return;
        }
        $map = [];
        foreach ($scripts->registered as $handle => $script) {
            $map[$handle] = [
                'src'       => $script->src,
                'deps'      => $script->deps,
                'in_footer' => !empty($script->extra['group']) && (int) $script->extra['group'] === 1,
            ];
        }
        set_transient('aejs_map', $map, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * Detect context and cache per URL.
     */
    public static function store_context(): void {
        if (is_admin()) {
            return;
        }
        $url = self::current_url();
        $key = 'aejs_ctx:' . md5($url);
        $ctx = [
            'page_type' => self::determine_page_type(),
            'widgets'   => self::discover_widgets(),
            'scripts'   => self::current_scripts(),
        ];
        set_transient($key, $ctx, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * Retrieve context for current URL.
     */
    public static function get_current_context(): array {
        $key = 'aejs_ctx:' . md5(self::current_url());
        $ctx = get_transient($key);
        return is_array($ctx) ? $ctx : [];
    }

    /**
     * Determine page type.
     */
    private static function determine_page_type(): string {
        if (is_front_page()) {
            return 'front';
        }
        if (function_exists('is_woocommerce') && is_woocommerce()) {
            if (function_exists('is_cart') && is_cart()) {
                return 'woo-cart';
            }
            if (function_exists('is_checkout') && is_checkout()) {
                return 'woo-checkout';
            }
            if (function_exists('is_account_page') && is_account_page()) {
                return 'woo-account';
            }
            if (function_exists('is_product') && is_product()) {
                return 'woo-product';
            }
            return 'woocommerce';
        }
        if (is_singular()) {
            $type = get_post_type();
            return $type ? 'single-' . $type : 'singular';
        }
        return 'other';
    }

    /**
     * Discover widgets, shortcodes, blocks and recaptcha usage.
     */
    private static function discover_widgets(): array {
        $widgets = [];
        $content = '';
        if (is_singular()) {
            $post = get_post();
            if ($post instanceof \WP_Post) {
                $content = (string) $post->post_content;
            }
        }
        // Elementor detection
        if (class_exists('\Elementor\Plugin') && is_singular()) {
            try {
                $document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend(get_the_ID());
                if ($document && $document->is_built_with_elementor()) {
                    $widgets[] = 'elementor';
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
        if ($content !== '') {
            if (preg_match_all('/\[([\w\-]+)(?:\s|\])/', $content, $sc_matches)) {
                foreach ($sc_matches[1] as $shortcode) {
                    $widgets[] = $shortcode;
                }
            }
            if (function_exists('has_blocks') && has_blocks($content)) {
                $blocks = parse_blocks($content);
                foreach ($blocks as $block) {
                    if (!empty($block['blockName'])) {
                        $widgets[] = $block['blockName'];
                    }
                }
            }
            if (strpos($content, 'g-recaptcha') !== false || strpos($content, 'data-sitekey') !== false) {
                $widgets[] = 'recaptcha';
            }
        }
        return array_values(array_unique($widgets));
    }

    /**
     * Collect currently queued scripts.
     */
    private static function current_scripts(): array {
        $scripts = wp_scripts();
        if (!$scripts) {
            return [];
        }
        return array_values(array_unique($scripts->queue));
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
