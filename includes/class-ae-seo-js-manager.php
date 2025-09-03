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
     * Whether the JS manager should be disabled for this request.
     *
     * @var bool
     */
    private static bool $disabled = false;

    /**
     * Bootstrap the manager.
     */
    public static function init(): void {
        if (isset($_GET['aejs']) && $_GET['aejs'] === 'off') {
            self::$disabled = true;
        }
        (new self())->run();
    }

    /**
     * Determine if the manager is disabled for this request.
     */
    public static function is_disabled(): bool {
        return self::$disabled;
    }

    /**
     * Load configuration and set up hooks.
     */
    public function run(): void {
        if (self::is_disabled()) {
            return;
        }
        $this->map = $this->ae_seo_load_map();
        add_action('wp_enqueue_scripts', [ $this, 'ae_seo_enqueue_scripts' ], 0);
        add_filter('script_loader_tag', [ $this, 'ae_seo_script_loader_tag' ], 10, 3);
    }

    /**
     * Load script map with filter override.
     *
     * @return array
     */
    private function ae_seo_load_map(): array {
        $path = dirname(__DIR__) . '/config/script-map.json';
        $map  = [];
        if (!is_readable($path)) {
            return apply_filters('ae_seo/js/script_map', $map);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            ae_seo_js_log('Unable to read script-map.json');
            return apply_filters('ae_seo/js/script_map', $map);
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                $map = $data;
            }
        } catch (\JsonException $e) {
            ae_seo_js_log('Invalid script-map.json: ' . $e->getMessage());
        }
        return apply_filters('ae_seo/js/script_map', $map);
    }

    /**
     * Retrieve DOM replacement callbacks via filter.
     *
     * @return array
     */
    private function ae_seo_get_replacements(): array {
        $replacements = [];
        return apply_filters('ae_seo/js/replacements', $replacements);
    }

    /**
     * Localize replacements to a script handle when enabled.
     *
     * @param string $handle Script handle.
     * @return void
     */
    private function ae_seo_localize_replacements(string $handle): void {
        if (get_option('ae_js_replacements', '0') !== '1') {
            return;
        }
        $replacements = $this->ae_seo_get_replacements();
        if (empty($replacements)) {
            return;
        }
        wp_localize_script($handle, 'aeSEO', [ 'replacements' => $replacements ]);
    }

    /**
     * Enqueue page-specific bundle or fall back to main script.
     *
     * @return void
     */
    public function ae_seo_enqueue_scripts(): void {
        if (self::is_disabled()) {
            return;
        }

        $ctx       = AE_SEO_JS_Detector::get_current_context();
        $page_type = $ctx['page_type'] ?? '';
        $handle    = $page_type !== '' && isset($this->map[$page_type]) ? (string) $this->map[$page_type] : '';

        $base = GM2_PLUGIN_URL . 'assets/dist/';
        $ver  = defined('GM2_VERSION') ? GM2_VERSION : false;

        if ($handle !== '') {
            $allow = apply_filters('ae_seo/js/enqueue_decision', true, $handle, $ctx);
            if (!$allow) {
                ae_seo_js_log('skip ' . $handle);
                return;
            }
            $file = str_replace('ae-', '', $handle) . '.js';
            wp_enqueue_script($handle, $base . $file, [], $ver, true);
            wp_script_add_data($handle, 'type', 'module');
            $this->ae_seo_localize_replacements($handle);
            ae_seo_js_log('enqueue ' . $handle);
            return;
        }

        $fallback = 'ae-main-modern';
        $allow    = apply_filters('ae_seo/js/enqueue_decision', true, $fallback, $ctx);
        if (!$allow) {
            ae_seo_js_log('skip ' . $fallback);
            return;
        }

        wp_enqueue_script('ae-main-modern', $base . 'ae-main.modern.js', [], $ver, true);
        wp_script_add_data('ae-main-modern', 'type', 'module');
        $this->ae_seo_localize_replacements('ae-main-modern');

        if (get_option('ae_js_nomodule_legacy', '0') === '1') {
            wp_enqueue_script('ae-main-legacy', $base . 'ae-main.legacy.js', [], $ver, true);
            wp_script_add_data('ae-main-legacy', 'nomodule', true);
            $this->ae_seo_localize_replacements('ae-main-legacy');
        }

        ae_seo_js_log('enqueue ' . $fallback);
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
        if (self::is_disabled()) {
            return $tag;
        }
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
    if (AE_SEO_JS_Manager::is_disabled() || get_option('ae_js_debug_log', '0') !== '1') {
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
