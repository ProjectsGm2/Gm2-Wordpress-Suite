<?php
/**
 * Combine and minify assets.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Combines and minifies CSS/JS assets.
 */
class AE_SEO_Combine_Minify {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'setup' ], 5);
    }

    /**
     * Set up hooks for combining and minifying assets.
     *
     * @return void
     */
    public function setup() {
        if (is_admin() || $this->other_optimizers_active()) {
            return;
        }
        // Skip combining during customizer, builder previews or when caching is disabled.
        if (
            isset($_GET['customize_changeset_uuid']) ||
            isset($_GET['elementor-preview']) ||
            isset($_GET['fl_builder']) ||
            (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE)
        ) {
            return;
        }
        if (get_option('ae_seo_ro_enable_combine_css', '0') === '1') {
            add_filter('print_styles_array', [ $this, 'combine_styles' ], 20);
        }
        if (get_option('ae_seo_ro_enable_combine_js', '0') === '1') {
            add_filter('print_scripts_array', [ $this, 'combine_scripts' ], 20);
        }
    }

    private function other_optimizers_active() {
        $constants = [ 'AUTOPTIMIZE_VERSION', 'W3TC', 'WP_ROCKET_VERSION' ];
        foreach ($constants as $c) {
            if (defined($c)) {
                return true;
            }
        }
        return false;
    }

    public function combine_styles($handles) {
        global $wp_styles;
        $local       = [];
        $total       = 0;
        $file_limit  = (int) get_option('ae_seo_ro_combine_file_kb', 60) * 1024;
        $bundle_cap  = (int) get_option('ae_seo_ro_combine_css_kb', 300) * 1024;

        foreach ($handles as $h) {
            $obj = $wp_styles->registered[$h] ?? null;
            if (!$obj) {
                continue;
            }
            if ($this->is_excluded($h, $obj->src)) {
                continue;
            }
            if ($this->is_local($obj->src)) {
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $size = filesize($path);
                if ($size === false || $size > $file_limit) {
                    continue;
                }
                if ($total + $size > $bundle_cap) {
                    continue;
                }
                $total   += $size;
                $local[] = $h;
            }
        }

        if (count($local) < 2) {
            return $handles;
        }

        $src = $this->build_combined_file($local, 'css');
        if (!$src) {
            return $handles;
        }

        $handle = 'ae-seo-combined-css';
        wp_enqueue_style($handle, $src, [], null);
        foreach ($local as $h) {
            wp_dequeue_style($h);
        }
        $first = $this->first_index($handles, $local);
        $handles = array_values(array_diff($handles, $local));
        array_splice($handles, $first, 0, $handle);
        return $handles;
    }

    public function combine_scripts($handles) {
        global $wp_scripts;
        $local       = [];
        $total       = 0;
        $file_limit  = (int) get_option('ae_seo_ro_combine_file_kb', 60) * 1024;
        $bundle_cap  = (int) get_option('ae_seo_ro_combine_js_kb', 300) * 1024;
        $group       = null;

        foreach ($handles as $h) {
            $obj = $wp_scripts->registered[$h] ?? null;
            if (!$obj) {
                continue;
            }
            if ($this->is_excluded($h, $obj->src)) {
                continue;
            }
            $current_group = $wp_scripts->groups[$h] ?? 0;
            if ($group === null) {
                $group = $current_group;
            }
            if ($current_group !== $group) {
                continue;
            }
            if ($this->is_local($obj->src)) {
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $size = filesize($path);
                if ($size === false || $size > $file_limit) {
                    continue;
                }
                if ($total + $size > $bundle_cap) {
                    continue;
                }
                $total   += $size;
                $local[] = $h;
            }
        }

        if (count($local) < 2) {
            return $handles;
        }

        $src = $this->build_combined_file($local, 'js');
        if (!$src) {
            return $handles;
        }

        $handle   = 'ae-seo-combined-js';
        $in_footer = $group !== null && $group > 0;
        wp_enqueue_script($handle, $src, [], null, $in_footer);
        foreach ($local as $h) {
            wp_dequeue_script($h);
        }
        $first = $this->first_index($handles, $local);
        $handles = array_values(array_diff($handles, $local));
        array_splice($handles, $first, 0, $handle);
        return $handles;
    }

    private function build_combined_file($handles, $type) {
        $parts   = [];
        $content = '';
        if ($type === 'css') {
            global $wp_styles;
            foreach ($handles as $h) {
                $obj = $wp_styles->registered[$h];
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $parts[] = $path . '|' . filemtime($path);
                $content .= file_get_contents($path) . "\n";
            }
            $wrapped = '<style>' . $content . '</style>';
        } else {
            global $wp_scripts;
            foreach ($handles as $h) {
                $obj = $wp_scripts->registered[$h];
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $parts[] = $path . '|' . filemtime($path);
                $content .= file_get_contents($path) . ";\n";
            }
            $wrapped = '<script>' . $content . '</script>';
        }

        if (empty($parts)) {
            return '';
        }

        $key       = md5(implode(',', $parts));
        $upload    = wp_upload_dir();
        $dir       = trailingslashit($upload['basedir']) . 'ae-seo/optimizer/' . $key . '/';
        $filename  = ($type === 'css') ? 'app.css' : 'app.js';
        $file      = $dir . $filename;
        if (!file_exists($file)) {
            wp_mkdir_p($dir);
            if ($type === 'css') {
                add_filter('pre_option_gm2_minify_css', '__return_true');
            } else {
                add_filter('pre_option_gm2_minify_js', '__return_true');
            }
            $min = (new \Gm2\Gm2_SEO_Public())->minify_output($wrapped);
            if ($type === 'css') {
                remove_filter('pre_option_gm2_minify_css', '__return_true');
            } else {
                remove_filter('pre_option_gm2_minify_js', '__return_true');
            }
            $min = preg_replace('#^<' . $type . '>#', '', $min);
            $min = preg_replace('#</' . $type . '>$#', '', $min);
            file_put_contents($file, $min);
        }
        return trailingslashit($upload['baseurl']) . 'ae-seo/optimizer/' . $key . '/' . $filename;
    }

    private function get_list_option($option, $default = '') {
        $value = get_option($option, $default);
        if (is_array($value)) {
            $list = $value;
        } else {
            $list = preg_split('/[\r\n,]+/', (string) $value);
        }
        return array_filter(array_map('trim', $list));
    }

    private function is_excluded($handle, $src) {
        $handles = $this->get_list_option('ae_seo_ro_combine_exclude_handles', 'woocommerce*,elementor*');
        foreach ($handles as $pattern) {
            if (fnmatch($pattern, $handle)) {
                return true;
            }
        }
        $domains = $this->get_list_option('ae_seo_ro_combine_exclude_domains');
        $host    = parse_url($src, PHP_URL_HOST);
        if ($host) {
            foreach ($domains as $pattern) {
                if (fnmatch($pattern, $host)) {
                    return true;
                }
            }
        }
        $regexes = $this->get_list_option('ae_seo_ro_combine_exclude_patterns');
        foreach ($regexes as $regex) {
            $regex = trim($regex);
            if ($regex === '') {
                continue;
            }
            if (@preg_match('#' . $regex . '#', $handle) || @preg_match('#' . $regex . '#', $src)) {
                return true;
            }
        }
        return false;
    }

    private function is_local($src) {
        if (!$src) {
            return false;
        }
        if (strpos($src, '//') === 0) {
            $src = (is_ssl() ? 'https:' : 'http:') . $src;
        }
        if (preg_match('#^https?://#', $src) && strpos($src, home_url()) !== 0) {
            return false;
        }
        return true;
    }

    private function local_path($src) {
        $src = preg_replace('#^https?://[^/]+#', '', $src);
        $src = strtok($src, '?');
        $src = ltrim($src, '/');
        return ABSPATH . $src;
    }

    private function first_index($haystack, $needles) {
        $index = count($haystack);
        foreach ($needles as $n) {
            $pos = array_search($n, $haystack, true);
            if ($pos !== false && $pos < $index) {
                $index = $pos;
            }
        }
        return $index;
    }

    public static function purge_cache() {
        $upload = wp_upload_dir();
        $base   = trailingslashit($upload['basedir']) . 'ae-seo/optimizer/';
        if (!is_dir($base)) {
            return;
        }
        foreach (glob($base . '*', GLOB_ONLYDIR) as $dir) {
            foreach (glob($dir . '/*') as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }
}
