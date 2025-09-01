<?php
/**
 * Defer JavaScript loading.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds defer/async attributes to scripts with dependency awareness.
 */
class AE_SEO_Defer_JS {
    /**
     * Per-handle attribute overrides.
     *
     * @var array
     */
    private array $overrides = [];

    /**
     * Cached resolution map.
     *
     * @var array
     */
    private array $resolved = [];

    /**
     * Attributes provided by gm2_script_attributes.
     *
     * @var array
     */
    private array $existing = [];

    /**
     * Constructor.
     */
    public function __construct() {
        add_option('gm2_defer_js_enabled', '1');
        add_option('gm2_defer_js_allowlist', '');
        add_option('gm2_defer_js_denylist', '');
        add_option('gm2_defer_js_overrides', []);
        add_option('ae_seo_ro_defer_allow_domains', '');
        add_option('ae_seo_ro_defer_deny_domains', '');

        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
    }

    /**
     * Set up hooks for deferring scripts.
     *
     * @return void
     */
    public function setup() {
        if (get_option('gm2_defer_js_enabled', '1') !== '1') {
            return;
        }

        add_filter('script_loader_tag', [ $this, 'filter' ], 20, 3);
    }

    /**
     * Filter script tag attributes.
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @param string $src    The script source.
     * @return string
     */
    public function filter($tag, $handle, $src) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WordPress filter signature.
        $allow = array_filter(array_map('trim', explode(',', get_option('gm2_defer_js_allowlist', ''))));
        $deny  = array_filter(array_map('trim', explode(',', get_option('gm2_defer_js_denylist', ''))));

        $allow_domains = array_filter(array_map('trim', explode(',', get_option('ae_seo_ro_defer_allow_domains', ''))));
        $deny_domains  = array_filter(array_map('trim', explode(',', get_option('ae_seo_ro_defer_deny_domains', ''))));

        $host = wp_parse_url($src, PHP_URL_HOST);
        $path = wp_parse_url($src, PHP_URL_PATH);
        $analytics_hosts = [
            'www.googletagmanager.com' => true,
            'www.google-analytics.com' => true,
            'www.google.com' => '/recaptcha',
            'www.gstatic.com' => '/recaptcha',
        ];

        if ($host && in_array($host, $deny_domains, true)) {
            return $tag;
        }

        $is_analytics = false;
        if ($host && isset($analytics_hosts[$host])) {
            $prefix = $analytics_hosts[$host];
            $is_analytics = $prefix === true || ($path && strpos($path, $prefix) === 0);
        }

        if ($host && (in_array($host, $allow_domains, true) || $is_analytics)) {
            $tag = $this->remove_attr($tag);
            return str_replace('<script ', '<script async defer ', $tag);
        }

        if (!empty($allow) && !in_array($handle, $allow, true)) {
            return $this->remove_attr($tag);
        }

        if (in_array($handle, $deny, true)) {
            return $this->remove_attr($tag);
        }

        $this->existing  = get_option('gm2_script_attributes', []);
        if (isset($this->existing[$handle])) {
            // Already handled by gm2_script_attributes.
            return $tag;
        }

        $this->overrides = get_option('gm2_defer_js_overrides', []);
        $this->resolved  = [];

        $attr = $this->determine_attribute($handle);

        if ($attr === 'async' || $attr === 'defer') {
            $tag = $this->remove_attr($tag);
            if (strpos($tag, $attr) === false) {
                $tag = str_replace('<script ', '<script ' . $attr . ' ', $tag);
            }
        } else {
            $tag = $this->remove_attr($tag);
        }

        return $tag;
    }

    /**
     * Remove async/defer attributes from a tag.
     *
     * @param string $tag The original tag.
     * @return string
     */
    private function remove_attr(string $tag): string {
        $tag = str_replace(' async', '', $tag);
        $tag = str_replace(' defer', '', $tag);
        return $tag;
    }

    /**
     * Determine attribute for a handle considering dependencies.
     *
     * @param string $handle Script handle.
     * @return string Attribute: async, defer, blocking or none.
     */
    private function determine_attribute(string $handle): string {
        if (isset($this->resolved[$handle])) {
            return $this->resolved[$handle];
        }

        if (isset($this->existing[$handle])) {
            return $this->resolved[$handle] = $this->existing[$handle];
        }

        $this->resolved[$handle] = 'none';

        $attr = $this->overrides[$handle] ?? 'defer';
        if ($attr === 'blocking') {
            return $this->resolved[$handle] = 'blocking';
        }

        global $wp_scripts;
        if (!$wp_scripts instanceof \WP_Scripts) {
            $wp_scripts = wp_scripts();
        }
        $registered = $wp_scripts->registered[$handle] ?? null;
        if ($registered && !empty($registered->deps)) {
            foreach ($registered->deps as $dep) {
                $dep_attr = $this->determine_attribute($dep);
                if ($dep_attr === 'blocking') {
                    return $this->resolved[$handle] = 'blocking';
                }
                if ($dep_attr !== 'defer') {
                    return $this->resolved[$handle] = 'none';
                }
            }
        }

        return $this->resolved[$handle] = $attr;
    }
}

