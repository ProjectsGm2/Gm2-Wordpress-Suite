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
     * Count of scripts dequeued.
     *
     * @var int
     */
    public static int $dequeued = 0;

    /**
     * Count of handles marked for lazy loading.
     *
     * @var int
     */
    public static int $lazy = 0;

    /**
     * Count of polyfill loads.
     *
     * @var int
     */
    public static int $polyfills = 0;

    /**
     * Count of jQuery removals.
     *
     * @var int
     */
    public static int $jquery = 0;

    /**
     * Recorded script sizes in bytes.
     *
     * @var array<string,int>
     */
    public static array $sizes = [];

    /**
     * Bootstrap the manager.
     */
    public static function init(): void {
        if (ae_seo_js_safe_mode()) {
            self::$disabled = true;
            return;
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
        add_action('wp_enqueue_scripts', [ __CLASS__, 'audit_third_party' ], PHP_INT_MAX);
        add_filter('gm2_third_party_allowed', [ __CLASS__, 'filter_disabled' ], 10, 2);
        add_filter('script_loader_tag', [ $this, 'ae_seo_script_loader_tag' ], 10, 3);
        add_action('send_headers', [ __CLASS__, 'send_server_timing' ], 999);
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
     * Dequeue scripts disallowed by filter hook.
     */
    public static function audit_third_party(): void {
        if (self::is_disabled()) {
            return;
        }
        $scripts = wp_scripts();
        if (!$scripts) {
            return;
        }
        $threshold = (int) apply_filters(
            'ae_seo/js/size_threshold',
            (int) get_option('ae_js_size_threshold', 0)
        );
        foreach ($scripts->queue as $handle) {
            $registered = $scripts->registered[$handle] ?? null;
            if (!$registered) {
                continue;
            }
            $src   = $registered->src;
            $size  = self::get_script_size($src);
            if ($size > 0) {
                self::$sizes[$handle] = $size;
                if ($threshold > 0 && $size > $threshold) {
                    ae_seo_js_log('large ' . $handle . ' size=' . $size);
                    $auto = apply_filters(
                        'ae_seo/js/auto_dequeue_large',
                        get_option('ae_js_auto_dequeue_large', '0') === '1',
                        $handle,
                        $size,
                        $src
                    );
                    if ($auto && !is_admin()) {
                        wp_dequeue_script($handle);
                        self::$dequeued++;
                        continue;
                    }
                }
            }

            $allow = apply_filters('gm2_third_party_allowed', true, $handle, $src);
            if (!$allow) {
                wp_dequeue_script($handle);
                self::$dequeued++;
            }
        }
    }

    /**
     * Determine script size.
     *
     * @param string $src Script source URL.
     * @return int Size in bytes.
     */
    private static function get_script_size(string $src): int {
        if ($src === '') {
            return 0;
        }
        // Relative path.
        if (strpos($src, '//') === false) {
            $path = ABSPATH . ltrim($src, '/');
            return file_exists($path) ? (int) filesize($path) : 0;
        }
        $host      = wp_parse_url($src, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($host === $home_host) {
            $path = ABSPATH . ltrim(wp_parse_url($src, PHP_URL_PATH) ?? '', '/');
            return file_exists($path) ? (int) filesize($path) : 0;
        }
        $resp = wp_remote_head($src);
        if (is_wp_error($resp)) {
            return 0;
        }
        $len = (int) wp_remote_retrieve_header($resp, 'content-length');
        return $len > 0 ? $len : 0;
    }

    /**
     * Default filter using saved disabled handles.
     */
    public static function filter_disabled(bool $allow, string $handle): bool {
        $disabled = get_option('gm2_third_party_disabled', []);
        if (is_array($disabled) && in_array($handle, $disabled, true)) {
            return false;
        }
        return $allow;
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

        if ($handle !== '') {
            $allow = apply_filters('ae_seo/js/enqueue_decision', true, $handle, $ctx);
            if (!$allow) {
                ae_seo_js_log('skip ' . $handle);
                return;
            }
            $file = str_replace('ae-', '', $handle) . '.js';
            ae_seo_register_asset($handle, $file);
            wp_enqueue_script($handle);
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

        ae_seo_register_asset('ae-main-modern', 'ae-main.modern.js');
        wp_enqueue_script('ae-main-modern');
        wp_script_add_data('ae-main-modern', 'type', 'module');
        $this->ae_seo_localize_replacements('ae-main-modern');

        if (get_option('ae_js_nomodule_legacy', '0') === '1') {
            ae_seo_register_asset('ae-main-legacy', 'ae-main.legacy.js');
            wp_enqueue_script('ae-main-legacy');
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
            self::$lazy++;
            ae_seo_js_log('lazy ' . $handle);
        }
        return $tag;
    }

    /**
     * Output server-timing header with script decision counters.
     */
    public static function send_server_timing(): void {
        $d = self::$dequeued;
        $l = self::$lazy;
        $p = self::$polyfills;
        $j = self::$jquery;
        header('Server-Timing: ae-dequeued=' . $d . ', ae-lazy=' . $l . ', ae-polyfills=' . $p . ', ae-jquery=' . $j);
    }
}

/**
 * Log helper for JS optimizer.
 *
 * @param string $message Log message.
 */
function ae_seo_js_log(string $message): void {
    static $messages = [];
    static $hooked   = false;

    if (get_option('ae_js_console_log', '0') === '1') {
        $messages[] = $message;
        if (!$hooked) {
            add_action(
                'wp_footer',
                function () use (&$messages) {
                    if (empty($messages)) {
                        return;
                    }
                    echo '<script>(function(){var msgs=' . wp_json_encode($messages) . ';msgs.forEach(function(msg){console.info("[AE-SEO]", msg);});})();</script>';
                }
            );
            $hooked = true;
        }
    }

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
