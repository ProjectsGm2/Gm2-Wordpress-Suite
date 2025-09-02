<?php
/**
 * Handles critical CSS loading.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output critical CSS for front-end.
 */
class AE_SEO_Critical_CSS {
    /**
     * Option names.
     */
    public const OPTION_ENABLE           = 'ae_seo_ro_enable_critical_css';
    public const OPTION_STRATEGY         = 'ae_seo_ro_critical_strategy';
    public const OPTION_CSS_MAP          = 'ae_seo_ro_critical_css_map';
    public const OPTION_ASYNC_METHOD     = 'ae_seo_ro_async_css_method';
    public const OPTION_EXCLUSIONS       = 'ae_seo_ro_critical_css_exclusions';
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
    }

    /**
     * Set up hooks for critical CSS handling.
     *
     * @return void
     */
    public function setup() {
        if ($this->is_excluded()) {
            AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                'handle' => '',
                'bundle' => '',
                'reason' => 'request_excluded',
            ]);
            return;
        }

        add_filter('style_loader_tag', [ $this, 'filter_style_tag' ], 10, 4);
        add_action('wp_head', [ $this, 'print_manual_css' ], 1);
        \assert(1 === has_action('wp_head', [ $this, 'print_manual_css' ]));
    }

    /**
     * Output manually supplied critical CSS.
     *
     * @return void
     */
    public function print_manual_css() {
        if (AE_SEO_Render_Optimizer::get_option(self::OPTION_ENABLE, '0') !== '1') {
            return;
        }

        $map = AE_SEO_Render_Optimizer::get_option(self::OPTION_CSS_MAP, []);
        if (!is_array($map) || empty($map)) {
            return;
        }

        $strategy = AE_SEO_Render_Optimizer::get_option(self::OPTION_STRATEGY, 'per_home_archive_single');
        $key      = '';

        if ($strategy === 'per_url_cache') {
            $key = md5(home_url(add_query_arg([])));
        } else {
            if (is_front_page() || is_home()) {
                $key = 'home';
            } elseif (is_archive()) {
                $key = 'archive';
            } elseif (is_singular()) {
                $key = 'single-' . get_post_type();
            }
        }

        if ($key === '' || empty($map[$key])) {
            return;
        }

        echo '<style id="ae-seo-critical-css">' . $map[$key] . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Replace style tags with critical CSS and preload full CSS.
     *
     * @param string $html   The original HTML tag.
     * @param string $handle The style handle.
     * @param string $href   The style href.
     * @param string $media  The media attribute.
     * @return string
     */
    public function filter_style_tag($html, $handle, $href, $media) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WordPress filter signature.
        if (is_feed() || is_404()) {
            AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                'handle' => $handle,
                'bundle' => '',
                'reason' => 'feed_or_404',
            ]);
            return $html;
        }

        $exclusions = AE_SEO_Render_Optimizer::get_option(self::OPTION_EXCLUSIONS, '');
        $deny       = array_filter(array_map('trim', is_array($exclusions) ? $exclusions : explode(',', $exclusions)));

        $patterns = ['editor', 'dashicons', 'admin-bar', 'woocommerce-inline'];
        foreach ($patterns as $pattern) {
            if (strpos($handle, $pattern) !== false) {
                AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                    'handle' => $handle,
                    'bundle' => '',
                    'reason' => 'pattern',
                ]);
                return $html;
            }
        }

        if (in_array($handle, $deny, true)) {
            AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                'handle' => $handle,
                'bundle' => '',
                'reason' => 'denylist',
            ]);
            return $html;
        }

        if (strpos($html, 'rel="preload"') !== false || strpos($html, "rel='preload'") !== false || strpos($html, 'data-no-async') !== false) {
            AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                'handle' => $handle,
                'bundle' => '',
                'reason' => 'preload_or_noasync',
            ]);
            return $html;
        }

        $store = AE_SEO_Render_Optimizer::get_option(self::OPTION_CSS_MAP, []);
        if (empty($store[$handle])) {
            AE_SEO_Optimizer_Diagnostics::add('critical_css', [
                'handle' => $handle,
                'bundle' => '',
                'reason' => 'no_map',
            ]);
            return $html;
        }

        $critical = $store[$handle];
        $style    = '<style>' . $critical . '</style>';

        $method = AE_SEO_Render_Optimizer::get_option(self::OPTION_ASYNC_METHOD, 'preload_onload');
        if ($method === 'media_print') {
            $async = sprintf(
                '<link rel="stylesheet" href="%s" media="print" onload="this.media=\'all\'">',
                esc_url($href)
            );
        } else {
            $async = sprintf(
                '<link rel="preload" as="style" href="%s" onload="this.onload=null;this.rel=\'stylesheet\'"><noscript><link rel="stylesheet" href="%s"></noscript>',
                esc_url($href),
                esc_url($href)
            );
        }

        AE_SEO_Optimizer_Diagnostics::add('critical_css', [
            'handle' => $handle,
            'bundle' => $href,
            'reason' => 'processed',
        ]);
        return $style . $async;
    }

    /**
     * Determine if current request should bypass critical CSS handling.
     *
     * @return bool
     */
    private function is_excluded() {
        if (is_admin() || is_user_logged_in() || is_admin_bar_showing() || is_feed() || is_preview() || wp_doing_cron()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') {
            return true;
        }

        return false;
    }
}
