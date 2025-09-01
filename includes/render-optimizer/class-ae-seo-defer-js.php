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
     * Handles referenced by inline scripts.
     *
     * @var array
     */
    private array $inline_block = [];

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
        add_option('ae_seo_ro_defer_respect_in_footer', '0');
        add_option('ae_seo_ro_defer_preserve_jquery', '1');

        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
        add_action('wp_head', [ $this, 'start_head_buffer' ], 0);
        add_action('wp_head', [ $this, 'end_head_buffer' ], PHP_INT_MAX);
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
        if ($this->is_module_handle($handle, $tag)) {
            return $tag;
        }

        $allow = array_filter(array_map('trim', explode(',', get_option('gm2_defer_js_allowlist', ''))));
        $deny  = array_filter(array_map('trim', explode(',', get_option('gm2_defer_js_denylist', ''))));

        $allow_domains = array_filter(array_map('trim', explode(',', get_option('ae_seo_ro_defer_allow_domains', ''))));
        $deny_domains  = array_filter(array_map('trim', explode(',', get_option('ae_seo_ro_defer_deny_domains', ''))));
        $respect_footer = get_option('ae_seo_ro_defer_respect_in_footer', '0') === '1';

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

        if ($respect_footer) {
            global $wp_scripts;
            if (!$wp_scripts instanceof \WP_Scripts) {
                $wp_scripts = wp_scripts();
            }
            $group = $wp_scripts->get_data($handle, 'group');
            if ((int) $group === 1 && !in_array($handle, $allow, true)) {
                return $tag;
            }
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
        if ($respect_footer) {
            $this->build_inline_map();
            foreach ($this->inline_block as $block_handle => $_) {
                $this->overrides[$block_handle] = 'blocking';
            }
        }
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
     * Begin buffering wp_head output for jQuery detection.
     *
     * @return void
     */
    public function start_head_buffer(): void {
        if (get_option('ae_seo_ro_defer_preserve_jquery', '1') !== '1') {
            return;
        }

        ob_start([ $this, 'process_head_buffer' ]);
    }

    /**
     * End buffering and output processed wp_head content.
     *
     * @return void
     */
    public function end_head_buffer(): void {
        if (get_option('ae_seo_ro_defer_preserve_jquery', '1') !== '1') {
            return;
        }

        if (ob_get_length() !== false) {
            ob_end_flush();
        }
    }

    /**
     * Inspect buffered head content for early jQuery usage.
     *
     * @param string $buffer Buffered head output.
     * @return string
     */
    public function process_head_buffer(string $buffer): string {
        $pattern = '#<script(?P<attr>[^>]*)>(?P<code>.*?)</script>#is';
        $offset  = 0;
        $needs_jquery = false;

        while (preg_match($pattern, $buffer, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $tag    = $m[0][0];
            $attr   = $m['attr'][0];
            $code   = $m['code'][0];
            $offset = $m[0][1] + strlen($tag);

            if (stripos($attr, 'src=') !== false) {
                if (stripos($attr, 'jquery') !== false) {
                    break;
                }
                continue;
            }

            if (preg_match('/\\bjQuery\\b|\$\s*\(/', $code)) {
                $needs_jquery = true;
                break;
            }
        }

        if ($needs_jquery) {
            add_filter('option_gm2_defer_js_denylist', [ $this, 'add_jquery_denylist' ]);
            $buffer = preg_replace_callback(
                '/<script[^>]*src=["\'][^"\']*jquery[^"\']*["\'][^>]*>/',
                function ($m) {
                    return $this->remove_attr($m[0]);
                },
                $buffer
            );
        }

        return $buffer;
    }

    /**
     * Add jQuery handles to the denylist for the current request.
     *
     * @param string $value Existing denylist value.
     * @return string
     */
    public function add_jquery_denylist($value): string {
        $handles = [ 'jquery', 'jquery-core', 'jquery-migrate' ];
        $list    = array_filter(array_map('trim', explode(',', (string) $value)));
        $list    = array_unique(array_merge($list, $handles));
        return implode(',', $list);
    }

    /**
     * Determine if a handle should remain blocking due to module/nomodule usage.
     *
     * @param string $handle Script handle.
     * @param string $tag    Optional script tag for attribute inspection.
     * @return bool
     */
    private function is_module_handle(string $handle, string $tag = ''): bool {
        $special = apply_filters('ae_seo_defer_js_blocking_handles', []);
        if (in_array($handle, $special, true)) {
            return true;
        }

        if ($tag !== '' && (stripos($tag, 'type="module"') !== false || stripos($tag, "type='module'") !== false || stripos($tag, ' nomodule') !== false)) {
            return true;
        }

        global $wp_scripts;
        if (!$wp_scripts instanceof \WP_Scripts) {
            $wp_scripts = wp_scripts();
        }

        $type     = $wp_scripts->get_data($handle, 'type');
        $nomodule = $wp_scripts->get_data($handle, 'nomodule');

        return $type === 'module' || !empty($nomodule);
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
     * Build map of handles referenced by inline scripts in footer queues.
     *
     * @return void
     */
    private function build_inline_map(): void {
        if (!empty($this->inline_block)) {
            return;
        }

        global $wp_scripts;
        if (!$wp_scripts instanceof \WP_Scripts) {
            $wp_scripts = wp_scripts();
        }

        $handles = array_unique(array_merge((array) $wp_scripts->in_footer, (array) $wp_scripts->print_inline_script));
        if (empty($handles)) {
            return;
        }

        $registered_handles = array_keys($wp_scripts->registered);

        foreach ($handles as $h) {
            $data   = $wp_scripts->get_data($h, 'data');
            $before = (array) $wp_scripts->get_data($h, 'before');
            $after  = (array) $wp_scripts->get_data($h, 'after');
            $codes  = array_merge($before, $after);
            if ($data) {
                $codes[] = $data;
            }
            foreach ($codes as $code) {
                foreach ($registered_handles as $handle) {
                    if (strpos($code, $handle) !== false) {
                        $this->inline_block[$handle] = true;
                    }
                }
            }
        }
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

        if ($this->is_module_handle($handle)) {
            return $this->resolved[$handle] = 'defer';
        }

        if (isset($this->inline_block[$handle])) {
            return $this->resolved[$handle] = 'blocking';
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

