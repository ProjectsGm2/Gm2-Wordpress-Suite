<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Manager')) {
    return;
}

/**
 * Manage JavaScript loading and replacements.
 */
class AE_SEO_JS_Manager {
    /**
     * Map of script replacements.
     *
     * @var array
     */
    private array $map = [];

    /**
     * Bootstrap the manager.
     */
    public static function init(): void {
        if (isset($_GET['aejs']) && $_GET['aejs'] === 'off') {
            return;
        }
        (new self())->run();
    }

    /**
     * Load configuration and set up hooks.
     */
    public function run(): void {
        $this->map = $this->ae_seo_load_map();
        add_action('wp_enqueue_scripts', [ $this, 'ae_seo_enqueue_scripts' ], 0);
        add_filter('script_loader_tag', [ $this, 'ae_seo_script_loader_tag' ], 10, 3);
    }

    /**
     * Load script replacement map with filter override.
     *
     * @return array
     */
    private function ae_seo_load_map(): array {
        $path = dirname(__DIR__) . '/config/script-map.json';
        $map  = [];
        if (!is_readable($path)) {
            return apply_filters('ae_seo/js/replacements', $map);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            ae_seo_js_log('Unable to read script-map.json');
            return apply_filters('ae_seo/js/replacements', $map);
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                $map = $data;
            }
        } catch (\JsonException $e) {
            ae_seo_js_log('Invalid script-map.json: ' . $e->getMessage());
        }
        return apply_filters('ae_seo/js/replacements', $map);
    }

    /**
     * Enqueue scripts based on replacement map.
     *
     * @return void
     */
    public function ae_seo_enqueue_scripts(): void {
        foreach ($this->map as $handle => $config) {
            $src = '';
            if (is_string($config)) {
                $src = $config;
                $config = [];
            } elseif (is_array($config) && isset($config['src'])) {
                $src = (string) $config['src'];
            }
            if ($src === '') {
                continue;
            }
            $allow = apply_filters('ae_seo/js/enqueue_decision', true, $handle, $src);
            if (!$allow) {
                ae_seo_js_log('skip ' . $handle);
                continue;
            }
            $deps = isset($config['deps']) && is_array($config['deps']) ? $config['deps'] : [];
            $ver  = $config['ver'] ?? null;
            $foot = $config['in_footer'] ?? true;
            wp_enqueue_script($handle, $src, $deps, $ver, $foot);
            ae_seo_js_log('enqueue ' . $handle);
        }
    }

    /**
     * Maybe adjust script tag for lazy-loading.
     *
     * @param string $tag    Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src    Script source.
     * @return string
     */
    public function ae_seo_script_loader_tag(string $tag, string $handle, string $src): string {
        $lazy = false;
        if (isset($this->map[$handle]) && is_array($this->map[$handle])) {
            $lazy = !empty($this->map[$handle]['lazy']);
        }
        $lazy = apply_filters('ae_seo/js/should_lazy_load', $lazy, $handle, $src);
        if ($lazy) {
            $tag = str_replace('<script ', '<script data-aejs="lazy" ', $tag);
            ae_seo_js_log('lazy ' . $handle);
        }
        return $tag;
    }
}

/**
 * Log helper for JS optimizer.
 *
 * @param string $message Log message.
 */
function ae_seo_js_log(string $message): void {
    if (get_option('ae_js_debug_log', '0') !== '1') {
        return;
    }
    $dir = WP_CONTENT_DIR . '/ae-seo/logs';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    $file = $dir . '/js-optimizer.log';
    $time = gmdate('Y-m-d H:i:s');
    file_put_contents($file, '[' . $time . '] ' . $message . PHP_EOL, FILE_APPEND);
}

AE_SEO_JS_Manager::init();
