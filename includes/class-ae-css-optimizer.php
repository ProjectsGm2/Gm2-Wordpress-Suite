<?php
namespace AE\CSS;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * CSS optimization utilities.
 */
final class AE_CSS_Optimizer {
    /**
     * Option name for persisted settings.
     */
    private const OPTION = 'ae_css_settings';

    /**
     * Singleton instance.
     *
     * @var AE_CSS_Optimizer|null
     */
    private static ?AE_CSS_Optimizer $instance = null;

    /**
     * Whether init has executed.
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Cached settings array.
     *
     * @var array
     */
    private array $settings = [
        'flags'    => [],
        'critical' => [],
        'queue'    => [],
    ];

    /**
     * Retrieve the singleton instance.
     *
     * @return self Singleton instance.
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register callbacks on important hooks.
     *
     * @return void
     */
    public static function bootstrap(): void {
        $instance = self::get_instance();
        foreach ([
            'admin_menu',
            'admin_init',
            'wp',
            'template_redirect',
            'save_post',
            'switch_theme',
            'updated_option',
        ] as $hook) {
            add_action($hook, [ $instance, 'init' ]);
        }
    }

    /**
     * Initialise internals once per request.
     *
     * @return void
     */
    public function init(): void {
        if ($this->booted) {
            return;
        }
        $this->booted   = true;
        $this->settings = \get_option(self::OPTION, $this->settings);

        add_action('wp_enqueue_scripts', [ $this, 'enqueue_smart' ], PHP_INT_MAX);
        add_action('wp_head', [ $this, 'inject_critical_and_defer' ], 1);
        add_filter('style_loader_tag', [ $this, 'inject_critical_and_defer' ], 10, 4);
    }

    /**
     * Dequeue WooCommerce and Elementor styles when unneeded.
     *
     * @return void
     */
    public function enqueue_smart(): void {
        $styles = \wp_styles();
        if (!$styles instanceof \WP_Styles) {
            return;
        }
        if (\class_exists('WooCommerce') && empty($this->settings['flags']['woo']) && !self::is_woocommerce_context()) {
            foreach ($styles->queue as $handle) {
                if (strpos($handle, 'woocommerce') === 0) {
                    \wp_dequeue_style($handle);
                }
            }
        }
        if (\did_action('elementor/loaded') && empty($this->settings['flags']['elementor']) && !self::is_elementor_context()) {
            foreach ($styles->queue as $handle) {
                if (strpos($handle, 'elementor') === 0) {
                    \wp_dequeue_style($handle);
                }
            }
        }
    }

    /**
     * Mark a URL for critical CSS generation.
     *
     * @param string $url     URL to capture.
     * @param int    $post_id Optional related post ID.
     *
     * @return void
     */
    public function mark_url_for_critical_generation(string $url, int $post_id = 0): void {
        $url = \esc_url_raw($url);
        if ($url === '') {
            return;
        }
        $this->settings['queue'][] = [ 'url' => $url, 'post_id' => $post_id ];
        \update_option(self::OPTION, $this->settings, false);
    }

    /**
     * Retrieve stored critical CSS for a given URL.
     *
     * @param string $url URL whose CSS to fetch.
     * @return string Critical CSS or empty string.
     */
    public function get_critical_css(string $url): string {
        $url = \esc_url_raw($url);
        return $this->settings['critical'][$url] ?? '';
    }

    /**
     * Inject critical CSS and defer the rest of the stylesheet loading.
     *
     * @param string $html   Original tag when used as filter.
     * @param string $handle Handle of the style.
     * @param string $href   Stylesheet URL.
     * @param string $media  Media attribute.
     * @return string|null Modified HTML tag or null on action.
     */
    public function inject_critical_and_defer(string $html = '', string $handle = '', string $href = '', string $media = '') {
        if (\is_admin()) {
            return $html;
        }
        if (current_filter() === 'style_loader_tag') {
            if ($html === '') {
                return $html;
            }
            if (strpos($html, 'rel="stylesheet"') !== false) {
                $html = str_replace('rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $html);
            } elseif (strpos($html, "rel='stylesheet'") !== false) {
                $html = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $html);
            }
            return $html;
        }
        $current = \home_url(\add_query_arg([], ''));
        $css     = $this->get_critical_css($current);
        if ($css !== '') {
            echo '<style id="ae-critical-css">' . $css . '</style>';
        }
        return null;
    }

    /**
     * Analyse CSS usage with PurgeCSS.
     *
     * @param array $css_paths  Array of CSS file paths.
     * @param array $html_paths Array of HTML file paths.
     * @param array $safelist   Optional list of selectors to preserve.
     * @return string Optimised CSS output.
     */
    public static function purgecss_analyze(array $css_paths, array $html_paths, array $safelist = []): string {
        if (!self::has_node_capability()) {
            return '';
        }
        // Stub: integrate with Node-based PurgeCSS.
        return '';
    }

    /**
     * Naive PHP split to extract above-the-fold CSS.
     *
     * @param string $css_string CSS string.
     * @return array{0:string,1:string} Critical and remaining CSS parts.
     */
    public static function php_fallback_split_css(string $css_string): array {
        $limit    = 20000; // bytes.
        $critical = \substr($css_string, 0, $limit);
        $rest     = \substr($css_string, $limit);
        return [ $critical, $rest ];
    }

    /**
     * Determine if Node or npx is available.
     *
     * @return bool True if Node tooling is available, false otherwise.
     */
    public static function has_node_capability(): bool {
        $cached = \get_transient('ae_css_has_node');
        if ($cached !== false) {
            return $cached === '1';
        }
        $has = false;
        foreach (['node', 'npx'] as $cmd) {
            $out = \shell_exec($cmd . ' --version 2>&1');
            if (\is_string($out) && $out !== '') {
                $has = true;
                break;
            }
        }
        \set_transient('ae_css_has_node', $has ? '1' : '0', DAY_IN_SECONDS);
        return $has;
    }

    /**
     * Detect if current request is a WooCommerce context.
     *
     * @return bool Whether WooCommerce styles are needed.
     */
    public static function is_woocommerce_context(): bool {
        if (!\class_exists('WooCommerce')) {
            return false;
        }
        if (function_exists('is_woocommerce') && \is_woocommerce()) {
            return true;
        }
        if (function_exists('is_cart') && \is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && \is_checkout()) {
            return true;
        }
        if (function_exists('is_account_page') && \is_account_page()) {
            return true;
        }
        return false;
    }

    /**
     * Detect if current request is an Elementor context.
     *
     * @return bool Whether Elementor assets are required.
     */
    public static function is_elementor_context(): bool {
        if (!\did_action('elementor/loaded')) {
            return false;
        }
        if (\is_admin()) {
            return true;
        }
        if (\is_singular()) {
            $post_id = \get_the_ID();
            if ($post_id && \class_exists('Elementor\\Plugin')) {
                $db = \Elementor\Plugin::$instance->db;
                if (\method_exists($db, 'is_built_with_elementor')) {
                    return $db->is_built_with_elementor($post_id);
                }
            }
        }
        return false;
    }
}

AE_CSS_Optimizer::bootstrap();
