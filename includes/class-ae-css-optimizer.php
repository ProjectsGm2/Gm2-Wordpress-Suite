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
        'flags'                         => [],
        'safelist'                      => '',
        'exclude_handles'               => [],
        'include_above_the_fold_handles'=> [],
        'generate_critical'             => '0',
        'async_load_noncritical'        => '0',
        'critical'                      => [],
        'queue'                         => [],
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
        $this->inject_critical_and_defer();
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
     * Attach hooks for critical CSS printing and async style loading.
     *
     * @return void
     */
    public function inject_critical_and_defer(): void {
        if (\is_admin()) {
            return;
        }
        add_action('wp_head', [ $this, 'print_critical_css' ], 1);
        add_filter('style_loader_tag', [ $this, 'filter_style_loader_tag' ], 20, 4);
    }

    /**
     * Output stored critical CSS for the current URL when enabled.
     *
     * @return void
     */
    public function print_critical_css(): void {
        $url      = \home_url(\add_query_arg([], ''));
        $critical = $this->get_critical_css($url);
        if ($critical !== '' && !empty($this->settings['generate_critical'])) {
            echo '<style id="ae-critical-css">' . $critical . '</style>';
        }
    }

    /**
     * Filter a stylesheet tag to load non-critical CSS asynchronously.
     *
     * @param string $html   Original tag.
     * @param string $handle Handle of the style.
     * @param string $href   Stylesheet URL.
     * @param string $media  Media attribute.
     * @return string Filtered tag.
     */
    public function filter_style_loader_tag(string $html, string $handle, string $href, string $media): string {
        $excluded = $this->settings['exclude_handles'] ?? [];
        if (empty($this->settings['async_load_noncritical']) || in_array($handle, $excluded, true) || $this->should_bypass_async()) {
            return $html;
        }
        $href = \esc_url($href);
        return '<link rel="preload" as="style" href="' . $href . '" onload="this.onload=null;this.rel=\'stylesheet\'">'
            . '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>';
    }

    /**
     * Determine whether async loading should be bypassed for debugging.
     *
     * @return bool
     */
    private function should_bypass_async(): bool {
        if (!\is_user_logged_in() || !\current_user_can('manage_options')) {
            return false;
        }
        if (empty($_GET['ae-css-debug']) || $_GET['ae-css-debug'] !== '1') {
            return false;
        }
        $nonce = $_GET['_wpnonce'] ?? '';
        return \wp_verify_nonce($nonce, 'ae-css-debug');
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
