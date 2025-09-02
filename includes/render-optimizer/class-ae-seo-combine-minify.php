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
        $local = [];
        foreach ($handles as $h) {
            $obj = $wp_styles->registered[$h] ?? null;
            if (!$obj) {
                continue;
            }
            if ($this->is_local($obj->src)) {
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
        $local = [];
        foreach ($handles as $h) {
            $obj = $wp_scripts->registered[$h] ?? null;
            if (!$obj) {
                continue;
            }
            if ($this->is_local($obj->src)) {
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

        $handle = 'ae-seo-combined-js';
        $in_footer = isset($wp_scripts->groups[$local[0]]) && $wp_scripts->groups[$local[0]] > 0;
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
        $dir = WP_CONTENT_DIR . '/cache/ae-seo/';
        wp_mkdir_p($dir);

        $parts = [];
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

        $key = md5(implode(',', $parts));
        $file = $dir . $key . '.' . $type;
        if (!file_exists($file)) {
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
        return content_url('cache/ae-seo/' . $key . '.' . $type);
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
        $dir = WP_CONTENT_DIR . '/cache/ae-seo/';
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
