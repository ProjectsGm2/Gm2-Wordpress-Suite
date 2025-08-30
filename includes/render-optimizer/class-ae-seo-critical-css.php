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
            return;
        }

        add_filter('style_loader_tag', [ $this, 'filter_style_tag' ], 10, 4);
        add_action('wp_head', [ $this, 'print_manual_css' ], 1);
    }

    /**
     * Output manually supplied critical CSS.
     *
     * @return void
     */
    public function print_manual_css() {
        $map = AE_SEO_Render_Optimizer::get_option(self::OPTION_CSS_MAP, []);
        $css = is_array($map) ? ($map['manual'] ?? '') : '';
        if (empty($css)) {
            return;
        }

        echo '<style id="gm2-critical-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
        $exclusions = AE_SEO_Render_Optimizer::get_option(self::OPTION_EXCLUSIONS, '');
        $deny  = array_filter(array_map('trim', is_array($exclusions) ? $exclusions : explode(',', $exclusions)));

        if (in_array($handle, $deny, true)) {
            return $html;
        }

        $store = AE_SEO_Render_Optimizer::get_option(self::OPTION_CSS_MAP, []);
        if (empty($store[$handle])) {
            return $html;
        }

        $critical = $store[$handle];
        $style    = '<style>' . $critical . '</style>';
        $preload  = sprintf(
            '<link rel="preload" as="style" href="%s" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url($href)
        );

        return $style . $preload;
    }

    /**
     * Determine if current request should bypass critical CSS handling.
     *
     * @return bool
     */
    private function is_excluded() {
        if (is_admin() || is_user_logged_in() || is_feed() || is_preview() || wp_doing_cron()) {
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
