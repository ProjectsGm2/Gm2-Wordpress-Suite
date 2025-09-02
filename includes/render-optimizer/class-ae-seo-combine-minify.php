<?php
/**
 * Combine and minify assets.
 *
 * @package Gm2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load composer autoloader for third-party libraries.
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
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
        $media       = [];
        $total       = 0;
        $file_limit  = (int) get_option('ae_seo_ro_combine_file_kb', 60) * 1024;
        $bundle_cap  = (int) get_option('ae_seo_ro_combine_css_kb', 300) * 1024;

        foreach ($handles as $h) {
            $obj = $wp_styles->registered[$h] ?? null;
            if (!$obj) {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'not_registered',
                ]);
                continue;
            }
            if ($this->is_excluded($h, $obj->src)) {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'excluded',
                ]);
                continue;
            }
            if (!empty($obj->extra['integrity']) || !empty($obj->extra['crossorigin'])) {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'integrity',
                ]);
                continue;
            }
            if ($this->is_local($obj->src)) {
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'missing',
                    ]);
                    continue;
                }
                $size = filesize($path);
                if ($size === false) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'filesize',
                    ]);
                    continue;
                }
                if ($size > $file_limit) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'file_limit',
                    ]);
                    continue;
                }
                if ($total + $size > $bundle_cap) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'bundle_cap',
                    ]);
                    continue;
                }
                $total   += $size;
                $local[] = $h;
                $media[] = $obj->args ? $obj->args : 'all';
            } else {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'external',
                ]);
            }
        }

        if (count($local) < 2) {
            foreach ($local as $h) {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'not_enough_files',
                ]);
            }
            return $handles;
        }

        $src = $this->build_combined_file($local, 'css');
        if (!$src) {
            foreach ($local as $h) {
                AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'build_failed',
                ]);
            }
            return $handles;
        }

        foreach ($local as $h) {
            AE_SEO_Optimizer_Diagnostics::add('combine_css', [
                'handle' => $h,
                'bundle' => $src,
                'reason' => 'combined',
            ]);
        }

        $unique_media = array_unique(array_map(function ($m) {
            return $m ?: 'all';
        }, $media));
        $bundle_media = (count($unique_media) === 1) ? $unique_media[0] : 'all';

        $handle = 'ae-seo-combined-css';
        wp_enqueue_style($handle, $src, [], null, $bundle_media);

        if (class_exists('AE_SEO_Render_Optimizer') && class_exists('AE_SEO_Critical_CSS')) {
            $method = AE_SEO_Render_Optimizer::get_option(AE_SEO_Critical_CSS::OPTION_ASYNC_METHOD, 'preload_onload');
            if ($method === 'preload_onload') {
                add_filter('style_loader_tag', function ($html, $handle_tag, $href, $media_attr) use ($handle) {
                    if ($handle_tag !== $handle) {
                        return $html;
                    }
                    return sprintf(
                        '<link rel="preload" as="style" href="%s" media="%s" onload="this.onload=null;this.rel=\'stylesheet\'"><noscript><link rel="stylesheet" href="%s" media="%s"></noscript>',
                        esc_url($href),
                        esc_attr($media_attr ?: 'all'),
                        esc_url($href),
                        esc_attr($media_attr ?: 'all')
                    );
                }, 10, 4);
            }
        }
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
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'not_registered',
                ]);
                continue;
            }
            if ($this->is_excluded($h, $obj->src)) {
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'excluded',
                ]);
                continue;
            }
            $current_group = $wp_scripts->groups[$h] ?? 0;
            if ($group === null) {
                $group = $current_group;
            }
            if ($current_group !== $group) {
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'group_mismatch',
                ]);
                continue;
            }
            if ($this->is_local($obj->src)) {
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'missing',
                    ]);
                    continue;
                }
                $size = filesize($path);
                if ($size === false) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'filesize',
                    ]);
                    continue;
                }
                if ($size > $file_limit) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'file_limit',
                    ]);
                    continue;
                }
                if ($total + $size > $bundle_cap) {
                    AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                        'handle' => $h,
                        'bundle' => '',
                        'reason' => 'bundle_cap',
                    ]);
                    continue;
                }
                $total   += $size;
                $local[] = $h;
            } else {
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'external',
                ]);
            }
        }

        if (count($local) < 2) {
            foreach ($local as $h) {
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'not_enough_files',
                ]);
            }
            return $handles;
        }

        $src = $this->build_combined_file($local, 'js');
        if (!$src) {
            foreach ($local as $h) {
                AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                    'handle' => $h,
                    'bundle' => '',
                    'reason' => 'build_failed',
                ]);
            }
            return $handles;
        }

        foreach ($local as $h) {
            AE_SEO_Optimizer_Diagnostics::add('combine_js', [
                'handle' => $h,
                'bundle' => $src,
                'reason' => 'combined',
            ]);
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
        $parts = [];
        if ($type === 'css') {
            global $wp_styles;
            $minifier = new \MatthiasMullie\Minify\CSS();
            foreach ($handles as $h) {
                $obj = $wp_styles->registered[$h];
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $parts[] = $path . '|' . filemtime($path);
                $minifier->add($path);
            }
        } else {
            global $wp_scripts;
            $minifier = new \MatthiasMullie\Minify\JS();
            foreach ($handles as $h) {
                $obj = $wp_scripts->registered[$h];
                $path = $this->local_path($obj->src);
                if (!file_exists($path)) {
                    continue;
                }
                $parts[] = $path . '|' . filemtime($path);
                $minifier->add($path);
            }
        }

        if (empty($parts)) {
            return '';
        }

        $key      = md5(implode(',', $parts));
        $upload   = wp_upload_dir();
        $dir      = trailingslashit($upload['basedir']) . 'ae-seo/optimizer/' . $key . '/';
        $filename = ($type === 'css') ? 'app.css' : 'app.js';
        $file     = $dir . $filename;
        if (!file_exists($file)) {
            wp_mkdir_p($dir);
            $minifier->minify($file);
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
