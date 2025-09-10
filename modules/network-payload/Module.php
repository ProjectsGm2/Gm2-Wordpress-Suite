<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Compression.php';

class Module {
    private const OPTION_KEY     = 'gm2_netpayload_settings';
    private const STATS_KEY      = 'gm2_netpayload_stats';
    private const REGEN_STATE_KEY = 'gm2_np_regen_state';

    private static bool $booted = false;
    private static string $page_hook = '';

    private static array $defaults = [
        'nextgen_images'   => true,
        'webp'             => true,
        'avif'             => true,
        'no_originals'     => false,
        'big_image_cap'    => 2560,
        'gzip_detection'   => 'detect',
        'fallback_gzip'    => false,
        'smart_lazyload'   => true,
        'auto_hero'        => true,
        'eager_selectors'  => [],
        'lite_embeds'      => true,
        'asset_budget'     => true,
        'asset_budget_limit' => 1258291,
        'handle_rules'     => [],
    ];

    /**
     * Boot the module once.
     */
    public static function boot(): void {
        if (self::$booted || did_action('gm2_netpayload_boot')) {
            return;
        }
        self::$booted = true;
        do_action('gm2_netpayload_boot');

        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('network_admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_route']);
        add_action('rest_api_init', [Compression::class, 'register_test_route']);
        add_action('admin_notices', [__CLASS__, 'maybe_show_missing_notice']);
        add_action('network_admin_notices', [__CLASS__, 'maybe_show_missing_notice']);
        add_action('gm2_np_regen_batch', [__CLASS__, 'process_regen_batch']);

        if (self::any_feature_enabled()) {
            add_action('init', [__CLASS__, 'maybe_run_features']);
        }
    }

    /** Register option for settings. */
    public static function register_settings(): void {
        register_setting('gm2_netpayload', self::OPTION_KEY, [
            'type'      => 'array',
            'default'   => self::$defaults,
            'show_in_rest' => false,
            'autoload'  => false,
        ]);
    }

    /** Determine if any toggle is enabled. */
    private static function any_feature_enabled(): bool {
        $opts = self::get_settings();
        return !empty(array_filter([
            $opts['nextgen_images'],
            $opts['gzip_detection'] !== 'off',
            $opts['fallback_gzip'],
            $opts['smart_lazyload'],
            $opts['lite_embeds'],
            $opts['asset_budget'],
            !empty($opts['handle_rules']),
        ]));
    }

    /** Placeholder for real feature hooks; gated. */
    public static function maybe_run_features(): void {
        if (!self::any_feature_enabled()) {
            return;
        }
        $opts = self::get_settings();
        if (!empty($opts['nextgen_images'])) {
            add_filter('wp_generate_attachment_metadata', [__CLASS__, 'add_nextgen_variants'], 10, 2);
            add_filter('big_image_size_threshold', [__CLASS__, 'filter_big_image_cap'], 10, 4);
        }
        if (!empty($opts['fallback_gzip'])) {
            add_action('template_redirect', [__CLASS__, 'maybe_start_fallback_gzip'], 0);
        }
        if (!empty($opts['smart_lazyload'])) {
            require_once __DIR__ . '/Lazyload.php';
            Lazyload::boot();
        }
        if (!empty($opts['lite_embeds'])) {
            require_once __DIR__ . '/LiteEmbeds.php';
            LiteEmbeds::boot();
        }
        if (!empty($opts['asset_budget']) || !empty($opts['handle_rules'])) {
            require_once __DIR__ . '/HandleAuditor.php';
            HandleAuditor::boot();
            add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_apply_handle_rules'], 20);
            add_filter('script_loader_tag', [__CLASS__, 'filter_script_tag'], 10, 3);
            add_filter('style_loader_tag', [__CLASS__, 'filter_style_tag'], 10, 4);
        }
        // Actual feature hooks would be added here.
    }

    /** Start PHP output buffering with gzip handler for HTML responses. */
    public static function maybe_start_fallback_gzip(): void {
        if (headers_sent() || is_admin() || defined('REST_REQUEST') || is_feed()) {
            return;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'text/html') === false) {
            return;
        }
        if (function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
        }
    }

    /** Apply handle rules such as dequeuing. */
    public static function maybe_apply_handle_rules(): void {
        $opts  = self::get_settings();
        $rules = $opts['handle_rules'] ?? [];
        if (empty($rules)) {
            return;
        }
        global $wp_scripts, $wp_styles;
        foreach (['scripts' => $wp_scripts, 'styles' => $wp_styles] as $type => $deps) {
            if (empty($rules[$type]) || !$deps) {
                continue;
            }
            foreach ($rules[$type] as $handle => $rule) {
                if (!self::should_dequeue($rule)) {
                    continue;
                }
                if (self::has_dependents($deps, $handle)) {
                    continue;
                }
                if ($type === 'scripts') {
                    wp_dequeue_script($handle);
                } else {
                    wp_dequeue_style($handle);
                }
            }
        }
    }

    /** Filter script tag to add attributes like defer/async. */
    public static function filter_script_tag(string $tag, string $handle, string $src): string {
        $opts  = self::get_settings();
        $rule  = $opts['handle_rules']['scripts'][$handle] ?? null;
        $attr  = $rule['attr'] ?? '';
        if (!$attr) {
            return $tag;
        }
        global $wp_scripts;
        if ($attr === 'async' && self::has_dependents($wp_scripts, $handle)) {
            return $tag;
        }
        if (false === strpos($tag, $attr)) {
            $tag = str_replace('<script ', '<script ' . $attr . ' ', $tag);
        }
        return $tag;
    }

    /** Inline small styles when requested. */
    public static function filter_style_tag(string $html, string $handle, string $href, string $media): string {
        $opts = self::get_settings();
        $rule = $opts['handle_rules']['styles'][$handle]['inline'] ?? false;
        if (empty($rule)) {
            return $html;
        }
        global $wp_styles;
        $src  = $wp_styles->registered[$handle]->src ?? '';
        $src  = self::resolve_src($src, $wp_styles);
        $path = self::local_path($src);
        if (!$path) {
            return $html;
        }
        $size = @filesize($path);
        if ($size === false || $size > 2048) {
            return $html;
        }
        $css = trim((string) @file_get_contents($path));
        if ($css === '') {
            return $html;
        }
        return '<style id="' . esc_attr($handle) . '-inline" media="' . esc_attr($media) . '">' . $css . '</style>';
    }

    private static function should_dequeue(array $rule): bool {
        $dq = $rule['dequeue'] ?? [];
        if (!empty($dq['front_page']) && is_front_page()) {
            return true;
        }
        if (!empty($dq['page_template']) && is_page_template($dq['page_template'])) {
            return true;
        }
        if (!empty($dq['shortcode']) && is_singular()) {
            global $post;
            if ($post && has_shortcode($post->post_content, $dq['shortcode'])) {
                return true;
            }
        }
        return false;
    }

    private static function has_dependents(\WP_Dependencies $deps, string $handle): bool {
        foreach ((array) $deps->queue as $queued) {
            if ($queued === $handle) {
                continue;
            }
            $reg = $deps->registered[$queued] ?? null;
            if ($reg && in_array($handle, (array) ($reg->deps ?? []), true)) {
                return true;
            }
        }
        return false;
    }

    private static function resolve_src(string $src, \WP_Dependencies $deps): string {
        if ($src === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#', $src)) {
            return $src;
        }
        return $deps->base_url . $src;
    }

    private static function local_path(string $url): ?string {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $site = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$host || $host === $site) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            $file = ABSPATH . ltrim($path ?? '', '/');
            return file_exists($file) ? $file : null;
        }
        return null;
    }

    /** Start background regeneration of next-gen images. */
    public static function start_regeneration(bool $only_missing = true): void {
        update_option(self::REGEN_STATE_KEY, ['offset' => 0, 'only_missing' => $only_missing], false);
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('gm2_np_regen_batch');
        } else {
            wp_schedule_single_event(time() + 1, 'gm2_np_regen_batch');
        }
    }

    /** Process a batch of attachment regeneration. */
    public static function process_regen_batch(): void {
        $state = get_option(self::REGEN_STATE_KEY);
        if (!is_array($state)) {
            return;
        }
        $offset       = intval($state['offset'] ?? 0);
        $only_missing = !empty($state['only_missing']);
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'offset'         => $offset,
        ];
        $ids = get_posts($args);
        if (!$ids) {
            delete_option(self::REGEN_STATE_KEY);
            return;
        }
        foreach ($ids as $id) {
            self::regenerate_attachment($id, $only_missing);
            $offset++;
        }
        $state['offset'] = $offset;
        update_option(self::REGEN_STATE_KEY, $state, false);
        if (count($ids) === intval($args['posts_per_page'])) {
            if (function_exists('as_enqueue_async_action')) {
                as_enqueue_async_action('gm2_np_regen_batch');
            } else {
                wp_schedule_single_event(time() + 1, 'gm2_np_regen_batch');
            }
        } else {
            delete_option(self::REGEN_STATE_KEY);
        }
    }

    /** Regenerate next-gen files for a single attachment. */
    private static function regenerate_attachment(int $attachment_id, bool $only_missing): bool {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!is_array($meta)) {
            return false;
        }
        if ($only_missing && !empty($meta['gm2_nextgen']['full'])) {
            $opts = self::get_settings();
            $needs = false;
            foreach (['webp', 'avif'] as $fmt) {
                if (!empty($opts[$fmt]) && empty($meta['gm2_nextgen']['full'][$fmt])) {
                    $needs = true;
                    break;
                }
            }
            if (!$needs) {
                return false;
            }
        }
        $meta = self::add_nextgen_variants($meta, $attachment_id);
        wp_update_attachment_metadata($attachment_id, $meta);
        return true;
    }

    /** Regenerate all images, optionally only those missing variants. */
    public static function regenerate_all_images(bool $only_missing = true, ?callable $progress = null): int {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'offset'         => 0,
        ];
        $count = 0;
        do {
            $ids = get_posts($args);
            foreach ($ids as $id) {
                if (self::regenerate_attachment($id, $only_missing)) {
                    $count++;
                }
                if ($progress) {
                    $progress($id);
                }
            }
            $args['offset'] += $args['posts_per_page'];
        } while ($ids);
        return $count;
    }

    /**
     * Generate next-gen image formats for intermediate sizes.
     */
    public static function add_nextgen_variants(array $metadata, int $attachment_id): array {
        if (empty($metadata['sizes']) || !wp_attachment_is_image($attachment_id)) {
            return $metadata;
        }

        $opts        = self::get_settings();
        $editor_args = ['methods' => ['Imagick', 'GD']];
        $supports_webp = !empty($opts['webp']) && wp_image_editor_supports(['mime_type' => 'image/webp'] + $editor_args);
        $supports_avif = !empty($opts['avif']) && wp_image_editor_supports(['mime_type' => 'image/avif'] + $editor_args);

        if (!$supports_webp && !$supports_avif) {
            return $metadata;
        }

        $file     = get_attached_file($attachment_id);
        $base_dir = dirname($file);

        if (empty($opts['no_originals']) && file_exists($file)) {
            $type     = wp_check_filetype($file);
            $mime     = $type['type'];
            $lossless = true;
            if ($mime === 'image/png') {
                $is_large  = filesize($file) > 500 * 1024;
                $has_alpha = self::png_has_alpha($file);
                if ($is_large && !$has_alpha) {
                    $lossless = false;
                }
            }
            if ($supports_webp) {
                $webp_path = preg_replace('/\.[^\.]+$/', '.webp', $file);
                $editor    = wp_get_image_editor($file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($webp_path, 'image/webp', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen']['full']['webp'] = basename($webp_path);
                }
            }
            $can_avif = $supports_avif;
            if ($mime === 'image/gif' && self::is_animated_gif($file)) {
                $can_avif = false;
            }
            if ($can_avif) {
                $avif_path = preg_replace('/\.[^\.]+$/', '.avif', $file);
                $editor    = wp_get_image_editor($file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($avif_path, 'image/avif', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen']['full']['avif'] = basename($avif_path);
                }
            }
        }

        foreach ($metadata['sizes'] as $size => $data) {
            $size_file = $base_dir . '/' . $data['file'];
            if (!file_exists($size_file)) {
                continue;
            }

            $type      = wp_check_filetype($size_file);
            $mime      = $type['type'];
            $lossless  = true;

            if ($mime === 'image/png') {
                $is_large = filesize($size_file) > 500 * 1024; // >500KB
                $has_alpha = self::png_has_alpha($size_file);
                if ($is_large && !$has_alpha) {
                    $lossless = false;
                }
            }

            if ($supports_webp) {
                $webp_path = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                $editor    = wp_get_image_editor($size_file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($webp_path, 'image/webp', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen'][$size]['webp'] = basename($webp_path);
                }
            }

            $can_avif = $supports_avif;
            if ($mime === 'image/gif' && self::is_animated_gif($size_file)) {
                $can_avif = false;
            }
            if ($can_avif) {
                $avif_path = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                $editor    = wp_get_image_editor($size_file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($avif_path, 'image/avif', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen'][$size]['avif'] = basename($avif_path);
                }
            }
        }

        return $metadata;
    }

    /** Determine if PNG has an alpha channel by reading its header. */
    private static function png_has_alpha(string $file): bool {
        $data = @file_get_contents($file, false, null, 0, 26);
        if ($data === false || strlen($data) < 26) {
            return false;
        }
        return (ord($data[25]) & 4) === 4; // Color type bit for alpha.
    }

    /** Quickly detect if a GIF is animated. */
    private static function is_animated_gif(string $file): bool {
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return false;
        }
        $count = 0;
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // 100KB
            $count += preg_match_all('/\x00\x21\xF9\x04/', $chunk, $m);
        }
        fclose($fh);
        return $count > 1;
    }

    /** Filter the big image size threshold. */
    public static function filter_big_image_cap($threshold, $imagesize = null, $file = '', $attachment_id = 0) {
        $opts = self::get_settings();
        $cap  = intval($opts['big_image_cap']);
        if ($cap > 0) {
            return $cap;
        }
        return $threshold;
    }

    /** Warn if server lacks next-gen support. */
    public static function maybe_show_missing_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $opts = self::get_settings();
        if (empty($opts['nextgen_images'])) {
            return;
        }
        $editor_args = ['methods' => ['Imagick', 'GD']];
        $missing = [];
        if (!empty($opts['webp']) && !wp_image_editor_supports(['mime_type' => 'image/webp'] + $editor_args)) {
            $missing[] = __('WebP', 'gm2-wordpress-suite');
        }
        if (!empty($opts['avif']) && !wp_image_editor_supports(['mime_type' => 'image/avif'] + $editor_args)) {
            $missing[] = __('AVIF', 'gm2-wordpress-suite');
        }
        if (!$missing) {
            return;
        }
        $formats = implode(' & ', $missing);
        echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(
            /* translators: list of image formats */
            __('Your server does not support %s. Responsive srcset will remain intact.', 'gm2-wordpress-suite'),
            $formats
        )) . '</p></div>';
    }

    /** Retrieve settings merged with network defaults. */
    public static function get_settings(): array {
        $network = is_multisite() ? self::get_raw_options(true) : [];
        $site    = self::get_raw_options(false);
        $opts    = wp_parse_args($site, wp_parse_args($network, self::$defaults));
        $opts['big_image_cap'] = intval($opts['big_image_cap']);
        $opts['asset_budget_limit'] = intval($opts['asset_budget_limit']);
        $opts['auto_hero']      = !empty($opts['auto_hero']);
        $opts['lite_embeds']    = !empty($opts['lite_embeds']);
        $opts['eager_selectors'] = array_values(array_filter(array_map('trim', (array)($opts['eager_selectors'] ?? []))));
        $opts['handle_rules']   = is_array($opts['handle_rules'] ?? null) ? $opts['handle_rules'] : [];
        return $opts;
    }

    private static function get_raw_options(bool $network): array {
        $fn   = $network ? 'get_site_option' : 'get_option';
        $opts = $fn(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        return $opts;
    }

    /** Add submenu page for settings. */
    public static function register_admin_page(): void {
        self::$page_hook = add_submenu_page(
            'gm2',
            esc_html__('Network Payload', 'gm2-wordpress-suite'),
            esc_html__('Network Payload', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2_netpayload',
            [__CLASS__, 'render_settings_page']
        );
        add_action('load-' . self::$page_hook, [__CLASS__, 'add_help_tabs']);
    }

    /** Enqueue scripts only on our settings page. */
    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== self::$page_hook) {
            return;
        }
        $base_url = GM2_PLUGIN_URL . 'modules/network-payload/assets/';
        $base_dir = GM2_PLUGIN_DIR . 'modules/network-payload/assets/';
        wp_enqueue_style(
            'gm2-netpayload-admin',
            $base_url . 'admin.css',
            [],
            file_exists($base_dir . 'admin.css') ? filemtime($base_dir . 'admin.css') : GM2_VERSION
        );
        wp_enqueue_script(
            'gm2-netpayload-admin',
            $base_url . 'admin.js',
            [],
            file_exists($base_dir . 'admin.js') ? filemtime($base_dir . 'admin.js') : GM2_VERSION,
            true
        );
        $opts = self::get_settings();
        wp_localize_script('gm2-netpayload-admin', 'gm2Netpayload', [
            'restUrl' => rest_url('gm2/v1/netpayload'),
            'nonce'   => wp_create_nonce('wp_rest'),
            'budget'  => intval($opts['asset_budget_limit'] / 1024),
        ]);

        if (!empty($opts['lite_embeds'])) {
            if (function_exists('ae_seo_register_asset')) {
                ae_seo_register_asset('gm2-lite-embeds', 'lite-embeds.js');
            }
            wp_enqueue_script('gm2-lite-embeds');
        }
    }

    /** Add contextual help tabs. */
    public static function add_help_tabs(): void {
        $screen  = get_current_screen();
        $content = '<p>' . esc_html__('Next‑Gen Images: Generates WebP/AVIF versions; may increase storage usage.', 'gm2-wordpress-suite') . '</p>';
        $content .= '<p>' . esc_html__('Gzip Detection: Checks if server compression is active; turning it off could mask misconfiguration.', 'gm2-wordpress-suite') . '</p>';
        $content .= '<p>' . esc_html__('Smart Lazy Load: Defers offscreen assets for faster paint; excessive use may delay important content.', 'gm2-wordpress-suite') . '</p>';
        $content .= '<p>' . esc_html__('Scripts: Asset budgeting and handle rules can defer or remove scripts but may break site features if misused.', 'gm2-wordpress-suite') . '</p>';
        $screen->add_help_tab([
            'id'      => 'gm2_np_overview',
            'title'   => esc_html__('Overview', 'gm2-wordpress-suite'),
            'content' => $content,
        ]);
    }

    /** Render settings form with tabbed interface. */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'status';

        if (isset($_POST[self::OPTION_KEY])) {
            check_admin_referer('gm2_netpayload_settings');
            $input = wp_unslash($_POST[self::OPTION_KEY]);
            $opts  = self::get_settings();
            $bools = ['nextgen_images', 'webp', 'avif', 'no_originals', 'fallback_gzip', 'smart_lazyload', 'auto_hero', 'lite_embeds', 'asset_budget'];
            foreach ($bools as $b) {
                if (array_key_exists($b, $input)) {
                    $opts[$b] = !empty($input[$b]);
                }
            }
            if (array_key_exists('big_image_cap', $input)) {
                $opts['big_image_cap'] = intval($input['big_image_cap']);
            }
            if (array_key_exists('gzip_detection', $input)) {
                $opts['gzip_detection'] = sanitize_text_field($input['gzip_detection']);
            }
            if (array_key_exists('eager_selectors', $input)) {
                $opts['eager_selectors'] = array_values(array_filter(array_map('sanitize_text_field', explode("\n", $input['eager_selectors']))));
            }
            if (array_key_exists('asset_budget_limit', $input)) {
                $limit_mb = floatval(sanitize_text_field($input['asset_budget_limit']));
                $opts['asset_budget_limit'] = $limit_mb > 0 ? (int)($limit_mb * 1024 * 1024) : self::$defaults['asset_budget_limit'];
            }
            if (array_key_exists('handle_rules', $input) && is_array($input['handle_rules'])) {
                $opts['handle_rules'] = [];
                foreach (['scripts', 'styles'] as $type) {
                    foreach ($input['handle_rules'][$type] ?? [] as $h => $rule) {
                        $h = sanitize_key($h);
                        $entry = [
                            'dequeue' => [
                                'front_page'    => !empty($rule['dequeue_front']),
                                'page_template' => sanitize_text_field($rule['page_template'] ?? ''),
                                'shortcode'     => sanitize_text_field($rule['shortcode'] ?? ''),
                            ],
                        ];
                        if ($type === 'scripts') {
                            $attr          = sanitize_key($rule['attr'] ?? '');
                            $entry['attr'] = in_array($attr, ['defer', 'async'], true) ? $attr : '';
                        } else {
                            $entry['inline'] = !empty($rule['inline']);
                        }
                        if ($entry['dequeue']['front_page'] || $entry['dequeue']['page_template'] || $entry['dequeue']['shortcode'] || !empty($entry['attr']) || !empty($entry['inline'])) {
                            $opts['handle_rules'][$type][$h] = $entry;
                        }
                    }
                }
            }
            if (is_network_admin()) {
                update_site_option(self::OPTION_KEY, $opts, false);
            } else {
                update_option(self::OPTION_KEY, $opts, false);
            }
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }

        if (isset($_POST['gm2_regen_nextgen'])) {
            check_admin_referer('gm2_regen_nextgen');
            self::start_regeneration(true);
            echo '<div class="updated"><p>' . esc_html__('Regeneration started in the background.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $opts  = self::get_settings();
        $stats = get_option(self::STATS_KEY, ['average' => 0, 'budget' => 0]);

        echo '<div class="wrap gm2-netpayload-wrap">';
        echo '<h1>' . esc_html__('Network Payload Optimizer', 'gm2-wordpress-suite') . '</h1>';

        $tabs = [
            'status'      => esc_html__('Status', 'gm2-wordpress-suite'),
            'images'      => esc_html__('Images', 'gm2-wordpress-suite'),
            'compression' => esc_html__('Compression', 'gm2-wordpress-suite'),
            'lazy'        => esc_html__('Lazy Load', 'gm2-wordpress-suite'),
            'scripts'     => esc_html__('Scripts', 'gm2-wordpress-suite'),
        ];
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $class = $tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url   = add_query_arg('tab', $slug, menu_page_url('gm2_netpayload', false));
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ($tab === 'status') {
            echo '<p class="status">' . sprintf(esc_html__('7‑day average payload: %s KB', 'gm2-wordpress-suite'), number_format_i18n(floatval($stats['average']), 2)) . '</p>';
            echo '<form method="post" style="margin-top:1em;">';
            wp_nonce_field('gm2_regen_nextgen');
            echo '<input type="hidden" name="gm2_regen_nextgen" value="1" />';
            submit_button(esc_html__('Regenerate Next‑Gen Images', 'gm2-wordpress-suite'), 'secondary');
            echo '</form>';
            Compression::render_panel();
        } elseif ($tab === 'images') {
            echo '<form method="post">';
            wp_nonce_field('gm2_netpayload_settings');
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th scope="row">' . esc_html__('Next‑Gen Images', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[nextgen_images]" value="1" ' . checked($opts['nextgen_images'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Enable WebP', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[webp]" value="1" ' . checked($opts['webp'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Enable AVIF', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[avif]" value="1" ' . checked($opts['avif'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__("Don't convert originals", 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[no_originals]" value="1" ' . checked($opts['no_originals'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Big image cap', 'gm2-wordpress-suite') . '</th><td><input type="number" name="' . esc_attr(self::OPTION_KEY) . '[big_image_cap]" value="' . esc_attr(intval($opts['big_image_cap'])) . '" /></td></tr>';
            echo '</table>';
            submit_button();
            echo '</form>';
        } elseif ($tab === 'compression') {
            echo '<form method="post">';
            wp_nonce_field('gm2_netpayload_settings');
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th scope="row">' . esc_html__('Gzip Detection', 'gm2-wordpress-suite') . '</th><td><select name="' . esc_attr(self::OPTION_KEY) . '[gzip_detection]"><option value="detect" ' . selected($opts['gzip_detection'], 'detect', false) . '>' . esc_html__('Detect', 'gm2-wordpress-suite') . '</option><option value="off" ' . selected($opts['gzip_detection'], 'off', false) . '>' . esc_html__('Off', 'gm2-wordpress-suite') . '</option></select></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Fallback Gzip', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[fallback_gzip]" value="1" ' . checked($opts['fallback_gzip'], true, false) . ' /></td></tr>';
            echo '</table>';
            submit_button();
            echo '</form>';
        } elseif ($tab === 'lazy') {
            echo '<form method="post">';
            wp_nonce_field('gm2_netpayload_settings');
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th scope="row">' . esc_html__('Smart Lazyload', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[smart_lazyload]" value="1" ' . checked($opts['smart_lazyload'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Auto Hero', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[auto_hero]" value="1" ' . checked($opts['auto_hero'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Eager Selectors', 'gm2-wordpress-suite') . '</th><td><textarea name="' . esc_attr(self::OPTION_KEY) . '[eager_selectors]" rows="3" cols="50">' . esc_textarea(implode("\n", $opts['eager_selectors'])) . '</textarea><p class="description">' . esc_html__('One CSS selector per line.', 'gm2-wordpress-suite') . '</p></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Lite Embeds', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[lite_embeds]" value="1" ' . checked($opts['lite_embeds'], true, false) . ' /></td></tr>';
            echo '</table>';
            submit_button();
            echo '</form>';
        } elseif ($tab === 'scripts') {
            echo '<form method="post">';
            wp_nonce_field('gm2_netpayload_settings');
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th scope="row">' . esc_html__('Asset Budget', 'gm2-wordpress-suite') . '</th><td><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[asset_budget]" value="1" ' . checked($opts['asset_budget'], true, false) . ' /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__('Asset Budget Limit (MB)', 'gm2-wordpress-suite') . '</th><td><input type="number" step="0.1" min="0" name="' . esc_attr(self::OPTION_KEY) . '[asset_budget_limit]" value="' . esc_attr(round($opts['asset_budget_limit'] / 1024 / 1024, 2)) . '" /></td></tr>';
            echo '</table>';

            $handles = $stats['handles'] ?? ['scripts' => [], 'styles' => []];
            echo '<h2>' . esc_html__('Handle Auditor', 'gm2-wordpress-suite') . '</h2>';
            foreach (['scripts' => esc_html__('Scripts', 'gm2-wordpress-suite'), 'styles' => esc_html__('Styles', 'gm2-wordpress-suite')] as $type => $label) {
                $list = $handles[$type] ?? [];
                if (!$list) {
                    continue;
                }
                uasort($list, function ($a, $b) { return intval($b['bytes'] ?? 0) <=> intval($a['bytes'] ?? 0); });
                echo '<h3>' . esc_html($label) . '</h3>';
                echo '<table class="widefat"><thead><tr><th>' . esc_html__('Handle', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Size', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Dependencies', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Loaded On', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Rules', 'gm2-wordpress-suite') . '</th></tr></thead><tbody>';
                foreach ($list as $h => $info) {
                    $size = number_format_i18n(intval($info['bytes']) / 1024, 2) . ' KB';
                    $deps = implode(', ', array_map('esc_html', $info['deps'] ?? []));
                    $ctxs = implode(', ', array_map('esc_html', $info['contexts'] ?? []));
                    $rule = $opts['handle_rules'][$type][$h] ?? [];
                    echo '<tr><td>' . esc_html($h) . '</td><td>' . esc_html($size) . '</td><td>' . $deps . '</td><td>' . $ctxs . '</td><td>';
                    echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[handle_rules][' . esc_attr($type) . '][' . esc_attr($h) . '][dequeue_front]" value="1" ' . checked($rule['dequeue']['front_page'] ?? false, true, false) . ' /> ' . esc_html__('Front', 'gm2-wordpress-suite') . '</label><br />';
                    echo '<label>' . esc_html__('Template', 'gm2-wordpress-suite') . ': <input type="text" name="' . esc_attr(self::OPTION_KEY) . '[handle_rules][' . esc_attr($type) . '][' . esc_attr($h) . '][page_template]" value="' . esc_attr($rule['dequeue']['page_template'] ?? '') . '" /></label><br />';
                    echo '<label>' . esc_html__('Shortcode', 'gm2-wordpress-suite') . ': <input type="text" name="' . esc_attr(self::OPTION_KEY) . '[handle_rules][' . esc_attr($type) . '][' . esc_attr($h) . '][shortcode]" value="' . esc_attr($rule['dequeue']['shortcode'] ?? '') . '" /></label><br />';
                    if ($type === 'scripts') {
                        $attr = $rule['attr'] ?? '';
                        echo '<label>' . esc_html__('Attr', 'gm2-wordpress-suite') . ': <select name="' . esc_attr(self::OPTION_KEY) . '[handle_rules][scripts][' . esc_attr($h) . '][attr]"><option value="">' . esc_html__('None', 'gm2-wordpress-suite') . '</option><option value="defer"' . selected($attr, 'defer', false) . '>defer</option><option value="async"' . selected($attr, 'async', false) . '>async</option></select></label>';
                    } else {
                        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[handle_rules][styles][' . esc_attr($h) . '][inline]" value="1" ' . checked($rule['inline'] ?? false, true, false) . ' /> ' . esc_html__('Inline ≤2KB', 'gm2-wordpress-suite') . '</label>';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            }
            submit_button();
            echo '</form>';
        }

        echo '</div>';
    }

    /** Register REST route for beacon data. */
    public static function register_rest_route(): void {
        register_rest_route('gm2/v1', '/netpayload', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback'            => [__CLASS__, 'handle_rest'],
        ]);
    }

    /** Handle beacon POST and store rolling average. */
    public static function handle_rest(\WP_REST_Request $request) {
        $payload = intval($request['payload']);
        $budget  = intval($request['budget']);
        $stats   = get_option(self::STATS_KEY, ['samples' => [], 'average' => 0, 'budget' => 0]);
        if (!is_array($stats['samples'] ?? null)) {
            $stats['samples'] = [];
        }
        $now = time();
        $stats['samples'][] = ['t' => $now, 'p' => $payload];
        $cutoff = $now - 7 * DAY_IN_SECONDS;
        $stats['samples'] = array_values(array_filter($stats['samples'], function ($s) use ($cutoff) {
            return isset($s['t']) && $s['t'] >= $cutoff;
        }));
        $total = 0;
        foreach ($stats['samples'] as $s) {
            $total += intval($s['p']);
        }
        $count = count($stats['samples']);
        $stats['average'] = $count ? $total / $count : 0;
        $stats['budget']  = $budget > 0 ? $budget : intval($stats['budget'] ?? 0);
        update_option(self::STATS_KEY, $stats, false);
        return rest_ensure_response(['average' => $stats['average'], 'budget' => $stats['budget']]);
    }

    /** Activation: create options. */
    public static function activate(bool $network_wide = false): void {
        $defaults = self::$defaults;
        if ($network_wide && is_multisite()) {
            add_site_option(self::OPTION_KEY, $defaults, '', 'no');
        }
        add_option(self::OPTION_KEY, $defaults, '', 'no');
        add_option(self::STATS_KEY, ['samples' => [], 'average' => 0, 'budget' => intval($defaults['asset_budget_limit'] / 1024)], '', 'no');
    }

    /** Deactivation: clear scheduled hooks. */
    public static function deactivate(bool $network_wide = false): void {
        wp_clear_scheduled_hook('gm2_netpayload_cron');
    }
}
